<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Leave\Models\LeaveType;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Supervisor\Attendance\Imports\AttendanceDataFixImport;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import attendance — overwrite total untuk periode terpilih.
 *
 * Jalur 1 — overwrite total:
 *   - Karyawan: punya sch_employee_shift_rosters di periode DAN terdaftar
 *     di supervisor_employee_groups (period_start/period_end = periode).
 *   - Setiap tanggal dalam periode selalu dibuat record
 *     (createOrUpdate per employee_id + date — record lama ditimpa).
 *   - Pola kerja:
 *     a. FIXED / FLEX-SHIFT (dan pola lain → default office):
 *        a.1 Senin-Jumat external 'P' → check_in = start + rand(-10..3),
 *            check_out = end + lembur + rand(-3..10), lembur cap 3 jam
 *        a.2 Senin-Jumat external 'S' → check_in = start - lembur + rand(-10..3),
 *            check_out = end + rand(-3..10), lembur cap 3 jam
 *        a.3 Sabtu → sama seperti hari kerja, tapi lm & lembur selalu 0
 *        a.4 Minggu & holiday → semua waktu kosong, status 'off'
 *        Cuti/izin/sakit (kode leave di att_prepares) → waktu kosong,
 *        status normalisasi + flag izin/sakit/deduct_day.
 *        att_prepares kosong di hari kerja → status 'absent'.
 *     c. SHIFT → ambil dari att_prepares (check_in, lm, lembur, status),
 *        check_out & actual_in/out dari jadwal shift. att_prepares kosong → default ''.
 *
 * Jalur 2 — Excel overwrite (periode 1-6) dijalankan setelah jalur 1.
 */
class AttendanceImportService
{
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

        Log::info('=== START ATTENDANCE IMPORT (OVERWRITE TOTAL) ===', [
            'batch'     => $this->importBatch,
            'period_id' => $periodId,
            'start'     => $startDate,
            'end'       => $endDate,
        ]);

        // ── ① Employee scope: punya roster di periode + terdaftar di supervisor_employee_groups ──
        $groupEmployeeIds = SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->pluck('employee_id')
            ->unique()
            ->values();

        $employeeIds = EmployeeShiftRoster::whereIn('employee_id', $groupEmployeeIds->toArray())
            ->whereBetween('date', [$startDate, $endDate])
            ->distinct()
            ->pluck('employee_id');

        Log::info('Employee scope', [
            'group_employees' => $groupEmployeeIds->count(),
            'with_roster'     => $employeeIds->count(),
        ]);

        if ($employeeIds->isEmpty()) {
            Log::warning('No employees found in supervisor_employee_groups with roster');
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        }

        // ── ② Load rosters (key: employee_id_date) ──
        $rosters = EmployeeShiftRoster::with('shift')
            ->whereIn('employee_id', $employeeIds->toArray())
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $rostersByKey = [];
        foreach ($rosters as $roster) {
            $key = $roster->employee_id . '_' . Carbon::parse($roster->date)->toDateString();
            $rostersByKey[$key] = $roster;
        }

        // ── ③ Load holidays ──
        $holidayDates = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $holidaySet = array_flip($holidayDates);
        Log::info('Holidays loaded', ['count' => count($holidayDates)]);

        // ── ④ Load att_prepares (key: employee_id_date) ──
        $prepares = DB::table('att_prepares')
            ->whereIn('employee_id', $employeeIds->toArray())
            ->whereBetween('date', [$startDate, $endDate])
            ->get(['employee_id', 'date', 'check_in', 'check_out', 'overtime', 'lm', 'late_minutes', 'status']);

        $preparesByKey = [];
        foreach ($prepares as $p) {
            $key = $p->employee_id . '_' . Carbon::parse($p->date)->toDateString();
            $preparesByKey[$key] = $p;
        }

        Log::info('Prepares loaded', ['count' => count($preparesByKey)]);

        // ── ⑤ Generate record: setiap karyawan x setiap tanggal ──
        $records = [];
        $endDateObj = Carbon::parse($endDate);

        foreach ($employeeIds as $employeeId) {
            $dateCursor = Carbon::parse($startDate);
            while ($dateCursor->lte($endDateObj)) {
                $dateStr = $dateCursor->toDateString();
                $key     = $employeeId . '_' . $dateStr;

                $records[] = $this->buildDayRecord(
                    $employeeId,
                    $dateCursor,
                    $rostersByKey[$key] ?? null,
                    $preparesByKey[$key] ?? null,
                    $holidaySet,
                );

                $dateCursor->addDay();
            }
        }

        // ── ⑥ Simpan: createOrUpdate per (employee_id + date) ──
        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                $exists = DB::table('attendance_autologs')
                    ->where('employee_id', $record['employee_id'])
                    ->where('date', $record['date'])
                    ->exists();

                if ($exists) {
                    // Pakai DB::table supaya baris soft-deleted ikut ter-temukan
                    // (unique employee_id+date tetap terisi), dan deleted_at dikosongkan
                    // supaya record yang sebelumnya di-hapus "dihidupkan" kembali.
                    DB::table('attendance_autologs')
                        ->where('employee_id', $record['employee_id'])
                        ->where('date', $record['date'])
                        ->update(array_merge($record, ['deleted_at' => null, 'updated_at' => now()]));
                    $this->updated++;
                } else {
                    DB::table('attendance_autologs')->insert($record);
                    $this->inserted++;
                }
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

        Log::info('Import (jalur 1) done', [
            'inserted'  => $this->inserted,
            'updated'   => $this->updated,
            'employees' => $employeeIds->count(),
        ]);

        // ── ⑦ Excel overwrite (periode 1-6) ──
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
            'skipped'  => 0,
            'excel'    => $excelResult,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  BUILD DAY RECORD — dispatcher per karyawan per tanggal
    // ═══════════════════════════════════════════════════════════════

    protected function buildDayRecord(
        int $employeeId,
        Carbon $date,
        ?EmployeeShiftRoster $roster,
        ?object $prepare,
        array $holidaySet
    ): array {
        $dateStr = $date->toDateString();

        // Tanpa roster → fallback (off utk Minggu/holiday, absent utk lainnya)
        if ($roster === null) {
            $isSunday  = $date->dayOfWeek === 0;
            $isHoliday = isset($holidaySet[$dateStr]);

            if ($isSunday || $isHoliday) {
                return $this->buildRecord($employeeId, null, $dateStr, null, null, null, null, 'off', 0, 0, 0, [
                    'is_sun'     => $isSunday ? 1 : 0,
                    'is_holiday' => $isHoliday ? 1 : 0,
                ]);
            }

            return $this->buildRecord($employeeId, null, $dateStr, null, null, null, null, 'absent', 0, 0, 0);
        }

        // SHIFT → ambil dari att_prepares
        if ($roster->work_pattern_type === 'SHIFT') {
            return $this->processShift($employeeId, $roster, $prepare, $dateStr, $holidaySet);
        }

        // FIXED / FLEX-SHIFT (dan pola lain → default office)
        return $this->processFixedFlex($employeeId, $roster, $prepare, $dateStr, $holidaySet);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: FIXED & FLEX-SHIFT (dan pola lain)
    // ═══════════════════════════════════════════════════════════════

    protected function processFixedFlex(
        int $employeeId,
        EmployeeShiftRoster $roster,
        ?object $prepare,
        string $dateStr,
        array $holidaySet
    ): array {
        $date       = Carbon::parse($dateStr);
        $isSunday   = $date->dayOfWeek === 0;
        $isSaturday = $date->dayOfWeek === 6;
        $isHoliday  = isset($holidaySet[$dateStr]);

        // a.4 Minggu & holiday → off (semua waktu kosong)
        if ($isSunday || $isHoliday) {
            return $this->buildRecord($employeeId, $roster, $dateStr, null, null, null, null, 'off', 0, 0, 0, [
                'is_sun'     => $isSunday ? 1 : 0,
                'is_holiday' => $isHoliday ? 1 : 0,
            ]);
        }

        $shift = $roster->shift;
        $start = $shift && $shift->work_hour_start
            ? Carbon::parse($dateStr . ' ' . $shift->work_hour_start)
            : null;
        $end = $shift && $shift->work_hour_end
            ? Carbon::parse($dateStr . ' ' . $shift->work_hour_end)
            : null;

        $externalCode = strtoupper(trim($shift?->external_code ?? ''));
        // Keluarga shift sore S (termasuk MK dll) → ikut pola shift sore TETAP.
        // Tidak boleh ada jam melewati 23:59, jadi jangan pakai jam roster
        // (utk 'MK' jam roster 22:50→06:50 menerobos tengah malam).
        $isSFlex = in_array($externalCode, ['S', 'MK', 'S1', 'S2', 'S3', 'MS', 'MSS'], true);
        if ($isSFlex) {
            if ($isSaturday) {
                $start = Carbon::parse($dateStr . ' 12:50:00');
                $end   = Carbon::parse($dateStr . ' 18:50:00');
            } else {
                $start = Carbon::parse($dateStr . ' 14:50:00');
                $end   = Carbon::parse($dateStr . ' 22:50:00');
            }
        }

        // Status dari att_prepares (normalisasi)
        $status = $this->normalizeStatus($prepare->status ?? '');

        // Cuti / izin / sakit → waktu kosong + flag
        if (in_array($status, ['leave', 'izin', 'sakit'], true)) {
            return $this->buildRecord($employeeId, $roster, $dateStr, null, null, null, null, $status, 0, 0, 0, [
                'is_leave'       => 1,
                'izin_duration'  => $status === 'izin' ? 1 : 0,
                'sakit_duration' => $status === 'sakit' ? 1 : 0,
                'deduct_day'     => $status === 'izin' ? 1 : 0,
            ]);
        }

        // Status off / holiday / absent dari att_prepares, atau att_prepares kosong → waktu kosong
        if ($status === '' || in_array($status, ['off', 'holiday', 'absent'], true)) {
            return $this->buildRecord(
                $employeeId,
                $roster,
                $dateStr,
                null,
                null,
                null,
                null,
                $status === '' ? 'absent' : $status,
                0,
                0,
                0,
                [
                    'is_sat'     => $isSaturday ? 1 : 0,
                    'is_holiday' => $isHoliday ? 1 : 0,
                ],
            );
        }

        // Status present / lainnya → hari kerja, generate jam dari jadwal
        $lembur = min((int) ($prepare->overtime ?? 0), self::MAX_OVERTIME_MINUTES);

        // a.3 Sabtu → lembur & lm selalu 0
        if ($isSaturday) {
            $lembur = 0;
        }

        if ($isSFlex) {
            // a.2 / a.3 'S' — lembur dikurangkan dari check_in (mulai lebih awal)
            $checkIn  = $start ? (clone $start)->subMinutes($lembur + random_int(-10, 3)) : null;
            $checkOut = $end   ? (clone $end)->addMinutes(random_int(-3, 10)) : null;
        } else {
            // a.1 / a.3 'P' (default utk external_code lain) — lembur di check_out
            $checkIn  = $start ? (clone $start)->addMinutes(random_int(-10, 3)) : null;
            $checkOut = $end   ? (clone $end)->addMinutes($lembur + random_int(-3, 10)) : null;
        }

        return $this->buildRecord(
            $employeeId,
            $roster,
            $dateStr,
            $checkIn,
            $checkOut,
            $start,
            $end,
            'present',
            (int) ($prepare->late_minutes ?? 0),
            $lembur,
            0,
            [
                'is_sat'     => $isSaturday ? 1 : 0,
                'is_holiday' => $isHoliday ? 1 : 0,
            ],
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN: SHIFT
    // ═══════════════════════════════════════════════════════════════

    protected function processShift(
        int $employeeId,
        EmployeeShiftRoster $roster,
        ?object $prepare,
        string $dateStr,
        array $holidaySet
    ): array {
        $date       = Carbon::parse($dateStr);
        $isSunday   = $date->dayOfWeek === 0;
        $isHoliday  = isset($holidaySet[$dateStr]);

        $shift = $roster->shift;
        $start = $shift && $shift->work_hour_start
            ? Carbon::parse($dateStr . ' ' . $shift->work_hour_start)
            : null;
        $end = $shift && $shift->work_hour_end
            ? Carbon::parse($dateStr . ' ' . $shift->work_hour_end)
            : null;

        $status = $this->normalizeStatus($prepare->status ?? '');

        // Off / cuti / izin / sakit / absent (atau att_prepares kosong) → waktu kosong
        if ($status === '' || in_array($status, ['off', 'holiday', 'leave', 'izin', 'sakit', 'absent'], true)) {
            return $this->buildRecord($employeeId, $roster, $dateStr, null, null, $start, $end, $status, 0, 0, 0, [
                'is_sun'         => $isSunday ? 1 : 0,
                'is_holiday'     => $isHoliday ? 1 : 0,
                'is_leave'       => in_array($status, ['leave', 'izin', 'sakit'], true) ? 1 : 0,
                'izin_duration'  => $status === 'izin' ? 1 : 0,
                'sakit_duration' => $status === 'sakit' ? 1 : 0,
                'deduct_day'     => $status === 'izin' ? 1 : 0,
            ]);
        }

        // Present — check_in & check_out dari att_prepares (apa adanya), actual dari jadwal shift
        $lembur = (int) ($prepare->overtime ?? 0);
        $lm     = (int) ($prepare->lm ?? 0);

        $checkIn  = $prepare->check_in ? Carbon::parse($prepare->check_in) : null;
        $checkOut = $prepare->check_out ? Carbon::parse($prepare->check_out) : null;

        return $this->buildRecord(
            $employeeId,
            $roster,
            $dateStr,
            $checkIn,
            $checkOut,
            $start,
            $end,
            'present',
            (int) ($prepare->late_minutes ?? 0),
            $lembur,
            $lm,
            [
                'is_sun'     => $isSunday ? 1 : 0,
                'is_holiday' => $isHoliday ? 1 : 0,
            ],
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  BUILD RECORD
    // ═══════════════════════════════════════════════════════════════

    protected function buildRecord(
        int $employeeId,
        ?EmployeeShiftRoster $roster,
        string $dateStr,
        $checkIn,
        $checkOut,
        $actualIn,
        $actualOut,
        string $status,
        int $lateDuration,
        int $lembur,
        int $lm,
        array $extra = []
    ): array {
        $scanCount = 0;
        if ($checkIn)  $scanCount++;
        if ($checkOut) $scanCount++;

        return [
            'company_id'               => 1,
            'branch_id'                => null,
            'employee_id'              => $employeeId,
            'employee_shift_roster_id' => $roster?->id,
            'date'                     => $dateStr,
            'check_in'                 => $checkIn?->format('Y-m-d H:i:s'),
            'check_out'                => $checkOut?->format('Y-m-d H:i:s'),
            'actual_in'                => $actualIn?->format('Y-m-d H:i:s'),
            'actual_out'               => $actualOut?->format('Y-m-d H:i:s'),
            'check_in_log_id'          => null,
            'check_out_log_id'         => null,
            'import_batch'             => $this->importBatch,
            'status'                   => $status,
            'late_duration'            => $lateDuration,
            'early_leave_duration'     => 0,
            'lembur'                   => $lembur,
            'lembur_calc'              => $lembur > 0 ? round($lembur / 60, 2) : 0,
            'lm'                       => $lm,
            'lm_calc'                  => $lm > 0 ? round($lm / 60, 2) : 0,
            'deduct_attendance'        => 0,
            'is_half_day'              => $roster?->is_half_day ?? 0,
            'is_sun'                   => $extra['is_sun'] ?? 0,
            'is_sat'                   => $extra['is_sat'] ?? 0,
            'is_holiday'               => $extra['is_holiday'] ?? 0,
            'is_leave'                 => $extra['is_leave'] ?? 0,
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
                'roster_id'   => $roster?->id,
                'imported_at' => now()->toDateTimeString(),
                'period_id'   => $this->periodId,
                'pattern'     => $roster?->work_pattern_type,
            ]),
            'scan_count'         => $scanCount,
            'leave_id'           => null,
            'deduct_day'         => $extra['deduct_day'] ?? null,
            'izin_duration'      => $extra['izin_duration'] ?? 0,
            'sakit_duration'     => $extra['sakit_duration'] ?? 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Normalisasi status dari att_prepares ke attendance_autologs.
     *   hadir → present, absent → absent, libur → holiday, off → off
     *   kode leave (ct/skt/itm/dll) → leave / izin / sakit sesuai kategori
     */
    protected function normalizeStatus(string $prepareStatus): string
    {
        $status = strtolower(trim($prepareStatus));

        $directMap = [
            'hadir'   => 'present',
            'absent'  => 'absent',
            'libur'   => 'holiday',
            'off'     => 'off',
            'holiday' => 'holiday',
            'present' => 'present',
        ];

        if (isset($directMap[$status])) {
            return $directMap[$status];
        }

        // Cek leave type cache
        $leaveInfo = static::$leaveTypeCache[strtoupper($status)] ?? null;
        if ($leaveInfo) {
            return match ($leaveInfo['category']) {
                'sick'   => 'sakit',
                'permit' => 'izin',
                default  => 'leave',
            };
        }

        // Status tidak dikenal → biarkan apa adanya
        return $status;
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
