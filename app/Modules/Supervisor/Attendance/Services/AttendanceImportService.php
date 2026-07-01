<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Supervisor\Attendance\Imports\AttendanceDataFixImport;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import attendance dari att_prepares ke attendance_autologs
 * dengan rules per hari + filter 6 group.
 *
 * Rule utama: Excel > att_prepares
 * - Ada Excel (periode 1-6) → overwrite dengan XLSX
 * - Tidak ada Excel → data final = att_prepares
 */
class AttendanceImportService
{
    /** @var array<string> Group yang diproses dari att_prepares */
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
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->periodId   = $periodId;
        $this->importBatch = 'IMP_'.date('YmdHis').'_'.uniqid();

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

        // ── ② Query att_prepares dengan join roster ──
        $prepares = DB::table('att_prepares as ap')
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
                'sr.shift_id',
            ])
            ->leftJoin('sch_employee_shift_rosters as sr', function ($join) {
                $join->on('ap.employee_id', '=', 'sr.employee_id')
                    ->on('ap.date', '=', 'sr.date');
            })
            ->whereIn('ap.employee_id', $groupEmployeeIds->toArray())
            ->whereBetween('ap.date', [$startDate, $endDate])
            ->orderBy('ap.date')
            ->orderBy('ap.employee_id')
            ->get();

        Log::info('Prepares query result', ['count' => $prepares->count()]);

        if ($prepares->isEmpty()) {
            Log::warning('No att_prepares records found');

            return [
                'inserted' => 0,
                'updated'  => 0,
                'skipped'  => 0,
            ];
        }

        // ── Pre-load employee → group mapping ──
        $employeeGroupMap = $this->loadEmployeeGroupMap($groupEmployeeIds);

        // ── Pre-load roster shift untuk Sabtu ──
        $shiftCache = $this->loadShiftCache($prepares);

        DB::beginTransaction();
        try {
            foreach ($prepares as $prepare) {
                $employeeGroups = $employeeGroupMap[$prepare->employee_id] ?? [];
                $this->processRow($prepare, $employeeGroups, $shiftCache);
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

        Log::info('Import from prepares done', [
            'inserted' => $this->inserted,
            'updated'  => $this->updated,
            'skipped'  => $this->skipped,
        ]);

        // ── ④ Excel overwrite (periode 1-6) ──
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
     * Proses satu record att_prepares → upsert ke attendance_autologs.
     */
    protected function processRow(
        object $prepare,
        array $employeeGroups,
        array $shiftCache
    ): void {
        $date = Carbon::parse($prepare->date);
        $dayOfWeek = (int) $date->dayOfWeek; // 0=Minggu, 1=Senin, ..., 6=Sabtu

        $isSunday   = $dayOfWeek === 0;
        $isSaturday = $dayOfWeek === 6;
        $isHoliday  = (bool) ($prepare->is_holiday ?? false);
        $isSS       = in_array('GRP-SS', $employeeGroups);

        // ── Tentukan lm, lembur, check_in, check_out ──
        if ($isSS) {
            // GRP-SS (Satpam): ambil SEMUA apa adanya dari att_prepares
            $lm      = (int) ($prepare->lm ?? 0);
            $lembur  = (int) ($prepare->overtime ?? 0);
            $checkIn = $prepare->check_in ? Carbon::parse($prepare->check_in) : null;
            $checkOut = $prepare->check_out ? Carbon::parse($prepare->check_out) : null;
        } elseif ($isSunday || $isHoliday) {
            // Minggu / Holiday: null semua
            $lm      = 0;
            $lembur  = 0;
            $checkIn = null;
            $checkOut = null;
        } elseif ($isSaturday) {
            // Sabtu: lm=0, lembur=0, check_in/out dari jadwal (abaikan att_prepares)
            $lm      = 0;
            $lembur  = 0;
            [$checkIn, $checkOut] = $this->getScheduleTimes($prepare, $shiftCache);
        } else {
            // Senin-Jumat: ambil dari att_prepares
            $lm      = (int) ($prepare->lm ?? 0);
            $lembur  = (int) ($prepare->overtime ?? 0);
            $checkIn = $prepare->check_in ? Carbon::parse($prepare->check_in) : null;
            $checkOut = $prepare->check_out ? Carbon::parse($prepare->check_out) : null;
        }

        $overtimeDuration = $lembur + $lm;
        $lateDuration     = (int) ($prepare->late_minutes ?? 0);

        // ── Normalisasi status ──
        $prepareStatus    = strtolower(trim($prepare->prepare_status ?? ''));
        $normalizedStatus = $this->normalizeStatus($prepareStatus);

        // ── Durasi izin/sakit ──
        $izinDuration  = ($normalizedStatus === 'izin') ? 1 : 0;
        $sakitDuration = ($normalizedStatus === 'sakit') ? 1 : 0;
        $isLeave       = ($normalizedStatus === 'leave') ? 1 : 0;

        // ── Deduct day ──
        $deductDay = null;
        if ($normalizedStatus === 'izin') {
            $deductDay = 1;
        } elseif ($normalizedStatus === 'leave') {
            $deductDay = 0;
        } elseif ($normalizedStatus === 'sakit') {
            $deductDay = 0;
        }

        // ── Scan count ──
        $scanCount = 0;
        if ($checkIn) {
            $scanCount++;
        }
        if ($checkOut) {
            $scanCount++;
        }

        // ── Build data array ──
        $autologData = [
            'company_id'               => 1,
            'branch_id'                => null,
            'employee_id'              => $prepare->employee_id,
            'employee_shift_roster_id' => $prepare->roster_id,
            'date'                     => $date->toDateString(),
            'check_in'                 => $checkIn,
            'check_out'                => $checkOut,
            'actual_in'                => $checkIn,
            'actual_out'               => $checkOut,
            'check_in_log_id'          => null,
            'check_out_log_id'         => null,
            'import_batch'             => $this->importBatch,
            'status'                   => $normalizedStatus,
            'late_duration'            => $lateDuration,
            'early_leave_duration'     => 0,
            'lembur'                   => $overtimeDuration,
            'deduct_attendance'        => 0,
            'is_half_day'              => $prepare->is_half_day ?? 0,
            'is_sun'                   => $prepare->is_sun ?? 0,
            'is_sat'                   => $prepare->is_sat ?? 0,
            'is_holiday'               => $prepare->is_holiday ?? 0,
            'is_leave'                 => $isLeave,
            'is_manual_edit'           => 0,
            'last_edited_at'           => null,
            'last_edited_by'           => null,
            'holiday_overtime'         => ($prepare->is_holiday && $overtimeDuration > 0) ? 1 : 0,
            'is_locked'                => 0,
            'locked_at'                => null,
            'locked_by'                => null,
            'notes'                    => $prepare->notes ?? 'Imported by AttendanceImportService',
            'metadata'                 => json_encode([
                'source'      => 'att_prepares',
                'prepare_id'  => $prepare->id,
                'imported_at' => now()->toDateTimeString(),
                'period_id'   => $this->periodId,
                'groups'      => $employeeGroups,
            ]),
            'scan_count'     => $scanCount,
            'leave_id'       => null,
            'deduct_day'     => $deductDay,
            'izin_duration'  => $izinDuration,
            'sakit_duration' => $sakitDuration,
            'lembur_calc'    => $overtimeDuration > 0
                ? round($overtimeDuration / 60, 2)
                : 0,
            'lm'             => $lm,
            'lm_calc'        => null, // dihitung oleh adjustment (Perhitungan Lembur)
            'created_at'     => now(),
            'updated_at'     => now(),
        ];

        // ── Upsert ──
        $exists = SupervisorAttendance::where('employee_id', $prepare->employee_id)
            ->where('date', $date->toDateString())
            ->exists();

        if ($exists) {
            SupervisorAttendance::where('employee_id', $prepare->employee_id)
                ->where('date', $date->toDateString())
                ->update($autologData);
            $this->updated++;
        } else {
            SupervisorAttendance::create($autologData);
            $this->inserted++;
        }
    }

    /**
     * Ambil check_in / check_out dari jadwal roster (untuk Sabtu).
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function getScheduleTimes(object $prepare, array $shiftCache): array
    {
        $rosterKey = $prepare->employee_id . '_' . $prepare->date;

        if (! isset($shiftCache[$rosterKey])) {
            return [null, null];
        }

        $shift = $shiftCache[$rosterKey];
        if (! $shift || ! $shift->work_hour_start) {
            return [null, null];
        }

        $dateStr = Carbon::parse($prepare->date)->toDateString();

        $checkIn = Carbon::parse($dateStr . ' ' . $shift->work_hour_start);

        $checkOut = null;
        if ($shift->work_hour_end) {
            $checkOut = Carbon::parse($dateStr . ' ' . $shift->work_hour_end);
        }

        return [$checkIn, $checkOut];
    }

    /**
     * Normalisasi status dari att_prepares ke attendance_autologs.
     */
    protected function normalizeStatus(string $prepareStatus): string
    {
        $directMap = [
            'hadir'   => 'present',
            'absent'  => 'absent',
            'libur'   => 'holiday',
            'off'     => 'off',
            'holiday' => 'holiday',
            'present' => 'present',
        ];

        if (isset($directMap[$prepareStatus])) {
            return $directMap[$prepareStatus];
        }

        $upperStatus = strtoupper($prepareStatus);
        $leaveInfo   = static::$leaveTypeCache[$upperStatus] ?? null;

        if ($leaveInfo) {
            return match ($leaveInfo['category']) {
                'sick'   => 'sakit',
                'permit' => 'izin',
                'leave'   => 'leave',
                default  => 'leave',
            };
        }

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
            ->mapWithKeys(fn ($lt) => [
                strtoupper($lt->code) => [
                    'category' => $lt->category ?? 'leave',
                    'is_paid'   => $lt->is_paid ?? true,
                ],
            ])
            ->toArray();
    }

    /**
     * Load mapping employee_id → array of group reference_codes.
     */
    protected function loadEmployeeGroupMap($employeeIds): array
    {
        $groups = EmployeeGroup::whereIn('employee_id', $employeeIds)
            ->whereIn('reference_code', self::GROUP_CODES)
            ->get();

        $map = [];
        foreach ($groups as $g) {
            $map[$g->employee_id][] = $g->reference_code;
        }

        return $map;
    }

    /**
     * Pre-load shift data untuk roster (dipakai Sabtu).
     *
     * @return array<string, object|null>  key = "employee_id_date"
     */
    protected function loadShiftCache($prepares): array
    {
        $shiftIds = [];
        $rosterMap = []; // key = employee_id_date → shift_id

        foreach ($prepares as $p) {
            if (! empty($p->shift_id)) {
                $shiftIds[] = $p->shift_id;
                $rosterMap[$p->employee_id . '_' . $p->date] = $p->shift_id;
            }
        }

        $shiftIds = array_unique($shiftIds);
        if (empty($shiftIds)) {
            return [];
        }

        $shifts = DB::table('sch_shifts')
            ->whereIn('id', $shiftIds)
            ->get()
            ->keyBy('id');

        $cache = [];
        foreach ($rosterMap as $key => $shiftId) {
            $cache[$key] = $shifts->get($shiftId);
        }

        return $cache;
    }
}
