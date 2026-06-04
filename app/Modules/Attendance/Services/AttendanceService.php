<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceAutolog;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\AttConfig;
use App\Modules\Attendance\Models\ConsecutiveDay;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Models\ScanDetectionConfig;
use App\Modules\Attendance\Services\AttendanceCalculatorService;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\WorkingCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Auto-proses scan mentah (RawLog) menjadi autolog per hari.
     *
     * Flow:
     * 1. Ambil scan mentah yang belum diproses (is_processed = false)
     * 2. Group by employee + date
     * 3. Deteksi scan type (in/out/break) via ScanDetectionConfig
     * 4. Hitung status (present/late/absent) berdasarkan jadwal
     * 5. Simpan ke att_autologs
     * 6. Tandai scan mentah sebagai processed
     *
     * @param string|null $date Specific date or null for today
     * @return array [processed => int, autologs => int, errors => array]
     */
    public function processAutologs(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        Log::info('AttendanceService: Memulai auto-proses autologs', ['date' => $date]);

        $result = ['processed' => 0, 'autologs' => 0, 'errors' => []];

        // Ambil scan mentah yang belum diproses untuk tanggal tersebut
        $rawLogs = RawLog::whereDate('scan_datetime', $date)
            ->where('is_processed', false)
            ->orderBy('scan_datetime')
            ->get();

        if ($rawLogs->isEmpty()) {
            Log::info('AttendanceService: Tidak ada scan mentah untuk diproses', ['date' => $date]);
            return $result;
        }

        // Group by employee_code (PIN)
        $grouped = $rawLogs->groupBy('employee_code');

        // Load scan detection configs
        $scanConfigs = ScanDetectionConfig::active()->get();

        foreach ($grouped as $employeeCode => $scans) {
            try {
                $employee = Employee::where('nip', $employeeCode)
                    ->orWhere('employee_code', $employeeCode)
                    ->first();

                $employeeId = $employee?->id;

                // Deteksi scan types
                $scanData = [];
                foreach ($scans as $scan) {
                    $scanTime = Carbon::parse($scan->scan_datetime);
                    $timeString = $scanTime->format('H:i');

                    $scanType = null;
                    foreach ($scanConfigs as $config) {
                        $detected = $config->detectScanType($timeString);
                        if ($detected) {
                            $scanType = $detected;
                            break;
                        }
                    }

                    // Fallback detection: sebelum 12:00 = in, setelah = out
                    if (!$scanType) {
                        $scanType = $scanTime->hour < 12 ? 'in' : 'out';
                    }

                    $scanData[] = [
                        'datetime' => $scanTime->toDateTimeString(),
                        'time' => $timeString,
                        'type' => $scanType,
                        'machine' => $scan->machine_sn ?? $scan->machine_name ?? 'Unknown',
                        'verify' => $scan->verify_type,
                    ];
                }

                // Dapatkan scan pertama & terakhir
                $firstScan = Carbon::parse($scans->first()->scan_datetime);
                $lastScan = Carbon::parse($scans->last()->scan_datetime);

                // Hitung status: bandingkan dengan config default_in_time
                $defaultInTime = AttConfig::getValue('default_in_time', '08:00');
                $lateTolerance = AttConfig::getValue('late_tolerance_minutes', 15);

                $inTime = Carbon::parse($date . ' ' . $defaultInTime);
                $lateMinutes = $firstScan->diffInMinutes($inTime, false);
                $lateMinutes = max(0, $lateMinutes > $lateTolerance ? $lateMinutes : 0);

                // Status
                $status = 'present';
                if ($lateMinutes > 0) {
                    $status = 'late';
                }

                // Cek apakah hari libur
                $isHoliday = Holiday::whereDate('date', $date)->exists();
                $isOff = false;

                if ($employee) {
                    // Check working calendar
                    $dayOfWeek = Carbon::parse($date)->dayOfWeek;
                    $calendar = WorkingCalendar::where('year', Carbon::parse($date)->year)
                        ->first();

                    if ($calendar) {
                        $daysOff = $calendar->days_off ?? [0, 6]; // default Sat-Sun
                        $isOff = in_array($dayOfWeek, $daysOff);
                    }

                    // Check specific holiday/leave day
                    // ...
                }

                if ($isHoliday || $isOff) {
                    $status = $isHoliday ? 'holiday' : 'off';
                }

                // Simpan atau update autolog
                $autolog = AttendanceAutolog::updateOrCreate(
                    ['employee_id' => $employeeId, 'date' => $date],
                    [
                        'first_scan' => $firstScan,
                        'last_scan' => $lastScan,
                        'total_scans' => $scans->count(),
                        'status' => $status,
                        'late_minutes' => $lateMinutes,
                        'early_minutes' => 0,
                        'overtime_minutes' => 0,
                        'scan_data' => $scanData,
                        'source' => 'auto',
                    ]
                );

                $result['autologs']++;

                // Tandai scan mentah sebagai processed
                RawLog::whereIn('id', $scans->pluck('id'))
                    ->update(['is_processed' => true, 'processed_at' => now()]);

                $result['processed'] += $scans->count();

            } catch (\Throwable $e) {
                $result['errors'][] = "Gagal proses {$employeeCode} pada {$date}: " . $e->getMessage();
                Log::error('AttendanceService: Error proses autolog', [
                    'employee_code' => $employeeCode,
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('AttendanceService: Auto-proses selesai', $result);

        return $result;
    }

    /**
     * Generate attendance records dari autologs untuk rentang tanggal.
     * Membandingkan actual scan dengan schedule untuk menentukan status.
     */
    public function generateRecords(Carbon $startDate, Carbon $endDate): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $autologs = AttendanceAutolog::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id');

        foreach ($autologs as $employeeId => $logs) {
            $employee = Employee::find($employeeId);
            if (!$employee) continue;

            foreach ($logs as $autolog) {
                try {
                    $date = Carbon::parse($autolog->date);
                    $dayOfWeek = $date->dayOfWeek;

                    // Get schedule for this employee on this date
                    $schedule = $this->getEmployeeSchedule($employee, $date);

                    $record = AttendanceRecord::updateOrCreate(
                        ['employee_id' => $employeeId, 'date' => $date->toDateString()],
                        [
                            'uuid' => $record->uuid ?? \Str::uuid(),
                            'shift_id' => $schedule['shift_id'] ?? null,
                            'work_pattern_id' => $schedule['work_pattern_id'] ?? null,
                            'schedule_in' => $schedule['schedule_in'] ?? null,
                            'schedule_out' => $schedule['schedule_out'] ?? null,
                            'actual_in' => $autolog->first_scan,
                            'actual_out' => $autolog->last_scan,
                            'late_minutes' => $autolog->late_minutes,
                            'early_minutes' => $autolog->early_minutes,
                            'overtime_minutes' => $autolog->overtime_minutes,
                            'status' => $autolog->status,
                            'is_manual' => false,
                            'raw_data' => [
                                'autolog_id' => $autolog->id,
                                'scan_data' => $autolog->scan_data,
                            ],
                        ]
                    );

                    $result[$record->wasRecentlyCreated ? 'created' : 'updated']++;

                } catch (\Throwable $e) {
                    $result['errors'][] = "Gagal generate record employee {$employeeId} date {$autolog->date}: " . $e->getMessage();
                }
            }
        }

        return $result;
    }

    /**
     * Generate attendance summary per bulan.
     */
    public function generateSummary(string $period): array
    {
        // period format: YYYY-MM
        $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $records = AttendanceRecord::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('employee_id');

        foreach ($records as $employeeId => $empRecords) {
            try {
                $totalDays = $empRecords->count();
                $presentDays = $empRecords->whereIn('status', ['present', 'late', 'half_day'])->count();
                $lateDays = $empRecords->where('status', 'late')->count();
                $absentDays = $empRecords->where('status', 'absent')->count();
                $offDays = $empRecords->where('status', 'off')->count();
                $leaveDays = $empRecords->whereIn('status', ['permit', 'sick'])->count();
                $holidayDays = $empRecords->where('status', 'holiday')->count();
                $overtimeHours = $empRecords->sum('overtime_minutes') / 60;
                $totalLateMinutes = $empRecords->sum('late_minutes');

                $summary = AttendanceSummary::updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'period_start' => $startDate->toDateString(),
                        'period_end' => $endDate->toDateString(),
                    ],
                    [
                        'total_days' => $totalDays,
                        'present_days' => $presentDays,
                        'late_days' => $lateDays,
                        'absent_days' => $absentDays,
                        'off_days' => $offDays,
                        'leave_days' => $leaveDays,
                        'holiday_days' => $holidayDays,
                        'overtime_hours' => round($overtimeHours, 2),
                        'total_late_minutes' => $totalLateMinutes,
                        'summary_data' => [
                            'generated_at' => now()->toDateTimeString(),
                            'total_records' => $totalDays,
                        ],
                    ]
                );

                $result[$summary->wasRecentlyCreated ? 'created' : 'updated']++;

            } catch (\Throwable $e) {
                $result['errors'][] = "Gagal generate summary employee {$employeeId}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Lengkapi attendance — update check_in/check_out manual untuk record yang incomplete.
     *
     * @param int   $prepareId
     * @param array $data  ['check_in' => 'H:i', 'check_out' => 'H:i', 'status' => '...', 'notes' => '...']
     * @return AttendancePrepare
     */
    public function lengkapi(int $prepareId, array $data): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);

        if ($prepare->is_locked) {
            throw new \RuntimeException('Data sudah terkunci.');
        }

        $update = [];

        if (array_key_exists('check_in', $data)) {
            $update['check_in'] = $data['check_in']
                ? Carbon::parse($prepare->date->toDateString() . ' ' . $data['check_in'])
                : null;
        }

        if (array_key_exists('check_out', $data)) {
            $update['check_out'] = $data['check_out']
                ? Carbon::parse($prepare->date->toDateString() . ' ' . $data['check_out'])
                : null;
        }

        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }

        if (array_key_exists('notes', $data)) {
            $update['notes'] = $data['notes'];
        }

        if (array_key_exists('overtime', $data)) {
            $update['overtime'] = (int) $data['overtime'];
        }

        $prepare->update($update);

        // Update review_status berdasarkan kelengkapan
        $prepare->refresh();
        $prepare->update([
            'review_status' => $prepare->hasCompleteTimes()
                ? AttendancePrepare::REVIEW_LENGKAP
                : AttendancePrepare::REVIEW_CEK,
        ]);

        return $prepare->fresh();
    }

    /**
     * Bulk lengkapi attendance.
     */
    public function bulkLengkapi(array $records, int $userId): array
    {
        $updated = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            foreach ($records as $item) {
                $prepare = AttendancePrepare::find($item['id'] ?? 0);
                if (!$prepare || $prepare->is_locked) {
                    continue;
                }

                $updateData = [];

                if (isset($item['check_in'])) {
                    $updateData['check_in'] = $item['check_in']
                        ? Carbon::parse($prepare->date->toDateString() . ' ' . $item['check_in'])
                        : null;
                }

                if (isset($item['check_out'])) {
                    $updateData['check_out'] = $item['check_out']
                        ? Carbon::parse($prepare->date->toDateString() . ' ' . $item['check_out'])
                        : null;
                }

                if (isset($item['status'])) {
                    $updateData['status'] = $item['status'];
                }

                if (!empty($updateData)) {
                    $updateData['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                }

                $note = $item['notes'] ?? '';
                if ($note) {
                    $updateData['notes'] = ($prepare->notes ? $prepare->notes . "\n" : '')
                        . "Dilengkapi oleh: #{$userId}";
                }

                if (!empty($updateData)) {
                    $prepare->update($updateData);
                    $updated++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * Hitung lembur (overtime multiplier) untuk satu record.
     * Recalculate menggunakan AttendanceCalculatorService.
     */
    public function hitungLembur(AttendancePrepare $prepare, AttendanceCalculatorService $calculator): AttendancePrepare
    {
        $shift = null;

        // Coba dapatkan shift dari roster
        $roster = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $prepare->employee_id)
            ->whereDate('date', $prepare->date)
            ->first();

        if ($roster) {
            $shift = $roster->shift;
        }

        $isHoliday = \App\Modules\Schedule\Models\Holiday::where('date', $prepare->date->toDateString())->exists();
        $isSunday  = $prepare->date->isSunday();

        $calc = $calculator->calculate($prepare, $shift, $isHoliday, $isSunday);

        $prepare->update([
            'late_minutes'  => $calc['late_minutes'],
            'lm'            => $calc['lm'],
            'lm_count'      => $calc['lm_count'],
            'overtime'      => $calc['overtime'],
            'overtime_count' => $calc['overtime_count'],
        ]);

        return $prepare->fresh();
    }

    /**
     * Bulk hitung lembur untuk rentang tanggal.
     */
    public function bulkHitungLembur(string $startDate, string $endDate, AttendanceCalculatorService $calculator): array
    {
        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->where('is_locked', false)
            ->where(function ($q) {
                $q->where('overtime', '>', 0)
                  ->orWhere('lm', '>', 0);
            })
            ->get();

        $updated = 0;
        foreach ($prepares as $prepare) {
            $this->hitungLembur($prepare, $calculator);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * Lock attendance record — hanya superadmin & hrmanager.
     */
    public function lock(int $prepareId, int $userId): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);
        $prepare->lock($userId);

        return $prepare->fresh();
    }

    /**
     * Unlock attendance record — hanya superadmin & hrmanager.
     */
    public function unlock(int $prepareId): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);
        $prepare->unlock();

        return $prepare->fresh();
    }

    /**
     * Bulk lock/unlock attendance records.
     */
    public function bulkLock(array $ids, int $userId, bool $lock = true): array
    {
        $updated = 0;
        foreach ($ids as $id) {
            $prepare = AttendancePrepare::find($id);
            if (!$prepare) continue;

            if ($lock) {
                $prepare->lock($userId);
            } else {
                $prepare->unlock();
            }
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * Create attendance snapshot untuk supervisor/audit.
     */
    public function createSnapshot(string $period, string $context = 'main'): array
    {
        $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

        $result = ['snapshots' => 0, 'errors' => []];

        $summaries = AttendanceSummary::where('period_start', $startDate->toDateString())
            ->where('period_end', $endDate->toDateString())
            ->get();

        foreach ($summaries as $summary) {
            try {
                \App\Modules\Attendance\Models\AttendanceSnapshot::create([
                    'uuid' => \Str::uuid(),
                    'period' => $period,
                    'employee_id' => $summary->employee_id,
                    'snapshot_data' => $summary->toArray(),
                    'context' => $context,
                ]);

                $result['snapshots']++;

            } catch (\Throwable $e) {
                $result['errors'][] = "Gagal snapshot employee {$summary->employee_id}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Track consecutive working days untuk deteksi pelanggaran.
     */
    public function trackConsecutiveDays(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $maxConsecutiveDays = (int) AttConfig::getValue('max_consecutive_days', 7);

        $result = ['updated' => 0, 'alerts' => []];

        // Get active employees
        $employees = Employee::where('is_active', true)->get();

        foreach ($employees as $employee) {
            // Check records for this employee going backwards from date
            $consecutive = 0;
            $currentDate = $date->copy();
            $breakDate = null;
            $breakReason = null;

            for ($i = 0; $i < 60; $i++) { // max 60 days lookback
                $record = AttendanceRecord::where('employee_id', $employee->id)
                    ->where('date', $currentDate->toDateString())
                    ->first();

                if ($record && in_array($record->status, ['present', 'late', 'half_day'])) {
                    $consecutive++;
                    $currentDate->subDay();
                } else {
                    $breakDate = $currentDate->toDateString();
                    $breakReason = $record->status ?? 'no_record';
                    break;
                }
            }

            if ($consecutive > 0) {
                $startDate = $currentDate->addDay()->toDateString();
                $endDate = $date->toDateString();

                $consecutiveRecord = ConsecutiveDay::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    [
                        'consecutive_count' => $consecutive,
                        'break_reason' => $breakReason,
                        'alert_triggered' => $consecutive >= $maxConsecutiveDays,
                    ]
                );

                $result['updated']++;

                if ($consecutive >= $maxConsecutiveDays) {
                    $result['alerts'][] = [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'consecutive_days' => $consecutive,
                        'since' => $startDate,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Dapatkan jadwal kerja karyawan untuk tanggal tertentu.
     */
    protected function getEmployeeSchedule(Employee $employee, Carbon $date): ?array
    {
        // 1. Cek roster shift
        try {
            $roster = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($roster && $roster->shift) {
                return [
                    'shift_id' => $roster->shift_id,
                    'schedule_in' => $roster->shift->start_time,
                    'schedule_out' => $roster->shift->end_time,
                ];
            }
        } catch (\Throwable) {
            // Roster mungkin belum ada
        }

        // 2. Cek work pattern
        // Gunakan Employee Group untuk mapping ke work pattern
        try {
            $workPatternGroup = $employee->employeeGroups()
                ->whereHas('groupMaster', fn($q) => $q->where('code', 'work_pattern'))
                ->first();

            if ($workPatternGroup) {
                $workPattern = \App\Modules\Schedule\Models\WorkPattern::where('code', $workPatternGroup->reference_code)
                    ->first();

                if ($workPattern) {
                    $dayName = strtolower($date->englishDayOfWeek);
                    $detail = $workPattern->details()
                        ->where('day', $dayName)
                        ->first();

                    if ($detail) {
                        return [
                            'work_pattern_id' => $workPattern->id,
                            'schedule_in' => $detail->start_time,
                            'schedule_out' => $detail->end_time,
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Work pattern mapping mungkin belum ada
        }

        // 3. Default: gunakan config
        return [
            'schedule_in' => AttConfig::getValue('default_in_time', '08:00'),
            'schedule_out' => AttConfig::getValue('default_out_time', '17:00'),
        ];
    }

    /**
     * Import data absensi dari RawLog ke AttendanceLog.
     * Memetakan PIN ke employee_id.
     */
    public function matchRawLogsToEmployees(?string $batch = null): array
    {
        $result = ['matched' => 0, 'errors' => []];

        $query = RawLog::whereNull('employee_name')->orWhere('employee_name', '');
        if ($batch) {
            $query->where('import_batch', $batch);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            try {
                $employee = Employee::where('nip', $log->employee_code)
                    ->orWhere('employee_code', $log->employee_code)
                    ->first();

                if ($employee) {
                    $log->update(['employee_name' => $employee->full_name]);
                    $result['matched']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = "Gagal match {$log->employee_code}: " . $e->getMessage();
            }
        }

        return $result;
    }
}
