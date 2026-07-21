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
 * Import attendance: copy check_in, check_out, overtime dari att_prepares
 * ke attendance_autologs dengan aturan per work_pattern_type dan group.
 *
 * Sumber data:
 *   1. Karyawan: sch_employee_shift_rosters (6 group)
 *   2. Holiday:   sch_holidays
 *   3. Cuti/Izin/Sakit: leave_requests + leave_types
 *   4. Scan:      att_prepares (check_in, check_out, overtime, late_minutes)
 *   5. Jadwal:    shift->work_hour_start / work_hour_end
 *
 * Aturan lembur per pattern + group:
 *   FIXED / FLEX-SHIFT:
 *     GRP-ALLIN, GRP-JKT, GRP-SPR, GRP-GD → lembur=0 semua hari
 *     GRP-SP1 → Sabtu lembur=0, Sen-Jum cap 3 jam (180 menit)
 *     Minggu/Holiday → off
 *     Cuti/Izin/Sakit → status leave
 *   SHIFT (GRP-SS):
 *     Ambil apa adanya dari att_prepare
 *     external_code="L" → off
 *     Cuti/Izin/Sakit → status leave
 *
 * lm selalu 0 untuk semua.
 *
 * Excel overwrite (periode 1-6) dijalankan setelah import roster.
 */
class AttendanceImportService
{
    /** @var array<string> Semua group yang diproses */
    protected const GROUP_CODES = [
        'GRP-JKT',
        'GRP-ALLIN',
        'GRP-SPR',
        'GRP-GD',
        'GRP-SS',
        'GRP-PS1',
    ];

    /** @var array<string> Group yang lembur-nya selalu 0 (FIXED/FLEX-SHIFT) */
    protected const ZERO_LEMBUR_GROUPS = ['GRP-ALLIN', 'GRP-JKT', 'GRP-SPR', 'GRP-GD'];

    /** @var array<string> Group yang lembur-nya di-cap 3 jam (FIXED/FLEX-SHIFT) */
    protected const CAP_LEMBUR_GROUPS = ['GRP-PS1'];

    /** @var array<int, string> Period yang punya Excel overwrite */
    protected const EXCEL_PERIODS = [1, 2, 3, 4, 5, 6];

    /** Maksimal lembur Senin-Jumat untuk FIXED & FLEX-SHIFT (menit) */
    protected const MAX_OVERTIME_MINUTES = 180;

    /** @var array<string, array{category: string, is_paid: bool}>|null */
    protected static ?array $leaveTypeCache = null;

    // ── State ──

    protected string $startDate;
    protected string $endDate;
    protected int $periodId;
    protected string $importBatch;

    protected int $inserted = 0;
    protected int $updated = 0;
    protected int $skipped = 0;

    /** @var array<int> Employee IDs dari ZERO_LEMBUR_GROUPS */
    protected array $zeroLemburIds = [];

    /** @var array<int> Employee IDs dari CAP_LEMBUR_GROUPS */
    protected array $capLemburIds = [];

    // ═══════════════════════════════════════════════════════════════
    //  MAIN
    // ═══════════════════════════════════════════════════════════════

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

        // ── ① Employee IDs per group category ──
        $allGroupIds = EmployeeGroup::whereIn('reference_code', self::GROUP_CODES)
            ->pluck('employee_id')->unique()->values();

        $this->zeroLemburIds = EmployeeGroup::whereIn('reference_code', self::ZERO_LEMBUR_GROUPS)
            ->pluck('employee_id')->unique()->values()->toArray();

        $this->capLemburIds = EmployeeGroup::whereIn('reference_code', self::CAP_LEMBUR_GROUPS)
            ->pluck('employee_id')->unique()->values()->toArray();

        Log::info('Employee IDs', [
            'total'       => $allGroupIds->count(),
            'zero_lembur' => count($this->zeroLemburIds),
            'cap_lembur'  => count($this->capLemburIds),
        ]);

        if ($allGroupIds->isEmpty()) {
            Log::warning('No employees found in target groups');
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        }

        // ── ② Query roster ──
        $rosters = EmployeeShiftRoster::with(['shift', 'leave.leaveType'])
            ->whereIn('employee_id', $allGroupIds->toArray())
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        Log::info('Roster query result', ['count' => $rosters->count()]);

        if ($rosters->isEmpty()) {
            Log::warning('No roster records found');
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        }

        // ── ③ Load holidays ──
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
                    userId: 1,
                    filePath: $filePath,
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

    // ═══════════════════════════════════════════════════════════════
    //  PROCESS ROSTER
    // ═══════════════════════════════════════════════════════════════

    protected function processRoster(EmployeeShiftRoster $roster, array $holidayDates, array $preparesMap): void
    {
        $date       = Carbon::parse($roster->date);
        $dateStr    = $date->toDateString();
        $dayOfWeek  = (int) $date->dayOfWeek;   // 0=Minggu .. 6=Sabtu
        $isSunday   = $dayOfWeek === 0;
        $isSaturday = $dayOfWeek === 6;
        $isHoliday  = in_array($dateStr, $holidayDates);

        // ── Data dari att_prepares ──
        $prepareKey = $roster->employee_id . '_' . $dateStr;
        $prepare    = $preparesMap[$prepareKey] ?? null;
        $checkIn    = $prepare['check_in'] ?? null;
        $checkOut   = $prepare['check_out'] ?? null;
        $overtime   = (int) ($prepare['overtime'] ?? 0);
        $lateMin    = (int) ($prepare['late_minutes'] ?? 0);

        // ── Data jadwal dari shift ──
        $shift      = $roster->shift;
        $schedulIn  = null;
        $schedulOut = null;

        if ($shift && $shift->work_hour_start) {
            $schedulIn = Carbon::parse($dateStr . ' ' . $shift->work_hour_start);
        }
        if ($shift && $shift->work_hour_end) {
            $schedulOut = Carbon::parse($dateStr . ' ' . $shift->work_hour_end);
        }

        // ── Data cuti/izin/sakit ──
        $leave     = $roster->leave;
        $leaveType = $leave?->leaveType;
        $isLeave   = $leave !== null;
        $leaveCode = $leaveType?->code;

        // ── Tentukan kategori lembur berdasarkan group ──
        $empId     = $roster->employee_id;
        $lemburRule = 'zero'; // default: lembur = 0
        if (in_array($empId, $this->capLemburIds)) {
            $lemburRule = 'cap';
        }

        // ── Dispatch ke handler ──
        $patternType = $roster->work_pattern_type;

        $record = match ($patternType) {
            'FIXED', 'FLEX-SHIFT' => $this->processFixedFlex(
                $roster, $dateStr,
                $isSunday, $isSaturday, $isHoliday, $lemburRule,
                $checkIn, $checkOut, $schedulIn, $schedulOut,
                $overtime, $lateMin,
                $isLeave, $leaveCode, $leave, $leaveType,
            ),
            'SHIFT' => $this->processShift(
                $roster, $dateStr,
                $checkIn, $checkOut, $schedulIn, $schedulOut,
                $overtime, $lateMin,
                $isLeave, $leaveCode, $leave, $leaveType,
            ),
            default => null,
        };

        if ($record === null) {
            $this->skipped++;
            return;
        }

        // ── Upsert ──
        $exists = SupervisorAttendance::where('employee_id', $empId)
            ->where('date', $dateStr)
            ->exists();

        if ($exists) {
            SupervisorAttendance::where('employee_id', $empId)
                ->where('date', $dateStr)
                ->update($record);
            $this->updated++;
        } else {
            SupervisorAttendance::create($record);
            $this->inserted++;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: FIXED & FLEX-SHIFT (aturan identik)
    // ═══════════════════════════════════════════════════════════════

    protected function processFixedFlex(
        EmployeeShiftRoster $roster,
        string $dateStr,
        bool $isSunday,
        bool $isSaturday,
        bool $isHoliday,
        string $lemburRule,     // 'zero' | 'cap'
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
            return $this->buildRecord($roster, $dateStr, null, null, null, null, 'off', 0, 0, false, null, 0, 0, null);
        }

        // b. Cuti / Izin / Sakit
        if ($isLeave && $leaveCode) {
            $izinDur   = ($leaveType?->category === 'permit') ? 1 : 0;
            $sakitDur  = ($leaveType?->category === 'sick') ? 1 : 0;
            $deductDay = match ($leaveType?->category) {
                'permit' => 1,
                'leave'  => 0,
                'sick'   => 0,
                default  => null,
            };

            return $this->buildRecord($roster, $dateStr, null, null, null, null, $leaveCode, 0, 0, true, $leave?->id, $izinDur, $sakitDur, $deductDay);
        }

        // c. Sabtu → present, lembur = 0, check_in/out random deket jadwal
        if ($isSaturday) {
            $satCheckIn  = $schedulIn  ? (clone $schedulIn)->addMinutes(rand(-15, 15)) : null;
            $satCheckOut = $schedulOut ? (clone $schedulOut)->addMinutes(rand(-15, 15)) : null;
            return $this->buildRecord($roster, $dateStr, $satCheckIn, $satCheckOut, $schedulIn, $schedulOut, 'present', $lateMin, 0);
        }

        // c.1 Shift siang (external_code "S") → check_in mundur, check_out 22:50
        if ($roster->shift && $roster->shift->external_code === 'S') {
            $siangCheckIn = $schedulIn
                ? (clone $schedulIn)->subMinutes(rand(0, 15) + $overtime)
                : null;
            $siangCheckOut = Carbon::parse($dateStr . ' 22:50')->addMinutes(rand(0, 5));

            $lemburMinutes = match ($lemburRule) {
                'zero' => 0,
                'cap'  => min($overtime, self::MAX_OVERTIME_MINUTES),
                default => 0,
            };

            return $this->buildRecord($roster, $dateStr, $siangCheckIn, $siangCheckOut, $schedulIn, $schedulOut, 'present', $lateMin, $lemburMinutes);
        }

        // d. Senin-Jumat → lembur sesuai group
        $lemburMinutes = match ($lemburRule) {
            'zero' => 0,
            'cap'  => min($overtime, self::MAX_OVERTIME_MINUTES),
            default => 0,
        };

        return $this->buildRecord($roster, $dateStr, $checkIn, $checkOut, $schedulIn, $schedulOut, 'present', $lateMin, $lemburMinutes);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: SHIFT (Satpam — GRP-SS)
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
            return $this->buildRecord($roster, $dateStr, null, null, null, null, 'off', 0, 0);
        }

        // b. Cuti / Izin / Sakit
        if ($isLeave && $leaveCode) {
            $izinDur   = ($leaveType?->category === 'permit') ? 1 : 0;
            $sakitDur  = ($leaveType?->category === 'sick') ? 1 : 0;
            $deductDay = match ($leaveType?->category) {
                'permit' => 1,
                'leave'  => 0,
                'sick'   => 0,
                default  => null,
            };

            return $this->buildRecord($roster, $dateStr, null, null, null, null, $leaveCode, 0, 0, true, $leave?->id, $izinDur, $sakitDur, $deductDay);
        }

        // c. Hari kerja → ambil apa adanya dari att_prepare
        return $this->buildRecord($roster, $dateStr, $checkIn, $checkOut, $schedulIn, $schedulOut, 'present', $lateMin, $overtime);
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
        bool $isLeave = false,
        ?int $leaveId = null,
        int $izinDuration = 0,
        int $sakitDuration = 0,
        ?int $deductDay = null,
    ): array {
        $scanCount = 0;
        if ($checkIn)  $scanCount++;
        if ($checkOut) $scanCount++;

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
            'lm'                 => 0,   // selalu 0
            'lm_calc'            => 0,   // selalu 0
            'created_at'         => now(),
            'updated_at'         => now(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Load att_prepares → map: key = "employee_id_date"
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
            ->get(['employee_id', 'date', 'check_in', 'check_out', 'overtime', 'late_minutes']);

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
