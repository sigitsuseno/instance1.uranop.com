<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync data fingerprint (att_raw_logs) → att_prepares per hari.
 *
 * Flow:
 *   EmployeeShiftRoster → RawLog → Match check_in/check_out → Calculate → Save att_prepares
 *
 * Port dari hris-system AttendanceSyncService (989 baris) — disederhanakan
 * tanpa multi-tenant, tanpa att_logs intermediate.
 */
class AttendanceSyncService
{
    protected AttendanceCalculatorService $calculator;

    /** @var array<string, bool> Cache holiday per tanggal */
    protected array $holidayCache = [];

    /** @var array<int, array<string, LeaveRequest|null>> Cache leave per employee per date */
    protected array $leaveCache = [];

    public function __construct(AttendanceCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    // ─── PUBLIC: Sync Period ────────────────────────────────────────

    /**
     * Sync attendance untuk satu rentang tanggal.
     * Membaca roster → raw_logs → save ke att_prepares.
     *
     * @return array{processed: int, failed: int, errors: array}
     */
    public function syncPeriod(string $startDate, string $endDate, ?int $employeeId = null): array
    {
        Log::info('AttendanceSync: starting period sync', [
            'start' => $startDate,
            'end'   => $endDate,
            'employee_id' => $employeeId,
        ]);

        $rosters = EmployeeShiftRoster::with(['shift', 'employee'])
            ->whereBetween('date', [$startDate, $endDate])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        if ($rosters->isEmpty()) {
            Log::warning('AttendanceSync: no rosters found for period', [
                'start' => $startDate,
                'end'   => $endDate,
            ]);

            return ['processed' => 0, 'failed' => 0, 'errors' => []];
        }

        // Preload holidays untuk range
        $this->preloadHolidays($startDate, $endDate);

        // Preload approved leaves untuk range
        $this->preloadLeaves($startDate, $endDate);

        $processed = 0;
        $failed    = 0;
        $errors    = [];

        // Group by date for batch raw_log query
        $dateGroups = $rosters->groupBy(fn($r) => $r->date->toDateString());

        foreach ($dateGroups as $dateStr => $dayRosters) {
            // Ambil SEMUA raw_logs untuk tanggal ini (biar efisien, 1 query)
            $allLogs = RawLog::whereDate('scan_datetime', $dateStr)
                ->orderBy('scan_datetime')
                ->get()
                ->groupBy('employee_code');

            foreach ($dayRosters as $roster) {
                DB::beginTransaction();
                try {
                    $this->processRoster($roster, $dateStr, $allLogs);
                    DB::commit();
                    $processed++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $failed++;
                    $errors[] = [
                        'employee_id' => $roster->employee_id,
                        'date'        => $dateStr,
                        'error'       => $e->getMessage(),
                        'file'        => $e->getFile(),
                        'line'        => $e->getLine(),
                    ];
                    Log::error('AttendanceSync: roster failed', [
                        'employee_id' => $roster->employee_id,
                        'date'        => $dateStr,
                        'error'       => $e->getMessage(),
                        'file'        => $e->getFile(),
                        'line'        => $e->getLine(),
                        'trace'       => $e->getTraceAsString(),
                    ]);
                }
            }
        }

        Log::info('AttendanceSync: period sync done', [
            'processed' => $processed,
            'failed'    => $failed,
        ]);

        return ['processed' => $processed, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * Sync satu hari untuk satu karyawan.
     */
    public function syncSingleDay(int $employeeId, string $date): ?AttendancePrepare
    {
        $roster = EmployeeShiftRoster::with(['shift', 'employee'])
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->first();

        if (!$roster) {
            return null;
        }

        $logs = RawLog::whereDate('scan_datetime', $date)
            ->orderBy('scan_datetime')
            ->get()
            ->groupBy('employee_code');

        // Overnight: juga ambil log besoknya
        if ($roster->shift && $roster->shift->is_overnight) {
            $nextDate = Carbon::parse($date)->addDay()->toDateString();
            $nextLogs = RawLog::whereDate('scan_datetime', $nextDate)
                ->orderBy('scan_datetime')
                ->get()
                ->groupBy('employee_code');
            // Merge next day logs into main collection
            foreach ($nextLogs as $code => $items) {
                if (isset($logs[$code])) {
                    $logs[$code] = $logs[$code]->merge($items)->sortBy('scan_datetime')->values();
                } else {
                    $logs[$code] = $items;
                }
            }
        }

        return $this->processRoster($roster, $date, $logs);
    }

    // ─── CORE: Process Single Roster ─────────────────────────────────

    protected function processRoster(
        EmployeeShiftRoster $roster,
        string $dateStr,
        Collection $allLogs
    ): AttendancePrepare {
        try {
            $employee = $roster->employee;
            $shift    = $roster->shift;

        // Safety: jika employee tidak ditemukan, skip
        if (!$employee) {
            Log::warning('AttendanceSync: roster tanpa employee', [
                'roster_id' => $roster->id,
                'employee_id' => $roster->employee_id,
                'date' => $dateStr,
            ]);
            // Return empty prepare (tidak disimpan)
            return new AttendancePrepare();
        }
        $isSunday = Carbon::parse($dateStr)->isSunday();
        $isHoliday = $this->isHoliday($dateStr);

        // ── 1. Status dari Leave / Permit ──────────────────────
        $leaveStatus = $this->getLeaveStatus($roster->employee_id, $dateStr);
        if ($leaveStatus) {
            return $this->savePrepare($roster, $dateStr, $shift, [
                'check_in'      => null,
                'check_out'     => null,
                'status'        => $leaveStatus,
                'review_status' => AttendancePrepare::REVIEW_LENGKAP,
                'is_holiday'    => $isHoliday,
                'is_sunday'     => $isSunday,
            ]);
        }

        // ── 2. Dapatkan logs karyawan ini ──────────────────────
        $empCode = $employee->nip ?? $employee->employee_code;
        $logs = $allLogs->get($empCode, collect());

        // Overnight shift: perlu juga log dari hari berikutnya
        if ($shift && $shift->is_overnight) {
            $nextDateStr = Carbon::parse($dateStr)->addDay()->toDateString();
            $nextDayLogs = RawLog::whereDate('scan_datetime', $nextDateStr)
                ->where('employee_code', $empCode)
                ->orderBy('scan_datetime')
                ->get();
            if ($nextDayLogs->isNotEmpty()) {
                $logs = $logs->merge($nextDayLogs)->sortBy('scan_datetime')->values();
            }
        }

        // ── 3. Gak ada log ─────────────────────────────────────
        if ($logs->isEmpty()) {
            // SHIFT (satpam): holiday nggak ngaruh, cuma external_code yang menentukan
            if ($roster->work_pattern_type === 'SHIFT') {
                if ($roster->external_code && strtoupper($roster->external_code) === 'L') {
                    return $this->savePrepare($roster, $dateStr, $shift, [
                        'check_in'      => null,
                        'check_out'     => null,
                        'status'        => AttendancePrepare::STATUS_OFF,
                        'review_status' => AttendancePrepare::REVIEW_LENGKAP,
                        'is_holiday'    => $isHoliday,
                        'is_sunday'     => $isSunday,
                    ]);
                }
                // SHIFT tanpa external_code 'L' + no scan = absent
                return $this->savePrepare($roster, $dateStr, $shift, [
                    'check_in'      => null,
                    'check_out'     => null,
                    'status'        => AttendancePrepare::STATUS_ABSENT,
                    'review_status' => AttendancePrepare::REVIEW_CEK,
                    'is_holiday'    => $isHoliday,
                    'is_sunday'     => $isSunday,
                ]);
            }

            // Non-SHIFT: holiday & sunday normal
            if ($isHoliday) {
                return $this->savePrepare($roster, $dateStr, $shift, [
                    'check_in'      => null,
                    'check_out'     => null,
                    'status'        => AttendancePrepare::STATUS_LIBUR,
                    'review_status' => AttendancePrepare::REVIEW_LENGKAP,
                    'is_holiday'    => true,
                    'is_sunday'     => $isSunday,
                ]);
            }
            if ($roster->is_sun || $isSunday) {
                return $this->savePrepare($roster, $dateStr, $shift, [
                    'check_in'      => null,
                    'check_out'     => null,
                    'status'        => AttendancePrepare::STATUS_OFF,
                    'review_status' => AttendancePrepare::REVIEW_LENGKAP,
                    'is_holiday'    => $isHoliday,
                    'is_sunday'     => true,
                ]);
            }

            // External code 'L' = Libur/Off (non-SHIFT)
            if ($roster->external_code && strtoupper($roster->external_code) === 'L') {
                return $this->savePrepare($roster, $dateStr, $shift, [
                    'check_in'      => null,
                    'check_out'     => null,
                    'status'        => AttendancePrepare::STATUS_OFF,
                    'review_status' => AttendancePrepare::REVIEW_LENGKAP,
                    'is_holiday'    => $isHoliday,
                    'is_sunday'     => $isSunday,
                ]);
            }

            // Hari kerja, tapi tidak ada scan → absent
            return $this->savePrepare($roster, $dateStr, $shift, [
                'check_in'      => null,
                'check_out'     => null,
                'status'        => AttendancePrepare::STATUS_ABSENT,
                'review_status' => AttendancePrepare::REVIEW_CEK,
                'is_holiday'    => $isHoliday,
                'is_sunday'     => $isSunday,
            ]);
        }

        // ── 4. Match check_in & check_out ──────────────────────
        $workPatternType = $roster->work_pattern_type ?? 'FIXED';

        $result = match ($workPatternType) {
            'FIXED'       => $this->detectFixed($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'FLEX-SHIFT'  => $this->detectFlexShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'SHIFT'       => $this->detectShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'LONGSHIFT'   => $this->detectLongshift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'SPLIT'       => $this->detectSplit($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'FLEXI'       => $this->detectFlexi($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'HOURLY'      => $this->detectHourly($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'ON_CAL'      => $this->detectOnCall($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            'SEASONAL'    => $this->detectSeasonal($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            default       => $this->detectShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        };

        return $this->savePrepare($roster, $dateStr, $shift, $result);
        } catch (\Throwable $e) {
            Log::error('AttendanceSync: processRoster internal error', [
                'step'      => 'processRoster',
                'roster_id' => $roster->id,
                'date'      => $dateStr,
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // ─── Detectors (ported from hris-system) ────────────────────────
    //  Signature: detectXxx(Collection $logs, ?Shift $shift, string $dateStr,
    //                        bool $isHoliday, bool $isSunday, EmployeeShiftRoster $roster): array
    //  Return format: [check_in, check_out, status, has_in, has_out, is_holiday, is_sunday]
    //
    //  NOTE: Leave/permit checks are handled BEFORE detectors in processRoster().
    //        Each detector only handles the matching of fingerprint logs.

    /**
     * FIXED: scan pertama = check_in, scan terakhir = check_out.
     */
    protected function detectFixed(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        $checkInLog  = $logs->first();
        $checkOutLog = $logs->count() > 1 ? $logs->last() : null;

        $checkIn  = $checkInLog ? Carbon::parse($checkInLog->scan_datetime) : null;
        $checkOut = $checkOutLog ? Carbon::parse($checkOutLog->scan_datetime) : null;

        return [
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'status'      => AttendancePrepare::STATUS_HADIR,
            'has_in'      => !is_null($checkIn),
            'has_out'     => !is_null($checkOut),
            'is_holiday'  => $isHoliday,
            'is_sunday'   => $isSunday,
        ];
    }

    /**
     * FLEX-SHIFT: Window matching + holiday config dari shift metadata.
     * Port dari hris-system detectFlexShift().
     */
    protected function detectFlexShift(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        $isOffDay = $isSunday || $isHoliday;

        // ── Holiday / Sunday: pakai holiday config ──────────────
        if ($isOffDay) {
            // Baca holiday config dari shift metadata
            $metadata = $shift?->metadata ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?? [];
            }
            $holidayCheckInStart  = $metadata['holiday_check_in_start'] ?? '05:50:00';
            $holidayCheckInTarget = $metadata['holiday_check_in_target'] ?? '10:00:00';
            $holidayCheckOutTarget = $metadata['holiday_check_out_target'] ?? '19:00:00';
            $holidayMaxHours = $metadata['holiday_max_work_hours'] ?? 11;

            // Cari check_in: log setelah holiday_check_in_start
            $checkInLog = $this->findLogAfterTime($logs, $dateStr, $holidayCheckInStart, $holidayCheckInTarget);
            $checkIn = $checkInLog ? Carbon::parse($checkInLog->scan_datetime) : null;

            // Cari check_out: log setelah check_in, sebelum max hours
            $checkOut = null;
            if ($checkIn) {
                $maxCheckOutTime = (clone $checkIn)->addHours($holidayMaxHours);
                $checkOutCandidates = $logs->filter(function (RawLog $log) use ($checkIn, $maxCheckOutTime) {
                    $logTime = Carbon::parse($log->scan_datetime);
                    return $logTime->gt($checkIn) && $logTime->lte($maxCheckOutTime);
                });

                if ($checkOutCandidates->isNotEmpty()) {
                    $checkOutLog = $this->findClosestToTarget($checkOutCandidates, $dateStr, $holidayCheckOutTarget);
                    $checkOut = $checkOutLog ? Carbon::parse($checkOutLog->scan_datetime) : null;
                }
            }

            if (! $checkIn && ! $checkOut) {
                // Tidak ada scan sama sekali
                return [
                    'check_in'   => null,
                    'check_out'  => null,
                    'status'     => $isHoliday ? AttendancePrepare::STATUS_LIBUR : AttendancePrepare::STATUS_OFF,
                    'has_in'     => false,
                    'has_out'    => false,
                    'is_holiday' => $isHoliday,
                    'is_sunday'  => $isSunday,
                ];
            }

            // Ada scan di hari libur → hadir
            return [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'status'     => AttendancePrepare::STATUS_HADIR,
                'has_in'     => !is_null($checkIn),
                'has_out'    => !is_null($checkOut),
                'is_holiday' => $isHoliday,
                'is_sunday'  => $isSunday,
            ];
        }

        // ── Hari biasa: window matching ─────────────────────────
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * SHIFT: Window matching standar.
     * Port dari hris-system detectShift().
     */
    protected function detectShift(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * LONGSHIFT: Window matching (sama dengan SHIFT di sistem lama).
     * Port dari hris-system detectLongshift().
     */
    protected function detectLongshift(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * SPLIT: Window matching.
     * Port dari hris-system detectSplit().
     */
    protected function detectSplit(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * FLEXI: Window matching (late dihandle oleh calculator dengan flexi option).
     * Port dari hris-system detectFlexi().
     */
    protected function detectFlexi(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * HOURLY: Window matching.
     * Port dari hris-system detectHourly().
     */
    protected function detectHourly(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * ON_CALL: Window matching.
     * Port dari hris-system detectOnCall().
     */
    protected function detectOnCall(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * SEASONAL: Window matching.
     * Port dari hris-system detectSeasonal().
     */
    protected function detectSeasonal(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        return $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday);
    }

    /**
     * Shared worker untuk semua pattern berbasis window (SHIFT, LONGSHIFT, SPLIT, FLEXI, dll).
     * Logic: filter log by check_in_start..end & check_out_start..end, lalu ambil
     * yang terdekat dengan work_hour_start/end.
     */
    protected function detectShiftWorker(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday
    ): array {
        if (!$shift) {
            // Fallback: no shift → first/last
            $checkIn  = $logs->first() ? Carbon::parse($logs->first()->scan_datetime) : null;
            $checkOut = $logs->count() > 1 ? Carbon::parse($logs->last()->scan_datetime) : null;

            return [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'status'     => AttendancePrepare::STATUS_HADIR,
                'has_in'     => !is_null($checkIn),
                'has_out'    => !is_null($checkOut),
                'is_holiday' => $isHoliday,
                'is_sunday'  => $isSunday,
            ];
        }

        // CHECK IN: cari log dalam range check_in_start..check_in_end
        $checkInLog = $this->findLogInWindow(
            $logs,
            $dateStr,
            $shift->check_in_start,
            $shift->check_in_end,
            $shift->work_hour_start
        );

        // CHECK OUT: cari log dalam range check_out_start..check_out_end
        if ($shift->is_overnight) {
            $checkOutLog = $this->findLogInWindow(
                $logs,
                $dateStr,
                $shift->check_out_overnight_start,
                $shift->check_out_overnight_end,
                $shift->work_hour_end,
                true
            );
        } else {
            $checkOutLog = $this->findLogInWindow(
                $logs,
                $dateStr,
                $shift->check_out_start,
                $shift->check_out_end,
                $shift->work_hour_end
            );
        }

        $checkIn  = $checkInLog ? Carbon::parse($checkInLog->scan_datetime) : null;
        $checkOut = ($checkOutLog && (!$checkInLog || $checkOutLog->id !== $checkInLog->id))
            ? Carbon::parse($checkOutLog->scan_datetime)
            : null;

        return [
            'check_in'   => $checkIn,
            'check_out'  => $checkOut,
            'status'     => AttendancePrepare::STATUS_HADIR,
            'has_in'     => !is_null($checkIn),
            'has_out'    => !is_null($checkOut),
            'is_holiday' => $isHoliday,
            'is_sunday'  => $isSunday,
        ];
    }

    // ─── Save ────────────────────────────────────────────────────────

    /**
     * Simpan / update record attendance_prepare.
     * HANYA menyimpan hasil deteksi (check_in, check_out, status).
     * Kalkulasi (late, overtime, LM) dilakukan terpisah di proses "Hitung Lembur".
     */
    protected function savePrepare(
        EmployeeShiftRoster $roster,
        string $dateStr,
        ?Shift $shift,
        array $result
    ): AttendancePrepare {
        $date    = Carbon::parse($dateStr);
        $isHoliday = $result['is_holiday'] ?? false;
        $isSunday  = $result['is_sunday'] ?? false;

        // Gunakan updateOrCreate — tapi JANGAN overwrite record yang sudah di-lock
        $existing = AttendancePrepare::where('employee_id', $roster->employee_id)
            ->where('date', $dateStr)
            ->first();

        if ($existing && $existing->is_locked) {
            return $existing; // jangan sentuh yang sudah dikunci
        }

        // Tentukan status akhir (deteksi saja, tanpa kalkulasi late)
        $status = $result['status'];
        // Biarkan status dari detector apa adanya (hadir, absent, libur, off, dll)
        // Status terlambat akan ditentukan saat proses Hitung Lembur

        // Tentukan review_status
        $hasIn  = $result['has_in'] ?? !is_null($result['check_in'] ?? null);
        $hasOut = $result['has_out'] ?? !is_null($result['check_out'] ?? null);

        if ($existing) {
            $reviewStatus = $existing->review_status; // pertahankan
        } else {
            $reviewStatus = ($hasIn && $hasOut)
                ? AttendancePrepare::REVIEW_LENGKAP
                : AttendancePrepare::REVIEW_CEK;
        }

        // Periode 25-24
        $periode = $this->getPeriode($date);

        $scheduleIn  = $shift?->work_hour_start;
        $scheduleOut = $shift?->work_hour_end;

        // Deteksi slot spesial (modifier): pilih slot terdekat dari check_in
        if ($shift?->has_modifier && !empty($result['check_in'])) {
            $meta = $shift->metadata ?? [];
            if (!empty($meta['is_special']) && !empty($meta['special_hour_start'])) {
                $checkInTime = Carbon::parse($result['check_in']);
                $normalStart = Carbon::parse($dateStr . ' ' . ($meta['work_hour_start'] ?? $scheduleIn));
                $specialStart = Carbon::parse($dateStr . ' ' . $meta['special_hour_start']);

                if ($checkInTime->diffInMinutes($specialStart, true)
                    < $checkInTime->diffInMinutes($normalStart, true)) {
                    $scheduleIn  = $meta['special_hour_start'];
                    $scheduleOut = $meta['special_hour_end'] ?? $scheduleOut;
                }
            }
        }

        $data = [
            'employee_id'    => $roster->employee_id,
            'date'           => $dateStr,
            'periode_start'  => $periode['start'],
            'periode_end'    => $periode['end'],
            'check_in'       => $result['check_in'] ?? null,
            'check_out'      => $result['check_out'] ?? null,
            'schedule_in'    => $scheduleIn,
            'schedule_out'   => $scheduleOut,
            'late_minutes'   => 0,
            'lm'             => 0,
            'lm_count'       => 0,
            'overtime'       => 0,
            'overtime_count' => 0,
            'status'         => $status,
            'review_status'  => $reviewStatus,
        ];

        if ($existing) {
            $existing->update($data);
            return $existing->fresh();
        }

        return AttendancePrepare::create($data);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Cari log dalam window waktu tertentu.
     */
    protected function findLogInWindow(
        Collection $logs,
        string $dateStr,
        ?string $startTime,
        ?string $endTime,
        ?string $targetTime = null,
        bool $isOvernight = false
    ): ?RawLog {
        if (!$startTime || !$endTime || $logs->isEmpty()) {
            return null;
        }

        $baseDate = $dateStr;
        $start = Carbon::parse($baseDate . ' ' . $startTime);
        $end   = Carbon::parse($baseDate . ' ' . $endTime);

        if ($end < $start) {
            $end->addDay();
        }

        // Filter log dalam window
        $candidates = $logs->filter(function (RawLog $log) use ($start, $end) {
            $scanTime = Carbon::parse($log->scan_datetime);
            return $scanTime->between($start, $end);
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        // Cari yang paling dekat dengan target_time
        if ($targetTime) {
            $target = Carbon::parse($baseDate . ' ' . $targetTime);
            if ($isOvernight && $target->hour < 12) {
                $target->addDay();
            }

            return $candidates->sortBy(fn(RawLog $log) =>
                abs(Carbon::parse($log->scan_datetime)->diffInSeconds($target))
            )->first();
        }

        return $candidates->first();
    }

    /**
     * Cari log pertama setelah waktu tertentu (digunakan FLEX-SHIFT holiday).
     * Kalau ada multiple, ambil yang terdekat dengan targetTime.
     */
    protected function findLogAfterTime(
        Collection $logs,
        string $dateStr,
        string $afterTime,
        ?string $targetTime = null
    ): ?RawLog {
        if ($logs->isEmpty()) {
            return null;
        }

        $after = Carbon::parse($dateStr . ' ' . $afterTime);

        $candidates = $logs->filter(function (RawLog $log) use ($after) {
            return Carbon::parse($log->scan_datetime)->gt($after);
        });

        if ($candidates->isEmpty()) {
            return null;
        }

        if ($candidates->count() === 1 || !$targetTime) {
            return $candidates->first();
        }

        return $this->findClosestToTarget($candidates, $dateStr, $targetTime);
    }

    /**
     * Cari log yang paling dekat dengan target time.
     */
    protected function findClosestToTarget(
        Collection $logs,
        string $dateStr,
        string $targetTimeStr
    ): ?RawLog {
        if ($logs->isEmpty() || !$targetTimeStr) {
            return null;
        }

        $target = Carbon::parse($dateStr . ' ' . $targetTimeStr);

        return $logs->sortBy(fn(RawLog $log) =>
            abs(Carbon::parse($log->scan_datetime)->diffInSeconds($target))
        )->first();
    }

    /**
     * Cek apakah tanggal adalah hari libur.
     */
    protected function isHoliday(string $dateStr): bool
    {
        if (isset($this->holidayCache[$dateStr])) {
            return (bool) $this->holidayCache[$dateStr];
        }

        $isHoliday = Holiday::where('date', $dateStr)->exists();
        $this->holidayCache[$dateStr] = $isHoliday;

        return $isHoliday;
    }

    /**
     * Preload holidays untuk efisiensi.
     */
    protected function preloadHolidays(string $startDate, string $endDate): void
    {
        Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->each(function ($date) {
                // Pastikan key adalah string (Carbon::__toString auto-convert)
                $this->holidayCache[(string) $date] = true;
            });
    }

    /**
     * Preload approved leaves untuk range tanggal.
     */
    protected function preloadLeaves(string $startDate, string $endDate): void
    {
        $leaves = LeaveRequest::with('leaveType')
            ->where('status', 'approved')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q2) use ($startDate, $endDate) {
                      $q2->where('start_date', '<=', $startDate)
                         ->where('end_date', '>=', $endDate);
                  });
            })
            ->get();

        foreach ($leaves as $leave) {
            $current = Carbon::parse(max($leave->start_date->toDateString(), $startDate));
            $end     = Carbon::parse(min($leave->end_date->toDateString(), $endDate));

            while ($current <= $end) {
                $dateKey = $current->toDateString();
                if (!isset($this->leaveCache[$leave->employee_id])) {
                    $this->leaveCache[$leave->employee_id] = [];
                }
                $this->leaveCache[$leave->employee_id][$dateKey] = $leave;
                $current->addDay();
            }
        }
    }

    /**
     * Dapatkan status leave spesifik berdasarkan kode LeaveType.
     * Return kode leave type (lowercase) biar bisa langsung dipakai
     * sebagai status di att_prepares. Contoh: 'ct', 'cm', 'skt', 'itm', dll.
     */
    protected function getLeaveStatus(int $employeeId, string $dateStr): ?string
    {
        if (!isset($this->leaveCache[$employeeId]) || !is_array($this->leaveCache[$employeeId])) {
            return null;
        }

        $leave = $this->leaveCache[$employeeId][$dateStr] ?? null;

        if (!$leave || !$leave->leaveType) {
            return null;
        }

        // Return kode leave type apa adanya (lowercase) —
        // biar frontend bisa resolve ke nama spesifik (Cuti Tahunan, Sakit, dll)
        return strtolower($leave->leaveType->code);
    }

    /**
     * Periode 25-24.
     * Jika tanggal >= 25, periode = bulan ini 25 s/d bulan depan 24.
     * Jika tanggal < 25, periode = bulan lalu 25 s/d bulan ini 24.
     */
    protected function getPeriode(Carbon $date): array
    {
        if ($date->day >= 25) {
            $start = $date->copy()->day(25)->toDateString();
            $end   = $date->copy()->addMonth()->day(24)->toDateString();
        } else {
            $start = $date->copy()->subMonth()->day(25)->toDateString();
            $end   = $date->copy()->day(24)->toDateString();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get sync summary untuk dashboard/stats.
     */
    public function getSyncSummary(string $startDate, string $endDate): array
    {
        $query = AttendancePrepare::whereBetween('date', [$startDate, $endDate]);

        // Kumpulkan semua kode leave type aktif (lowercase)
        $leaveCodes = \App\Modules\Leave\Models\LeaveType::where('is_active', true)
            ->pluck('code')
            ->map(fn($c) => strtolower($c))
            ->toArray();

        return [
            'total'       => $query->count(),
            'hadir'       => (clone $query)->where('status', AttendancePrepare::STATUS_HADIR)->count(),
            'absent'      => (clone $query)->where('status', AttendancePrepare::STATUS_ABSENT)->count(),
            'libur'       => (clone $query)->where('status', AttendancePrepare::STATUS_LIBUR)->count(),
            'off'         => (clone $query)->where('status', AttendancePrepare::STATUS_OFF)->count(),
            'leave'       => (clone $query)->whereIn('status', $leaveCodes)->count(),
            'cek'         => (clone $query)->where('review_status', AttendancePrepare::REVIEW_CEK)->count(),
            'lengkap'     => (clone $query)->where('review_status', AttendancePrepare::REVIEW_LENGKAP)->count(),
            'locked'      => (clone $query)->where('is_locked', true)->count(),
        ];
    }
}
