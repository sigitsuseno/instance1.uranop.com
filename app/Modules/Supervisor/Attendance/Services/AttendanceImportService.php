<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Leave\Models\LeaveType;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Supervisor\Attendance\Imports\AttendanceDataFixImport;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import attendance dari sch_employee_shift_rosters ke attendance_autologs
 * dengan rules per work_pattern_type (FIXED / FLEX-SHIFT / SHIFT).
 *
 * Sumber data:
 *   1. Karyawan: sch_employee_shift_rosters (filter 6 group)
 *   2. Holiday: sch_holidays
 *   3. Cuti/Izin/Sakit: leave_requests + leave_types
 *   4. Scan: att_prepares (check_in, check_out, overtime)
 *   5. Jadwal: shift->work_hour_start / work_hour_end
 *
 * Rule utama: Excel > att_prepares
 * - Ada Excel (periode 1-6) → overwrite dengan XLSX
 * - Tidak ada Excel → data final = hasil Service ini
 */
class AttendanceImportService
{
    /** @var array<string> Group yang diproses */
    protected const GROUP_CODES = [
        'GRP-JKT',
        'GRP-ALLIN',
        'GRP-SPR',
        'GRP-GD',
        'GRP-SS',
        'GRP-SP1',
    ];

    /** @var array<int, string> Period yang punya Excel */
    protected const EXCEL_PERIODS = [1, 2, 3, 4, 5, 6];

    /** Maksimal lembur untuk FIXED & FLEX-SHIFT (dalam menit) */
    protected const MAX_OVERTIME_MINUTES = 180;

    /** @var array<string, array{category: string, is_paid: bool}>|null */
    protected static ?array $leaveTypeCache = null;

    protected string $startDate;
    protected string $endDate;
    protected int $periodId;
    protected string $importBatch;

    protected int $inserted = 0;
    protected int $updated = 0;
    protected int $skipped = 0;

    /**
     * Main entry point.
     */
    public function import(
        string $startDate,
        string $endDate,
        int $periodId
    ): array {
        $this->startDate   = $startDate;
        $this->endDate     = $endDate;
        $this->periodId    = $periodId;
        $this->importBatch = 'IMP_' . date('YmdHis') . '_' . uniqid();

        $this->loadLeaveTypeCache();

        Log::info('=== START ATTENDANCE IMPORT ===', [
            'batch'     => $this->importBatch,
            'period_id' => $periodId,
            'start'     => $startDate,
            'end'       => $endDate,
            'groups'    => self::GROUP_CODES,
        ]);

        // ── ① Dapatkan employee_id dari 6 group ──
        $groupEmployeeIds = EmployeeGroup::whereIn('reference_code', self::GROUP_CODES)
            ->pluck('employee_id')
            ->unique()
            ->values();

        Log::info('Group employee IDs', ['count' => $groupEmployeeIds->count()]);

        if ($groupEmployeeIds->isEmpty()) {
            Log::warning('No employees found in target groups');

            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
            ];
        }

        // ── ② Query roster (primary driver) ──
        $rosters = EmployeeShiftRoster::with(['shift', 'leave.leaveType'])
            ->whereIn('employee_id', $groupEmployeeIds->toArray())
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        Log::info('Roster query result', ['count' => $rosters->count()]);

        if ($rosters->isEmpty()) {
            Log::warning('No roster records found');

            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
            ];
        }

        // ── ③ Load holidays dari sch_holidays ──
        $holidayDates = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        Log::info('Holidays loaded', ['count' => count($holidayDates)]);

        // ── ④ Load att_prepares (key: employee_id_date) ──
        $preparesMap = $this->loadPreparesMap($rosters);

        Log::info('Prepares loaded', ['count' => count($preparesMap)]);

        // ── ⑤ Proses setiap roster ──
        DB::beginTransaction();
        try {
            foreach ($rosters as $roster) {
                $this->processRoster($roster, $holidayDates, $preparesMap);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Attendance import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        Log::info('Import from roster done', [
            'inserted' => $this->inserted,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
        ]);

        // ── ⑥ Excel overwrite (periode 1-6) ──
        $excelResult = null;
        if (in_array($periodId, self::EXCEL_PERIODS)) {
            Log::info('Period has Excel — running XLSX overwrite', ['period_id' => $periodId]);

            $periodFileMap = [
                1 => 'data_januari.xlsx',
                2 => 'februari.xlsx',
                3 => 'sampe_data.xlsx',
                4 => 'april.xlsx',
                5 => 'mei.xlsx',
                6 => 'juni.xlsx',
            ];

            $filePath = $periodFileMap[$periodId] ?? null;
            if ($filePath) {
                $excelResult = AttendanceDataFixImport::runImport(
                    userId: 1, // system
                    filePath: $filePath
                );

                Log::info('Excel overwrite result', $excelResult);
            }
        }

        return [
            'inserted' => $this->inserted,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
            'excel'    => $excelResult,
        ];
    }

    /**
     * Proses satu roster → upsert ke attendance_autologs.
     */
    protected function processRoster(EmployeeShiftRoster $roster, array $holidayDates, array $preparesMap): void
    {
        $date       = Carbon::parse($roster->date);
        $dateStr    = $date->toDateString();
        $dayOfWeek  = (int) $date->dayOfWeek; // 0=Minggu .. 6=Sabtu
        $isSunday   = $dayOfWeek === 0;
        $isHoliday  = in_array($dateStr, $holidayDates);

        // ── Data dari att_prepares (key: employee_id_date) ──
        $prepareKey = $roster->employee_id . '_' . $dateStr;
        $prepare    = $preparesMap[$prepareKey] ?? null;
        $checkIn    = $prepare['check_in'] ?? null;
        $checkOut   = $prepare['check_out'] ?? null;
        $overtime   = (int) ($prepare['overtime'] ?? 0);
        $lateMin    = (int) ($prepare['late_minutes'] ?? 0);

        // ── Data jadwal dari shift ──
        $shift       = $roster->shift;
        $schedulIn   = null;
        $schedulOut  = null;

        if ($shift && $shift->work_hour_start) {
            $schedulIn = Carbon::parse($dateStr . ' ' . $shift->work_hour_start);
        }
        if ($shift && $shift->work_hour_end) {
            $schedulOut = Carbon::parse($dateStr . ' ' . $shift->work_hour_end);
        }

        // ── Data cuti/izin/sakit ──
        $leave      = $roster->leave;
        $leaveType  = $leave?->leaveType;
        $isLeave    = $leave !== null;
        $leaveCode  = $leaveType?->code;

        // ── Tentukan pattern type ──
        $patternType = $roster->work_pattern_type;

        // ── Dispatch ke handler sesuai pattern ──
        $record = match ($patternType) {
            'FIXED'      => $this->processFixed($roster, $dateStr, $isSunday, $isHoliday, $checkIn, $checkOut, $schedulIn, $schedulOut, $overtime, $lateMin, $isLeave, $leaveCode, $leave, $leaveType),
            'FLEX-SHIFT' => $this->processFlexShift($roster, $dateStr, $isSunday, $isHoliday, $checkIn, $checkOut, $schedulIn, $schedulOut, $overtime, $lateMin, $isLeave, $leaveCode, $leave, $leaveType),
            'SHIFT'      => $this->processShift($roster, $dateStr, $checkIn, $checkOut, $schedulIn, $schedulOut, $overtime, $lateMin, $isLeave, $leaveCode, $leave, $leaveType),
            default      => null,
        };

        if ($record === null) {
            $this->skipped++;
            return;
        }

        // ── Upsert ──
        $exists = SupervisorAttendance::where('employee_id', $roster->employee_id)
            ->where('date', $dateStr)
            ->exists();

        if ($exists) {
            SupervisorAttendance::where('employee_id', $roster->employee_id)
                ->where('date', $dateStr)
                ->update($record);
            $this->updated++;
        } else {
            SupervisorAttendance::create($record);
            $this->inserted++;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: FIXED
    // ═══════════════════════════════════════════════════════════════

    protected function processFixed(
        EmployeeShiftRoster $roster,
        string $dateStr,
        bool $isSunday,
        bool $isHoliday,
        $checkIn,
        $checkOut,
        $schedulIn,
        $schedulOut,
        int $overtime,
        int $lateMin,
        bool $isLeave,
        ?string $leaveCode,
        $leave,
        $leaveType
    ): ?array {
        // a. Minggu / Holiday → off
        if ($isSunday || $isHoliday) {
            return $this->buildRecord(
                roster: $roster,
                dateStr: $dateStr,
                checkIn: null,
                checkOut: null,
                actualIn: null,
                actualOut: null,
                status: 'off',
                lateDuration: 0,
                lembur: 0,
                isLeave: false,
                leaveId: null,
                izinDuration: 0,
                sakitDuration: 0,
                deductDay: null,
            );
        }

        // b. Cuti / Izin / Sakit
        if ($isLeave && $leaveCode) {
            $izinDur  = ($leaveType?->category === 'permit') ? 1 : 0;
            $sakitDur = ($leaveType?->category === 'sick') ? 1 : 0;
            $deductDay = ($leaveType?->category === 'permit') ? 1 : (($leaveType?->category === 'leave') ? 0 : (($leaveType?->category === 'sick') ? 0 : null));

            return $this->buildRecord(
                roster: $roster,
                dateStr: $dateStr,
                checkIn: null,
                checkOut: null,
                actualIn: null,
                actualOut: null,
                status: $leaveCode,
                lateDuration: 0,
                lembur: 0,
                isLeave: true,
                leaveId: $leave?->id,
                izinDuration: $izinDur,
                sakitDuration: $sakitDur,
                deductDay: $deductDay,
            );
        }

        // c. Hari kerja (Senin-Sabtu) — lembur maks 3 jam
        $lemburMinutes = min($overtime, self::MAX_OVERTIME_MINUTES);

        return $this->buildRecord(
            roster: $roster,
            dateStr: $dateStr,
            checkIn: $checkIn,
            checkOut: $checkOut,
            actualIn: $schedulIn,
            actualOut: $schedulOut,
            status: 'present',
            lateDuration: $lateMin,
            lembur: $lemburMinutes,
            isLeave: false,
            leaveId: null,
            izinDuration: 0,
            sakitDuration: 0,
            deductDay: null,
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: FLEX-SHIFT
    // ═══════════════════════════════════════════════════════════════

    protected function processFlexShift(
        EmployeeShiftRoster $roster,
        string $dateStr,
        bool $isSunday,
        bool $isHoliday,
        $checkIn,
        $checkOut,
        $schedulIn,
        $schedulOut,
        int $overtime,
        int $lateMin,
        bool $isLeave,
        ?string $leaveCode,
        $leave,
        $leaveType
    ): ?array {
        // a. Minggu / Holiday → off
        if ($isSunday || $isHoliday) {
            return $this->buildRecord(
                roster: $roster,
                dateStr: $dateStr,
                checkIn: null,
                checkOut: null,
                actualIn: null,
                actualOut: null,
                status: 'off',
                lateDuration: 0,
                lembur: 0,
                isLeave: false,
                leaveId: null,
                izinDuration: 0,
                sakitDuration: 0,
                deductDay: null,
            );
        }

        // b. Cuti / Izin / Sakit
        if ($isLeave && $leaveCode) {
            $izinDur  = ($leaveType?->category === 'permit') ? 1 : 0;
            $sakitDur = ($leaveType?->category === 'sick') ? 1 : 0;
            $deductDay = ($leaveType?->category === 'permit') ? 1 : (($leaveType?->category === 'leave') ? 0 : (($leaveType?->category === 'sick') ? 0 : null));

            return $this->buildRecord(
                roster: $roster,
                dateStr: $dateStr,
                checkIn: null,
                checkOut: null,
                actualIn: null,
                actualOut: null,
                status: $leaveCode,
                lateDuration: 0,
                lembur: 0,
                isLeave: true,
                leaveId: $leave?->id,
                izinDuration: $izinDur,
                sakitDuration: $sakitDur,
                deductDay: $deductDay,
            );
        }

        // c. Hari kerja (Senin-Sabtu) — lembur maks 3 jam
        $lemburMinutes = min($overtime, self::MAX_OVERTIME_MINUTES);

        return $this->buildRecord(
            roster: $roster,
            dateStr: $dateStr,
            checkIn: $checkIn,
            checkOut: $checkOut,
            actualIn: $schedulIn,
            actualOut: $schedulOut,
            status: 'present',
            lateDuration: $lateMin,
            lembur: $lemburMinutes,
            isLeave: false,
            leaveId: null,
            izinDuration: 0,
            sakitDuration: 0,
            deductDay: null,
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: SHIFT (Satpam)
    // ═══════════════════════════════════════════════════════════════

    protected function processShift(
        EmployeeShiftRoster $roster,
        string $dateStr,
        $checkIn,
        $checkOut,
        $schedulIn,
        $schedulOut,
        int $overtime,
        int $lateMin,
        bool $isLeave,
        ?string $leaveCode,
        $leave,
        $leaveType
    ): ?array {
        // a. external_code = "L" → off
        if ($roster->external_code === 'L') {
            return $this->buildRecord(
                roster: $roster,
                dateStr: $dateStr,
                checkIn: null,
                checkOut: null,
                actualIn: null,
                actualOut: null,
                status: 'off',
                lateDuration: 0,
                lembur: 0,
                isLeave: false,
                leaveId: null,
                izinDuration: 0,
                sakitDuration: 0,
                deductDay: null,
            );
        }

        // b. Cuti / Izin / Sakit
        if ($isLeave && $leaveCode) {
            $izinDur  = ($leaveType?->category === 'permit') ? 1 : 0;
            $sakitDur = ($leaveType?->category === 'sick') ? 1 : 0;
            $deductDay = ($leaveType?->category === 'permit') ? 1 : (($leaveType?->category === 'leave') ? 0 : (($leaveType?->category === 'sick') ? 0 : null));

            return $this->buildRecord(
                roster: $roster,
                dateStr: $dateStr,
                checkIn: null,
                checkOut: null,
                actualIn: null,
                actualOut: null,
                status: $leaveCode,
                lateDuration: 0,
                lembur: 0,
                isLeave: true,
                leaveId: $leave?->id,
                izinDuration: $izinDur,
                sakitDuration: $sakitDur,
                deductDay: $deductDay,
            );
        }

        // c. external_code != "L" → hari kerja, lembur dari att_prepare (no cap)
        $lemburMinutes = $overtime;

        return $this->buildRecord(
            roster: $roster,
            dateStr: $dateStr,
            checkIn: $checkIn,
            checkOut: $checkOut,
            actualIn: $schedulIn,
            actualOut: $schedulOut,
            status: 'present',
            lateDuration: $lateMin,
            lembur: $lemburMinutes,
            isLeave: false,
            leaveId: null,
            izinDuration: 0,
            sakitDuration: 0,
            deductDay: null,
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  BUILD RECORD
    // ═══════════════════════════════════════════════════════════════

    protected function buildRecord(
        EmployeeShiftRoster $roster,
        string $dateStr,
        $checkIn,
        $checkOut,
        $actualIn,
        $actualOut,
        string $status,
        int $lateDuration,
        int $lembur,
        bool $isLeave,
        ?int $leaveId,
        int $izinDuration,
        int $sakitDuration,
        ?int $deductDay,
    ): array {
        $scanCount = 0;
        if ($checkIn) {
            $scanCount++;
        }
        if ($checkOut) {
            $scanCount++;
        }

        return [
            'company_id'               => 1,
            'branch_id'                => null,
            'employee_id'              => $roster->employee_id,
            'employee_shift_roster_id' => $roster->id,
            'date'                     => $dateStr,
            'check_in'                 => $checkIn,
            'check_out'                => $checkOut,
            'actual_in'                => $actualIn,
            'actual_out'               => $actualOut,
            'check_in_log_id'          => null,
            'check_out_log_id'         => null,
            'import_batch'             => $this->importBatch,
            'status'                   => $status,
            'late_duration'            => $lateDuration,
            'early_leave_duration'     => 0,
            'lembur'                   => $lembur,
            'deduct_attendance'        => 0,
            'is_half_day'              => $roster->is_half_day ?? 0,
            'is_sun'                   => $roster->is_sun ?? 0,
            'is_sat'                   => $roster->is_sat ?? 0,
            'is_holiday'               => $roster->is_holiday ?? 0,
            'is_leave'                 => $isLeave ? 1 : 0,
            'is_manual_edit'           => 0,
            'last_edited_at'           => null,
            'last_edited_by'           => null,
            'holiday_overtime'         => 0,
            'is_locked'                => 0,
            'locked_at'                => null,
            'locked_by'                => null,
            'notes'                    => 'Imported by AttendanceImportService',
            'metadata'                 => json_encode([
                'source'      => 'sch_employee_shift_rosters',
                'roster_id'   => $roster->id,
                'imported_at' => now()->toDateTimeString(),
                'period_id'   => $this->periodId,
                'pattern'     => $roster->work_pattern_type,
            ]),
            'scan_count'         => $scanCount,
            'leave_id'           => $leaveId,
            'deduct_day'         => $deductDay,
            'izin_duration'      => $izinDuration,
            'sakit_duration'     => $sakitDuration,
            'lembur_calc'        => $lembur > 0 ? round($lembur / 60, 2) : 0,
            'lm'                 => 0,
            'lm_calc'            => null,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Load att_prepares untuk semua roster, return map: key = "employee_id_date"
     * → array check_in, check_out, overtime, late_minutes.
     */
    protected function loadPreparesMap($rosters): array
    {
        if ($rosters->isEmpty()) {
            return [];
        }

        $employeeIds = $rosters->pluck('employee_id')->unique()->values()->toArray();

        $prepares = DB::table('att_prepares')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->get([
                'employee_id', 'date',
                'check_in', 'check_out',
                'overtime', 'late_minutes',
            ]);

        $map = [];
        foreach ($prepares as $p) {
            $key = $p->employee_id . '_' . Carbon::parse($p->date)->toDateString();
            $map[$key] = [
                'check_in'     => $p->check_in ? Carbon::parse($p->check_in) : null,
                'check_out'    => $p->check_out ? Carbon::parse($p->check_out) : null,
                'overtime'     => (int) $p->overtime,
                'late_minutes' => (int) $p->late_minutes,
            ];
        }

        return $map;
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
            ->mapWithKeys(fn ($lt) => [
                strtoupper($lt->code) => [
                    'category' => $lt->category ?? 'leave',
                    'is_paid'  => $lt->is_paid ?? true,
                ],
            ])
            ->toArray();
    }
}
