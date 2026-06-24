<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupervisorAttPrepareSync
{
    protected int $periodId;
    protected int $year;
    protected string $startDate;
    protected string $endDate;
    protected string $importBatch;

    protected int $inserted = 0;
    protected int $updated = 0;
    protected int $skipped = 0;

    /** @var array<string, array{category: string, is_paid: bool}>|null */
    protected static ?array $leaveTypeCache = null;

    /**
     * Sync data dari att_prepares ke attendance_autologs.
     *
     * - 2026, periode 1-4: hanya karyawan GRP-JKT (non-JKT sudah dari XLSX manual)
     * - 2026, periode 5-12: semua karyawan
     * - 2027+, semua periode: semua karyawan
     */
    public function sync(
        int $periodId,
        string $startDate,
        string $endDate
    ): array {
        $this->periodId = $periodId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        // Gunakan end_date year karena periode 1 start di Desember tahun sebelumnya
        // (misal: periode 1/2026 = 25 Des 2025 - 24 Jan 2026 → year = 2026)
        $this->year = Carbon::parse($endDate)->year;
        $this->importBatch = 'SYNC_'.date('YmdHis').'_'.uniqid();

        // Load leave type cache
        $this->loadLeaveTypeCache();

        Log::info('=== START ATTENDANCE SYNC FROM PREPARES ===', [
            'batch' => $this->importBatch,
            'period_id' => $periodId,
            'year' => $this->year,
            'start' => $startDate,
            'end' => $endDate,
        ]);

        // Query att_prepares dengan join ke roster
        $query = DB::table('att_prepares as ap')
            ->select([
                'ap.id',
                'ap.employee_id',
                'ap.date',
                'ap.check_in',
                'ap.check_out',
                'ap.late_minutes',
                'ap.overtime',
                'ap.lm',
                'ap.status as prepare_status',
                'ap.notes',
                'sr.id as roster_id',
                'sr.is_sun',
                'sr.is_sat',
                'sr.is_holiday',
                'sr.is_half_day',
            ])
            ->leftJoin('sch_employee_shift_rosters as sr', function ($join) {
                $join->on('ap.employee_id', '=', 'sr.employee_id')
                    ->on('ap.date', '=', 'sr.date');
            })
            ->whereBetween('ap.date', [$startDate, $endDate]);

        // ── Filter GRP-JKT untuk 2026 periode 1-4 ──
        if ($this->year === 2026 && $periodId >= 1 && $periodId <= 4) {
            $jktEmployeeIds = Employee::whereHas('groups', function ($q) {
                $q->where('reference_code', 'GRP-JKT');
            })->pluck('id');

            Log::info('Sync: 2026 period 1-4 — GRP-JKT only', [
                'jkt_employee_count' => $jktEmployeeIds->count(),
            ]);

            if ($jktEmployeeIds->isNotEmpty()) {
                $query->whereIn('ap.employee_id', $jktEmployeeIds->toArray());
            } else {
                Log::warning('No GRP-JKT employees found');
                return [
                    'inserted' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                ];
            }
        } else {
            Log::info('Sync: All employees', [
                'year' => $this->year,
                'period_id' => $periodId,
            ]);
        }

        $prepares = $query->orderBy('ap.date')
            ->orderBy('ap.employee_id')
            ->get();

        Log::info('Prepares query result', ['count' => $prepares->count()]);

        if ($prepares->isEmpty()) {
            Log::warning('No att_prepares records found for period', [
                'start' => $startDate,
                'end' => $endDate,
            ]);

            return [
                'inserted' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        DB::beginTransaction();
        try {
            foreach ($prepares as $prepare) {
                $this->processPrepareRecord($prepare);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Attendance sync from prepares failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::info('Sync from prepares completed', [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ]);

        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * Proses satu record att_prepares → upsert ke attendance_autologs.
     *
     * Mapping sama persis dengan AttendanceImportFromPrepares::processPrepareRecord().
     */
    protected function processPrepareRecord(object $prepare): void
    {
        $date = $prepare->date;

        // company_id & branch_id: multi-instance, tidak ada
        $recordCompanyId = 1;
        $recordBranchId = null;

        // Parse check_in/check_out
        $checkIn = $prepare->check_in
            ? Carbon::parse($prepare->check_in)
            : null;
        $checkOut = $prepare->check_out
            ? Carbon::parse($prepare->check_out)
            : null;

        // Hitung overtime_duration: overtime + lm (raw, menit)
        $overtimeRaw = (int) ($prepare->overtime ?? 0);
        $lmRaw = (int) ($prepare->lm ?? 0);
        $overtimeDuration = $overtimeRaw + $lmRaw;

        // Late duration
        $lateDuration = (int) ($prepare->late_minutes ?? 0);

        // Normalisasi status
        $prepareStatus = strtolower(trim($prepare->prepare_status ?? ''));
        $normalizedStatus = $this->normalizeStatus($prepareStatus);

        // Durasi izin/sakit
        $izinDuration = ($normalizedStatus === 'izin') ? 1 : 0;
        $sakitDuration = ($normalizedStatus === 'sakit') ? 1 : 0;
        $isLeave = ($normalizedStatus === 'leave') ? 1 : 0;

        // Deduct day
        $deductDay = null;
        if ($normalizedStatus === 'izin') {
            $deductDay = 1;
        } elseif ($normalizedStatus === 'leave') {
            $deductDay = 0;
        } elseif ($normalizedStatus === 'sakit') {
            $deductDay = 0;
        }

        // Scan count
        $scanCount = 0;
        if ($checkIn) {
            $scanCount++;
        }
        if ($checkOut) {
            $scanCount++;
        }

        // Data untuk upsert
        $autologData = [
            'company_id' => $recordCompanyId,
            'branch_id' => $recordBranchId,
            'employee_id' => $prepare->employee_id,
            'employee_shift_roster_id' => $prepare->roster_id,
            'date' => $date,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'actual_in' => $checkIn,
            'actual_out' => $checkOut,
            'check_in_log_id' => null,
            'check_out_log_id' => null,
            'import_batch' => $this->importBatch,
            'status' => $normalizedStatus,
            'late_duration' => $lateDuration,
            'early_leave_duration' => 0,
            'overtime_duration' => $overtimeDuration,
            'deduct_attendance' => 0,
            'is_half_day' => $prepare->is_half_day ?? 0,
            'is_sun' => $prepare->is_sun ?? 0,
            'is_sat' => $prepare->is_sat ?? 0,
            'is_holiday' => $prepare->is_holiday ?? 0,
            'is_leave' => $isLeave,
            'is_manual_edit' => 0,
            'last_edited_at' => null,
            'last_edited_by' => null,
            'holiday_overtime' => ($prepare->is_holiday && $overtimeDuration > 0) ? 1 : 0,
            'is_locked' => 0,
            'locked_at' => null,
            'locked_by' => null,
            'notes' => $prepare->notes ?? 'Synced from att_prepares',
            'metadata' => json_encode([
                'source' => 'att_prepares_sync',
                'prepare_id' => $prepare->id,
                'synced_at' => now()->toDateTimeString(),
                'period_id' => $this->periodId,
            ]),
            'scan_count' => $scanCount,
            'leave_id' => null,
            'deduct_day' => $deductDay,
            'izin_duration' => $izinDuration,
            'sakit_duration' => $sakitDuration,
            'overtime_converted_hours' => $overtimeDuration > 0
                ? round($overtimeDuration / 60, 2)
                : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Upsert: WHERE employee_id + date
        $exists = SupervisorAttendance::where('employee_id', $prepare->employee_id)
            ->where('date', $date)
            ->exists();

        if ($exists) {
            SupervisorAttendance::where('employee_id', $prepare->employee_id)
                ->where('date', $date)
                ->update($autologData);
            $this->updated++;
        } else {
            SupervisorAttendance::create($autologData);
            $this->inserted++;
        }
    }

    /**
     * Normalisasi status dari att_prepares ke attendance_autologs.
     *
     * Mapping sama dengan AttendanceImportFromPrepares:
     *   hadir  → present
     *   absent → absent
     *   libur  → holiday (jika is_holiday) / off
     *   off    → off
     *   SKT    → sakit
     *   ITM/IMT/IPA/IZN → izin
     *   CT/CM/CKM/CH/CTM/CTK/CTH/CTI/CB → leave
     */
    protected function normalizeStatus(string $prepareStatus): string
    {
        $directMap = [
            'hadir' => 'present',
            'absent' => 'absent',
            'libur' => 'holiday',
            'off' => 'off',
            'holiday' => 'holiday',
            'present' => 'present',
        ];

        if (isset($directMap[$prepareStatus])) {
            return $directMap[$prepareStatus];
        }

        // Cek leave type cache
        $upperStatus = strtoupper($prepareStatus);
        $leaveInfo = static::$leaveTypeCache[$upperStatus] ?? null;

        if ($leaveInfo) {
            return match ($leaveInfo['category']) {
                'sick' => 'sakit',
                'permit' => 'izin',
                'leave' => 'leave',
                default => 'leave',
            };
        }

        // Fallback
        return 'absent';
    }

    /**
     * Load LeaveType codes ke static cache.
     */
    protected static function loadLeaveTypeCache(): void
    {
        if (static::$leaveTypeCache !== null) {
            return;
        }

        static::$leaveTypeCache = LeaveType::where('is_active', true)
            ->get()
            ->mapWithKeys(fn($lt) => [
                strtoupper($lt->code) => [
                    'category' => $lt->category ?? 'leave',
                    'is_paid' => $lt->is_paid ?? true,
                ],
            ])
            ->toArray();
    }
}
