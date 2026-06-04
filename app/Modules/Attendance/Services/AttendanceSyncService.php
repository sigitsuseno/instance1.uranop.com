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
    public function syncPeriod(string $startDate, string $endDate): array
    {
        Log::info('AttendanceSync: starting period sync', [
            'start' => $startDate,
            'end'   => $endDate,
        ]);

        $rosters = EmployeeShiftRoster::with(['shift', 'employee'])
            ->whereBetween('date', [$startDate, $endDate])
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

        // ── 3. Gak ada log ─────────────────────────────────────
        if ($logs->isEmpty()) {
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
            'FIXED' => $this->detectFixed($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
            default => $this->detectShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
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

    // ─── Detectors ──────────────────────────────────────────────────

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

        $hasBoth = $checkIn && $checkOut;
        $hasOne  = ($checkIn && !$checkOut) || (!$checkIn && $checkOut);

        return [
            'check_in'    => $checkIn,
            'check_out'   => $checkOut,
            'status'      => $hasBoth ? AttendancePrepare::STATUS_HADIR : AttendancePrepare::STATUS_HADIR,
            'has_in'      => !is_null($checkIn),
            'has_out'     => !is_null($checkOut),
            'is_holiday'  => $isHoliday,
            'is_sunday'   => $isSunday,
        ];
    }

    /**
     * SHIFT / FLEX_SHIFT / LONGSHIFT / SPLIT / FLEXI / HOURLY / ON_CALL / SEASONAL:
     * Match scan dalam window check_in_start..check_in_end dan check_out_start..check_out_end.
     */
    protected function detectShift(
        Collection $logs,
        ?Shift $shift,
        string $dateStr,
        bool $isHoliday,
        bool $isSunday,
        EmployeeShiftRoster $roster
    ): array {
        // Holiday / off-day dengan log: tetap proses sebagai event khusus
        $isOffDay = $isHoliday || $isSunday || $roster->is_sun;

        if ($isOffDay && !$isHoliday && !$isSunday && $roster->is_sun) {
            // Minggu biasa — first/last aja
            $checkIn  = $logs->first() ? Carbon::parse($logs->first()->scan_datetime) : null;
            $checkOut = $logs->count() > 1 ? Carbon::parse($logs->last()->scan_datetime) : null;

            return [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'status'     => AttendancePrepare::STATUS_OFF,
                'has_in'     => !is_null($checkIn),
                'has_out'    => !is_null($checkOut),
                'is_holiday' => $isHoliday,
                'is_sunday'  => true,
            ];
        }

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
        // Untuk overnight shift, pakai overnight window
        if ($shift->is_overnight) {
            $checkOutLog = $this->findLogInWindow(
                $logs,
                $dateStr,
                $shift->check_out_overnight_start,
                $shift->check_out_overnight_end,
                $shift->work_hour_end,
                true // overnight: next day
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

        $hasBoth = $checkIn && $checkOut;

        return [
            'check_in'   => $checkIn,
            'check_out'  => $checkOut,
            'status'     => $hasBoth ? AttendancePrepare::STATUS_HADIR : AttendancePrepare::STATUS_HADIR,
            'has_in'     => !is_null($checkIn),
            'has_out'    => !is_null($checkOut),
            'is_holiday' => $isHoliday,
            'is_sunday'  => $isSunday,
        ];
    }

    // ─── Save ────────────────────────────────────────────────────────

    /**
     * Simpan / update record attendance_prepare.
     * Includes kalkulasi via AttendanceCalculatorService.
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

        // Kalkulasi
        $tempPrepare = new AttendancePrepare([
            'employee_id' => $roster->employee_id,
            'date'        => $dateStr,
            'check_in'    => $result['check_in'] ?? null,
            'check_out'   => $result['check_out'] ?? null,
        ]);

        $calc = $this->calculator->calculate($tempPrepare, $shift, $isHoliday, $isSunday);

        // Tentukan status akhir
        $status = $result['status'];
        if (!in_array($status, [
            AttendancePrepare::STATUS_CUTI,
            AttendancePrepare::STATUS_IZIN,
            AttendancePrepare::STATUS_SAKIT,
            AttendancePrepare::STATUS_LIBUR,
            AttendancePrepare::STATUS_OFF,
            AttendancePrepare::STATUS_ABSENT,
        ])) {
            // Re-derive: hadir / terlambat
            $status = $calc['late_minutes'] > 0
                ? AttendancePrepare::STATUS_TERLAMBAT
                : AttendancePrepare::STATUS_HADIR;
        }

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

        $data = [
            'employee_id'   => $roster->employee_id,
            'date'          => $dateStr,
            'periode_start' => $periode['start'],
            'periode_end'   => $periode['end'],
            'check_in'      => $result['check_in'] ?? null,
            'check_out'     => $result['check_out'] ?? null,
            'schedule_in'   => $shift?->work_hour_start,
            'schedule_out'  => $shift?->work_hour_end,
            'late_minutes'  => $calc['late_minutes'],
            'lm'            => $calc['lm'],
            'lm_count'      => $calc['lm_count'],
            'overtime'      => $calc['overtime'],
            'overtime_count' => $calc['overtime_count'],
            'status'        => $status,
            'review_status' => $reviewStatus,
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
     * Dapatkan status leave/izin/sakit untuk karyawan pada tanggal tertentu.
     * Return null jika tidak ada leave.
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

        $code = strtoupper(substr($leave->leaveType->code, 0, 1));

        return match ($code) {
            'C' => AttendancePrepare::STATUS_CUTI,
            'I' => AttendancePrepare::STATUS_IZIN,
            'S' => AttendancePrepare::STATUS_SAKIT,
            default => AttendancePrepare::STATUS_CUTI, // fallback: cuti
        };
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

        return [
            'total'       => $query->count(),
            'hadir'       => (clone $query)->where('status', AttendancePrepare::STATUS_HADIR)->count(),
            'terlambat'   => (clone $query)->where('status', AttendancePrepare::STATUS_TERLAMBAT)->count(),
            'absent'      => (clone $query)->where('status', AttendancePrepare::STATUS_ABSENT)->count(),
            'cuti'        => (clone $query)->where('status', AttendancePrepare::STATUS_CUTI)->count(),
            'izin'        => (clone $query)->where('status', AttendancePrepare::STATUS_IZIN)->count(),
            'sakit'       => (clone $query)->where('status', AttendancePrepare::STATUS_SAKIT)->count(),
            'libur'       => (clone $query)->where('status', AttendancePrepare::STATUS_LIBUR)->count(),
            'off'         => (clone $query)->where('status', AttendancePrepare::STATUS_OFF)->count(),
            'cek'         => (clone $query)->where('review_status', AttendancePrepare::REVIEW_CEK)->count(),
            'lengkap'     => (clone $query)->where('review_status', AttendancePrepare::REVIEW_LENGKAP)->count(),
            'locked'      => (clone $query)->where('is_locked', true)->count(),
        ];
    }
}
