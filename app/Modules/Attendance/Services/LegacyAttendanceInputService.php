<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Imports\LegacyAttendanceMatrixImport;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveRequestService;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Pengisian data kehadiran lama (hasil kerja manual di Excel) ke:
 *   att_prepares, leave_requests, att_consecutive_days.
 *
 * Pemetaan kode dan aturan per kolom berasal dari konvensi yang sudah dipakai
 * AttendanceDataFixImport + keputusan pemilik aplikasi (lihat docs/changelog.md).
 */
class LegacyAttendanceInputService
{
    /** Kelompok karyawan yang tetap dibuatkan record walau tidak ada di file. */
    public const SKELETON_GROUP = 'GRP-JKT';

    /** Jam kerja tetap untuk hari libur yang tetap dikerjakan (kode L / Minggu berlembur). */
    public const HOLIDAY_START = '08:00:00';

    public const HOLIDAY_SCHEDULE_END = '16:00:00';

    /** LM otomatis untuk kode L (menit). */
    public const CODE_L_LM_MINUTES = 480;

    /** Lembur hari kerja di atas ini ditandai "perhatian" (tidak dipotong). */
    public const WEEKDAY_OVERTIME_REVIEW_HOURS = 3.0;

    /** Rentang detik acak untuk check_in/check_out. */
    public const SECONDS_MIN = 1;

    public const SECONDS_MAX = 29;

    /**
     * Pemetaan kode Excel => status att_prepares + opsi tambahan.
     * 'leave' = kode LeaveType yang dipakai untuk leave_requests.
     */
    public const CODE_MAP = [
        'H'     => ['status' => AttendancePrepare::STATUS_HADIR],
        'CSF'   => ['status' => AttendancePrepare::STATUS_HADIR, 'consecutive' => true],
        'L'     => ['status' => AttendancePrepare::STATUS_HADIR, 'lm_minutes' => self::CODE_L_LM_MINUTES],
        'OFF'   => ['status' => AttendancePrepare::STATUS_OFF],
        'ALFA'  => ['status' => AttendancePrepare::STATUS_ABSENT],
        'OUT'   => ['status' => AttendancePrepare::STATUS_ABSENT],
        '-'     => ['status' => AttendancePrepare::STATUS_ABSENT],
        'CUTI'  => ['status' => 'ct',  'leave' => 'CT'],
        'SAKIT' => ['status' => 'skt', 'leave' => 'SKT'],
        'IZIN'  => ['status' => 'itm', 'leave' => 'ITM'],
        'CTM'   => ['status' => 'ctm', 'leave' => 'CTM'],
        'CTH'   => ['status' => 'cth', 'leave' => 'CTH'],
        'CTN'   => ['status' => 'cm',  'leave' => 'CM'],
        'CHJ'   => ['status' => 'ch',  'leave' => 'CH'],
        'CMD'   => ['status' => 'ckm', 'leave' => 'CKM'],
    ];

    public function __construct(private LeaveRequestService $leaveRequestService)
    {
    }

    // ════════════════════════════════════════════════════════════════
    //  BUILD — baca file, susun rencana tulis, tanpa menyentuh DB
    // ════════════════════════════════════════════════════════════════

    /**
     * @return array{plan: array, summary: array, issues: list<string>}
     */
    public function build(string $filePath, PayPeriod $period): array
    {
        $import = new LegacyAttendanceMatrixImport;
        Excel::import($import, $filePath);

        $dates = $import->dates();
        $fileRows = $import->rows();
        $issues = $import->errors();

        if ($dates === []) {
            throw new RuntimeException('Tidak ada kolom tanggal yang terbaca. Pastikan file memakai format absen_core (tanggal di baris 1).');
        }

        $periodStart = Carbon::parse($period->start_date)->toDateString();
        $periodEnd = Carbon::parse($period->end_date)->toDateString();
        $fileStart = reset($dates);
        $fileEnd = end($dates);

        if ($fileStart !== $periodStart || $fileEnd !== $periodEnd) {
            throw new RuntimeException(
                "Rentang tanggal file ({$fileStart} s/d {$fileEnd}) tidak sama dengan periode terpilih "
                ."({$periodStart} s/d {$periodEnd}). Pilih periode yang sesuai dengan isi file."
            );
        }

        // ── Roster wajib tersedia untuk periode ini ──
        $rosterCount = EmployeeShiftRoster::query()
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->count();

        if ($rosterCount === 0) {
            throw new RuntimeException(
                "Jadwal (roster) periode {$periodStart} s/d {$periodEnd} belum ada. "
                .'Upload matrix jadwal lewat menu Jadwal terlebih dahulu, baru ulangi proses ini.'
            );
        }

        // ── Resolusi karyawan lewat NIP (kolom NO di file) ──
        $nips = array_values(array_unique(array_filter(
            array_column($fileRows, 'nip'),
            fn ($nip) => $nip !== '' && ! str_starts_with($nip, '#')
        )));

        $employees = Employee::query()
            ->whereIn('nip', $nips)
            ->get(['id', 'nip', 'name'])
            ->keyBy(fn (Employee $e) => (string) $e->nip);

        $leaveTypes = LeaveType::query()
            ->whereIn('code', array_values(array_filter(array_column(self::CODE_MAP, 'leave'))))
            ->get(['id', 'code', 'name'])
            ->keyBy('code');

        $roster = $this->loadRoster($periodStart, $periodEnd);

        $attPrepare = [];
        $leaveDatesByEmployee = [];
        $consecutive = [];
        $byStatus = [];
        $unmatched = [];
        $unknownCodes = [];
        $seenNip = [];
        $duplicateNips = [];
        $weekdayOvertimeOverLimit = 0;
        $matchedEmployeeIds = [];

        foreach ($fileRows as $fileRow) {
            $nip = $fileRow['nip'];

            if ($nip === '' || str_starts_with($nip, '#')) {
                $unmatched[] = ['row' => $fileRow['row'], 'nip' => $nip, 'nama' => $fileRow['nama']];

                continue;
            }

            if (isset($seenNip[$nip])) {
                $duplicateNips[] = ['nip' => $nip, 'nama' => $fileRow['nama'], 'rows' => [$seenNip[$nip], $fileRow['row']]];
            }
            $seenNip[$nip] = $fileRow['row'];

            $employee = $employees->get($nip);
            if (! $employee) {
                $unmatched[] = ['row' => $fileRow['row'], 'nip' => $nip, 'nama' => $fileRow['nama']];

                continue;
            }

            $matchedEmployeeIds[$employee->id] = true;

            foreach ($dates as $column => $date) {
                $cell = $fileRow['cells'][$column] ?? ['code' => '', 'hours' => null];
                $code = strtoupper($cell['code']);
                $hours = $cell['hours'];

                if ($code !== '' && ! isset(self::CODE_MAP[$code])) {
                    $unknownCodes[$code] = ($unknownCodes[$code] ?? 0) + 1;
                }

                $built = $this->buildDay($employee->id, $date, $code, $hours, $roster);

                $attPrepare[] = $built['row'];
                $byStatus[$built['row']['status']] = ($byStatus[$built['row']['status']] ?? 0) + 1;

                if ($built['over_weekday_limit']) {
                    $weekdayOvertimeOverLimit++;
                }

                if ($built['leave_code'] !== null) {
                    $leaveDatesByEmployee[$employee->id][$built['leave_code']][] = $date;
                }

                if ($built['consecutive']) {
                    $consecutive[] = ['employee_id' => $employee->id, 'date' => $date];
                }
            }
        }

        // ── Skeleton untuk kelompok GRP-JKT yang tidak ada di file ──
        $skeletons = $this->buildSkeletons($period, $dates, $matchedEmployeeIds, $roster);
        foreach ($skeletons['rows'] as $row) {
            $attPrepare[] = $row;
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;
        }

        $occupied = $this->loadOccupiedLeaveDates(array_keys($matchedEmployeeIds), $periodStart, $periodEnd);

        [$leaveRanges, $skippedLeaveDates] = $this->buildLeaveRanges(
            $leaveDatesByEmployee,
            $leaveTypes,
            $employees->pluck('name', 'id'),
            $occupied,
        );

        [$rosterRowsMissing, $employeesWithoutRoster] = $this->inspectRosterGaps($dates, $roster, array_keys($matchedEmployeeIds));

        $summary = [
            'period' => [
                'id'    => $period->id,
                'name'  => $period->name,
                'start' => $periodStart,
                'end'   => $periodEnd,
            ],
            'file' => [
                'rows'          => count($fileRows),
                'unique_nip'    => count($seenNip),
                'days'          => count($dates),
                'first_date'    => $fileStart,
                'last_date'     => $fileEnd,
            ],
            'employees' => [
                'matched'          => count($matchedEmployeeIds),
                'grp_jkt_skeleton' => $skeletons['count'],
                'unmatched'        => $unmatched,
                'duplicate_nips'   => $duplicateNips,
            ],
            'att_prepare' => [
                'total'  => count($attPrepare),
                'locked' => $this->countLocked($periodStart, $periodEnd, array_keys($matchedEmployeeIds)),
                'by_status' => $byStatus,
            ],
            'roster' => [
                'period_rows'            => $rosterCount,
                'missing_days'           => $rosterRowsMissing,
                'employees_without_roster' => $employeesWithoutRoster,
            ],
            'leave_requests' => [
                'total'   => count($leaveRanges),
                'ranges'  => $leaveRanges,
                'skipped' => $skippedLeaveDates,
            ],
            'consecutive' => [
                'total' => count($consecutive),
            ],
            'flags' => [
                'unknown_codes'                 => $unknownCodes,
                'weekday_overtime_over_limit'   => $weekdayOvertimeOverLimit,
                'weekday_overtime_limit_hours'  => self::WEEKDAY_OVERTIME_REVIEW_HOURS,
            ],
        ];

        if ($unmatched !== []) {
            $issues[] = count($unmatched).' baris dilewati karena NIP tidak ditemukan atau rusak (#N/A).';
        }
        if ($duplicateNips !== []) {
            $issues[] = count($duplicateNips).' NIP muncul lebih dari sekali di file; baris terakhir yang dipakai.';
        }
        if ($unknownCodes !== []) {
            $issues[] = 'Kode tidak dikenal (diisi absent): '.implode(', ', array_keys($unknownCodes));
        }
        if ($skippedLeaveDates !== []) {
            $issues[] = count($skippedLeaveDates).' hari cuti/izin/sakit dilewati karena tanggalnya sudah punya '
                .'leave_request berstatus approved.';
        }
        if ($rosterRowsMissing > 0) {
            $issues[] = $rosterRowsMissing.' hari tidak punya baris roster; kolom jadwal/jam dibiarkan kosong.';
        }
        if ($weekdayOvertimeOverLimit > 0) {
            $issues[] = $weekdayOvertimeOverLimit.' hari kerja dengan lembur di atas '
                .self::WEEKDAY_OVERTIME_REVIEW_HOURS.' jam (diambil apa adanya, ditandai "perhatian").';
        }
        if ($skeletons['other_active'] > 0) {
            $issues[] = $skeletons['other_active'].' karyawan aktif di periode ini tidak ada di file dan bukan '
                .self::SKELETON_GROUP.', sehingga tidak dibuatkan baris.';
        }

        return [
            'plan' => [
                'att_prepare'  => $attPrepare,
                'leave_ranges' => $leaveRanges,
                'consecutive'  => $consecutive,
            ],
            'summary' => $summary,
            'issues'  => $issues,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    //  COMMIT — tulis ke tiga tabel dalam satu transaksi
    // ════════════════════════════════════════════════════════════════

    /**
     * @return array{att_prepare: array, leave_requests: int, consecutive: int}
     */
    public function commit(array $plan, PayPeriod $period, ?int $userId = null): array
    {
        $periodStart = Carbon::parse($period->start_date)->toDateString();
        $periodEnd = Carbon::parse($period->end_date)->toDateString();

        return DB::transaction(function () use ($plan, $periodStart, $periodEnd, $userId) {
            $attResult = $this->writeAttPrepares($plan['att_prepare'], $periodStart, $periodEnd);
            $leaveCount = $this->writeLeaveRequests($plan['leave_ranges'], $userId);
            $consecutiveCount = $this->writeConsecutive($plan['consecutive'], $userId);

            return [
                'att_prepare'    => $attResult,
                'leave_requests' => $leaveCount,
                'consecutive'    => $consecutiveCount,
            ];
        });
    }

    /**
     * @param  list<array<string,mixed>>  $rows
     * @return array{inserted:int,updated:int,skipped_locked:int}
     */
    protected function writeAttPrepares(array $rows, string $periodStart, string $periodEnd): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'updated' => 0, 'skipped_locked' => 0];
        }

        $employeeIds = array_values(array_unique(array_column($rows, 'employee_id')));

        // Termasuk baris soft-deleted: unique index (employee_id, date) tidak peduli deleted_at,
        // jadi baris terhapus harus diperbarui, bukan di-insert ulang.
        $existing = DB::table('att_prepares')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereIn('employee_id', $employeeIds)
            ->get(['id', 'employee_id', 'date', 'is_locked'])
            ->keyBy(fn ($row) => $row->employee_id.'|'.$row->date);

        $now = now();
        $inserted = 0;
        $updated = 0;
        $skippedLocked = 0;

        foreach ($rows as $row) {
            $key = $row['employee_id'].'|'.$row['date'];

            $payload = $row;
            $payload['periode_start'] = $periodStart;
            $payload['periode_end'] = $periodEnd;
            $payload['updated_at'] = $now;

            $current = $existing->get($key);

            if ($current) {
                if ($current->is_locked) {
                    $skippedLocked++;

                    continue;
                }

                DB::table('att_prepares')->where('id', $current->id)->update($payload + ['deleted_at' => null]);
                $updated++;

                continue;
            }

            DB::table('att_prepares')->insert($payload + ['created_at' => $now, 'deleted_at' => null]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'skipped_locked' => $skippedLocked];
    }

    /**
     * @param  list<array<string,mixed>>  $ranges
     */
    protected function writeLeaveRequests(array $ranges, ?int $userId): int
    {
        $created = 0;

        foreach ($ranges as $range) {
            $exists = LeaveRequest::query()
                ->where('employee_id', $range['employee_id'])
                ->where('leave_type_id', $range['leave_type_id'])
                ->where('start_date', $range['start_date'])
                ->where('end_date', $range['end_date'])
                ->where('status', 'approved')
                ->exists();

            if ($exists) {
                continue;
            }

            $this->leaveRequestService->createApprovedForDate([
                'employee_id'    => $range['employee_id'],
                'leave_type_id'  => $range['leave_type_id'],
                'start_date'     => $range['start_date'],
                'end_date'       => $range['end_date'],
                'days_requested' => $range['days_requested'],
                'reason'         => $range['reason'],
                'note'           => ['tanggal_masuk' => $range['tanggal_masuk']],
            ], $range['start_date'], $userId);

            $created++;
        }

        return $created;
    }

    /**
     * @param  list<array{employee_id:int,date:string}>  $rows
     */
    protected function writeConsecutive(array $rows, ?int $userId): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $exists = DB::table('att_consecutive_days')
                ->where('employee_id', $row['employee_id'])
                ->where('start_date', $row['date'])
                ->where('type', 'worked')
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $payload[] = [
                'uuid'        => (string) Str::uuid(),
                'employee_id' => $row['employee_id'],
                'start_date'  => $row['date'],
                'end_date'    => $row['date'],
                'total_days'  => 1,
                'type'        => 'worked',
                'status'      => 'calculated',
                'notes'       => 'CONSECUTIVE',
                'created_by'  => $userId,
                'updated_by'  => $userId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table('att_consecutive_days')->insert($chunk);
        }

        return count($payload);
    }

    // ════════════════════════════════════════════════════════════════
    //  PENYUSUN BARIS
    // ════════════════════════════════════════════════════════════════

    /**
     * Susun satu baris att_prepares dari satu sel file.
     *
     * @return array{row: array<string,mixed>, leave_code: ?string, consecutive: bool, over_weekday_limit: bool}
     */
    protected function buildDay(int $employeeId, string $date, string $code, ?float $hours, array $roster): array
    {
        $carbon = Carbon::parse($date);
        $slot = $roster[$employeeId.'|'.$date] ?? null;
        $isHolidayLike = $carbon->isSunday() || (bool) ($slot['is_holiday'] ?? false);

        $scheduleIn = $slot['work_hour_start'] ?? null;
        $scheduleOut = $slot['work_hour_end'] ?? null;

        $mapping = self::CODE_MAP[$code] ?? null;
        $leaveCode = $mapping['leave'] ?? null;
        $consecutive = (bool) ($mapping['consecutive'] ?? false);
        $known = $mapping !== null;

        if ($known) {
            $status = $mapping['status'];
        } elseif ($code === '' && $hours !== null && $carbon->isSunday()) {
            // Minggu tanpa kode tapi ada jam lembur = kerja di hari libur.
            $status = AttendancePrepare::STATUS_HADIR;
            $isHolidayLike = true;
        } else {
            // Sel kosong, kode tak dikenal, ALFA, OUT.
            $status = AttendancePrepare::STATUS_ABSENT;
        }

        $lm = 0;
        $overtime = 0;
        $checkIn = null;
        $checkOut = null;
        $review = AttendancePrepare::REVIEW_LENGKAP;
        $overWeekdayLimit = false;

        if ($known && $code === 'CSF') {
            // Consecutive day: status hadir, tanpa jam, tetap dicatat di att_consecutive_days.
            $scheduleIn = null;
            $scheduleOut = null;
            $review = AttendancePrepare::REVIEW_CSF;
        } elseif ($known && $code === 'L') {
            // Lembur hari libur: LM otomatis, jam tetap 08:00-16:00.
            $lm = self::CODE_L_LM_MINUTES;
            $scheduleIn = self::HOLIDAY_START;
            $scheduleOut = self::HOLIDAY_SCHEDULE_END;
            $checkIn = $date.' '.self::HOLIDAY_START;
            $checkOut = $date.' '.self::HOLIDAY_SCHEDULE_END;
        } elseif ($code === '' && $hours !== null && $carbon->isSunday()) {
            // Minggu berlembur: masuk 08:00, pulang 08:00 + jam lembur.
            $lm = (int) round($hours * 60);
            $scheduleIn = self::HOLIDAY_START;
            $scheduleOut = self::HOLIDAY_SCHEDULE_END;
            $checkIn = $date.' '.self::HOLIDAY_START;
            $checkOut = Carbon::parse($date.' '.self::HOLIDAY_START)->addMinutes($lm)->format('Y-m-d H:i:s');
        } elseif ($status === AttendancePrepare::STATUS_HADIR) {
            $checkIn = $scheduleIn ? $date.' '.$scheduleIn : null;

            if ($hours !== null && $hours > 0) {
                $minutes = (int) round($hours * 60);

                if ($isHolidayLike) {
                    $lm = $minutes;
                } else {
                    $overtime = $minutes;
                    // Sabtu bukan "hari kerja" dalam aturan lembur maksimal 3 jam —
                    // shift Sabtu memang pendek dan lembur 4 jam lazim di sana.
                    if ($carbon->isWeekday() && $hours > self::WEEKDAY_OVERTIME_REVIEW_HOURS) {
                        $overWeekdayLimit = true;
                        $review = AttendancePrepare::REVIEW_PERHATIAN;
                    }
                }
            }

            // check_out = jam pulang jadwal + lembur hari kerja.
            $checkOut = $scheduleOut
                ? Carbon::parse($date.' '.$scheduleOut)->addMinutes($overtime)->format('Y-m-d H:i:s')
                : null;
        }

        if (! $known && $code !== '') {
            $review = AttendancePrepare::REVIEW_PERHATIAN;
        }

        return [
            'row' => [
                'employee_id'     => $employeeId,
                'date'            => $date,
                'check_in'        => $this->withRandomSeconds($checkIn),
                'check_out'       => $this->withRandomSeconds($checkOut),
                'schedule_in'     => $scheduleIn,
                'schedule_out'    => $scheduleOut,
                'lm'              => $lm,
                'lm_count'        => 0,
                'overtime'        => $overtime,
                'overtime_count'  => 0,
                'late_minutes'    => 0,
                'status'          => $status,
                'review_status'   => $review,
                'is_locked'       => 0,
                'notes'           => null,
            ],
            'leave_code'        => $leaveCode,
            'consecutive'       => $consecutive,
            'over_weekday_limit' => $overWeekdayLimit,
        ];
    }

    /**
     * Ganti komponen detik dengan nilai acak 01-29.
     *
     * Jam dan menit tetap sesuai jadwal + lembur; hanya detiknya yang diacak
     * supaya tidak selalu bulat :00 seperti jam jadwal, melainkan seperti hasil
     * scan mesin absen.
     */
    protected function withRandomSeconds(?string $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        $parsed = Carbon::parse($dateTime);

        return $parsed
            ->setTime($parsed->hour, $parsed->minute, random_int(self::SECONDS_MIN, self::SECONDS_MAX))
            ->format('Y-m-d H:i:s');
    }

    /**
     * Baris penuh periode untuk kelompok yang tidak ada di file (GRP-JKT),
     * seluruh kolom jam & lembur dikosongkan.
     *
     * @param  list<string>  $dates
     * @param  array<int,bool>  $matchedEmployeeIds
     * @return array{rows: list<array<string,mixed>>, count: int, other_active: int}
     */
    protected function buildSkeletons(PayPeriod $period, array $dates, array $matchedEmployeeIds, array $roster): array
    {
        $periodStart = Carbon::parse($period->start_date)->toDateString();
        $periodEnd = Carbon::parse($period->end_date)->toDateString();

        $hasGroup = DB::table('employee_group_masters')
            ->where('code', self::SKELETON_GROUP)
            ->exists();

        if (! $hasGroup) {
            return ['rows' => [], 'count' => 0, 'other_active' => 0];
        }

        $skeletonIds = DB::table('employee_groups')
            ->where('reference_code', self::SKELETON_GROUP)
            ->pluck('employee_id')
            ->all();

        $activeIds = Employee::query()
            ->whereIn('id', $skeletonIds)
            ->where('join_date', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('resign_date')->orWhere('resign_date', '>=', $periodStart))
            ->pluck('id')
            ->all();

        $rows = [];
        $skeletonCount = 0;

        foreach ($activeIds as $employeeId) {
            if (isset($matchedEmployeeIds[$employeeId])) {
                continue;
            }

            $skeletonCount++;

            foreach ($dates as $date) {
                $slot = $roster[$employeeId.'|'.$date] ?? null;
                $isHolidayLike = Carbon::parse($date)->isSunday() || (bool) ($slot['is_holiday'] ?? false);

                $rows[] = [
                    'employee_id'    => $employeeId,
                    'date'           => $date,
                    'check_in'       => null,
                    'check_out'      => null,
                    'schedule_in'    => null,
                    'schedule_out'   => null,
                    'lm'             => 0,
                    'lm_count'       => 0,
                    'overtime'       => 0,
                    'overtime_count' => 0,
                    'late_minutes'   => 0,
                    'status'         => $isHolidayLike ? AttendancePrepare::STATUS_OFF : AttendancePrepare::STATUS_ABSENT,
                    'review_status'  => AttendancePrepare::REVIEW_CEK,
                    'is_locked'      => 0,
                    'notes'          => null,
                ];
            }
        }

        $otherActive = Employee::query()
            ->where('join_date', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('resign_date')->orWhere('resign_date', '>=', $periodStart))
            ->whereNotIn('id', $skeletonIds)
            ->whereNotIn('id', array_keys($matchedEmployeeIds))
            ->count();

        return ['rows' => $rows, 'count' => $skeletonCount, 'other_active' => $otherActive];
    }

    /**
     * Tanggal yang sudah ditempati leave_request berstatus approved.
     *
     * Dipakai agar pengisian data lama tidak membuat cuti dobel — kalau tanggalnya
     * sudah punya record approved, hari itu dilewati (bukan dibuatkan record baru),
     * supaya saldo cuti tidak terpotong dua kali.
     *
     * @param  list<int>  $employeeIds
     * @return array<int,array<string,bool>> employee_id => [Y-m-d => true]
     */
    protected function loadOccupiedLeaveDates(array $employeeIds, string $periodStart, string $periodEnd): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $occupied = [];

        LeaveRequest::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get(['employee_id', 'start_date', 'end_date'])
            ->each(function ($request) use (&$occupied) {
                $date = Carbon::parse($request->start_date);
                $end = Carbon::parse($request->end_date);

                while ($date->lte($end)) {
                    $occupied[$request->employee_id][$date->toDateString()] = true;
                    $date->addDay();
                }
            });

        return $occupied;
    }

    /**
     * Gabungkan hari cuti/izin/sakit yang bertanggal berurutan menjadi satu rentang.
     *
     * Tanggal yang sudah ditempati leave_request approved dibuang lebih dulu,
     * sehingga satu rentang bisa terpecah bila ada hari yang sudah terisi.
     *
     * @param  array<int,array<string,list<string>>>  $leaveDatesByEmployee
     * @param  \Illuminate\Support\Collection<string,\App\Modules\Leave\Models\LeaveType>  $leaveTypes
     * @param  \Illuminate\Support\Collection<int,string>  $employeeNames
     * @param  array<int,array<string,bool>>  $occupied
     * @return array{0: list<array<string,mixed>>, 1: list<array<string,mixed>>} [rentang, tanggal yang dilewati]
     */
    protected function buildLeaveRanges(array $leaveDatesByEmployee, $leaveTypes, $employeeNames, array $occupied = []): array
    {
        $ranges = [];
        $skipped = [];

        foreach ($leaveDatesByEmployee as $employeeId => $byCode) {
            foreach ($byCode as $code => $dates) {
                $leaveType = $leaveTypes[$code] ?? null;
                if (! $leaveType) {
                    continue;
                }

                // Buang tanggal yang sudah punya leave_request approved.
                $free = [];
                foreach ($dates as $date) {
                    if (isset($occupied[$employeeId][$date])) {
                        $skipped[] = [
                            'employee_id'   => $employeeId,
                            'employee_nama' => $employeeNames[$employeeId] ?? null,
                            'date'          => $date,
                            'leave_code'    => $code,
                        ];

                        continue;
                    }

                    $free[] = $date;
                }

                if ($free === []) {
                    continue;
                }

                sort($free);
                $start = null;
                $previous = null;

                $flush = function () use (&$ranges, &$start, &$previous, $employeeId, $code, $leaveType, $employeeNames) {
                    if ($start === null) {
                        return;
                    }

                    $days = Carbon::parse($start)->diffInDays(Carbon::parse($previous)) + 1;

                    $ranges[] = [
                        'employee_id'    => $employeeId,
                        'employee_nama'  => $employeeNames[$employeeId] ?? null,
                        'leave_type_id'  => $leaveType->id,
                        'leave_code'     => $code,
                        'start_date'     => $start,
                        'end_date'       => $previous,
                        'days_requested' => (int) $days,
                        'tanggal_masuk'  => Carbon::parse($previous)->addDay()->toDateString(),
                        'reason'         => $leaveType->name,
                    ];
                };

                foreach ($free as $date) {
                    if ($previous !== null && Carbon::parse($previous)->addDay()->toDateString() !== $date) {
                        $flush();
                        $start = null;
                    }

                    $start ??= $date;
                    $previous = $date;
                }

                $flush();
            }
        }

        return [$ranges, $skipped];
    }

    // ════════════════════════════════════════════════════════════════
    //  ROSTER
    // ════════════════════════════════════════════════════════════════

    /**
     * @return array<string,array{work_hour_start:?string,work_hour_end:?string,is_holiday:bool}>
     */
    protected function loadRoster(string $periodStart, string $periodEnd): array
    {
        $map = [];

        EmployeeShiftRoster::query()
            ->with('shift:id,work_hour_start,work_hour_end')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->chunk(1000, function ($rosters) use (&$map) {
                foreach ($rosters as $roster) {
                    $map[$roster->employee_id.'|'.Carbon::parse($roster->date)->toDateString()] = [
                        'work_hour_start' => $roster->shift?->work_hour_start,
                        'work_hour_end'   => $roster->shift?->work_hour_end,
                        'is_holiday'      => (bool) $roster->is_holiday,
                    ];
                }
            });

        return $map;
    }

    /**
     * @param  list<string>  $dates
     * @param  list<int>  $employeeIds
     * @return array{0:int,1:list<array{employee_id:int,days:int}>}
     */
    protected function inspectRosterGaps(array $dates, array $roster, array $employeeIds): array
    {
        $missing = 0;
        $byEmployee = [];

        foreach ($employeeIds as $employeeId) {
            $missingHere = 0;
            foreach ($dates as $date) {
                if (! isset($roster[$employeeId.'|'.$date])) {
                    $missingHere++;
                }
            }

            if ($missingHere > 0) {
                $missing += $missingHere;
                $byEmployee[] = ['employee_id' => $employeeId, 'days' => $missingHere];
            }
        }

        return [$missing, $byEmployee];
    }

    protected function countLocked(string $periodStart, string $periodEnd, array $employeeIds): int
    {
        if ($employeeIds === []) {
            return 0;
        }

        return AttendancePrepare::query()
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereIn('employee_id', $employeeIds)
            ->where('is_locked', true)
            ->count();
    }
}
