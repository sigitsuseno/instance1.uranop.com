<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aturan window deteksi kehadiran yang dipakai bersama oleh halaman
 * Settings → Simulasi Deteksi dan fetch Manual Sync.
 *
 * - P (pagi)          : scan pada tanggal roster, filter window shift pagi
 * - S / ML (siang/malam): data mulai 12 jam setelah tanggal roster,
 *                         filter window shift siang/malam
 * - Minggu/libur non-SHIFT: range tetap 06:00–18:00
 */
class DetectionWindowService
{
    public const GROUP = 'detection_simulation';

    /** key => default (H:i) */
    public const DEFAULTS = [
        'global_start_time'      => '04:00',
        'global_end_time'        => '23:59',
        'shift_pagi_start_time'  => '06:00',
        'shift_pagi_end_time'    => '14:00',
        'shift_siang_start_time' => '14:00',
        'shift_siang_end_time'   => '22:00',
        'shift_malam_start_time' => '22:00',
        'shift_malam_end_time'   => '06:00',
    ];

    /** external_code => grup shift */
    public const SHIFT_GROUPS = [
        'P'  => 'pagi',
        'S'  => 'siang',
        'ML' => 'malam',
    ];

    /**
     * Keluarga kode shift => grup (exact match).
     * 'M' (Minggu) dan 'L' (Libur) sengaja tidak termasuk.
     */
    public const SHIFT_FAMILIES = [
        'siang' => ['S', 'S1', 'S2', 'S3'],
        'malam' => ['ML', 'ML1', 'MK', 'MS', 'MSS'],
    ];

    /**
     * Grup shift dari external_code, termasuk anggota keluarganya.
     */
    public static function resolveGroup(?string $externalCode): ?string
    {
        $code = strtoupper(trim((string) $externalCode));

        if ($code === '') {
            return null;
        }

        foreach (self::SHIFT_FAMILIES as $group => $codes) {
            if (in_array($code, $codes, true)) {
                return $group;
            }
        }

        return self::SHIFT_GROUPS[$code] ?? null;
    }

    public const GROUP_LABELS = [
        'pagi'  => 'Pagi',
        'siang' => 'Siang',
        'malam' => 'Malam',
    ];

    public const LIBUR_LABEL = 'Libur / Minggu';
    public const NONE_LABEL  = 'Tanpa aturan';

    /**
     * Nilai setting yang tersimpan, dilengkapi default.
     */
    public function settings(): array
    {
        $stored = SystemSetting::where('group', self::GROUP)
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->pluck('value', 'key')
            ->toArray();

        return array_merge(self::DEFAULTS, array_filter($stored, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Tentukan aturan deteksi untuk satu roster.
     *
     * @return array{rule:string,label:string,window_start:?string,window_end:?string}
     */
    public function resolveRule(
        EmployeeShiftRoster $roster,
        array $settings,
        ?bool $isHoliday = null,
        ?bool $isSunday = null
    ): array {
        $dateStr = $roster->date instanceof Carbon
            ? $roster->date->toDateString()
            : (string) $roster->date;

        $externalCode = strtoupper(trim((string) (
            $roster->shift->external_code
            ?? $roster->external_code
            ?? ''
        )));

        $group = self::resolveGroup($externalCode);

        $isOffDay = (bool) $roster->is_sun
            || (bool) $roster->is_holiday
            || (bool) $isSunday
            || (bool) $isHoliday
            || Carbon::parse($dateStr)->isSunday();

        $workPatternType = strtoupper(trim((string) (
            $roster->work_pattern_type
            ?? $roster->workPattern?->employee_type
            ?? ''
        )));

        if ($isOffDay && $workPatternType !== 'SHIFT') {
            return [
                'rule'         => 'libur',
                'label'        => self::LIBUR_LABEL,
                'window_start' => '06:00',
                'window_end'   => '18:00',
            ];
        }

        if ($group) {
            return [
                'rule'         => $group,
                'label'        => self::GROUP_LABELS[$group],
                'window_start' => $settings["shift_{$group}_start_time"],
                'window_end'   => $settings["shift_{$group}_end_time"],
            ];
        }

        return [
            'rule'         => 'none',
            'label'        => self::NONE_LABEL,
            'window_start' => null,
            'window_end'   => null,
        ];
    }

    /**
     * Filter log milik karyawan sesuai aturan roster.
     */
    public function matchedLogs(Collection $logs, string $dateStr, array $rule): Collection
    {
        if (($rule['rule'] ?? 'none') === 'none') {
            return collect();
        }

        $startTime = $rule['window_start'];
        $endTime   = $rule['window_end'];

        if (in_array($rule['rule'], ['pagi', 'libur'], true)) {
            return $logs->filter(function ($log) use ($dateStr, $startTime, $endTime) {
                $scan = Carbon::parse($log->scan_datetime);

                return $scan->toDateString() === $dateStr
                    && $this->timeInWindow($scan, $startTime, $endTime);
            })->values();
        }

        // Siang & malam: data mulai 12 jam setelah tanggal roster (D 12:00 → D+1 12:00).
        $fetchStart = Carbon::parse($dateStr)->setTime(12, 0);
        $fetchEnd   = (clone $fetchStart)->addDay();

        return $logs->filter(function ($log) use ($fetchStart, $fetchEnd, $startTime, $endTime) {
            $scan = Carbon::parse($log->scan_datetime);

            return $scan->greaterThanOrEqualTo($fetchStart)
                && $scan->lessThanOrEqualTo($fetchEnd)
                && $this->timeInWindow($scan, $startTime, $endTime);
        })->values();
    }

    /**
     * Rentang datetime untuk sebuah tanggal. Bila end < start, dianggap
     * melewati tengah malam sehingga end jatuh pada hari berikutnya.
     */
    public function windowRange(string $date, string $startTime, string $endTime): array
    {
        $start = Carbon::parse("{$date} {$startTime}");
        $end   = Carbon::parse("{$date} {$endTime}");

        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * Apakah jam scan masuk window berdasarkan jam (tanpa peduli tanggal).
     * Bila end < start, window dianggap melewati tengah malam.
     */
    public function timeInWindow(Carbon $scan, string $startTime, string $endTime): bool
    {
        $minutes = ($scan->hour * 60) + $scan->minute;

        [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
        [$endHour, $endMinute]     = array_map('intval', explode(':', $endTime));

        $start = ($startHour * 60) + $startMinute;
        $end   = ($endHour * 60) + $endMinute;

        if ($start <= $end) {
            return $minutes >= $start && $minutes <= $end;
        }

        return $minutes >= $start || $minutes <= $end;
    }
}
