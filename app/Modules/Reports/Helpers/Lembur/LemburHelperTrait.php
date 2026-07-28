<?php

namespace App\Modules\Reports\Helpers\Lembur;

use Carbon\Carbon;

trait LemburHelperTrait
{
    /**
     * Static cache: kode leave type → is_paid.
     * @var array<string, bool>|null
     */
    private static ?array $_ltPaidMap = null;

    /**
     * Load semua leave type code → is_paid (static cache, shared via trait).
     */
    private static function getLeaveTypePaidMap(): array
    {
        if (self::$_ltPaidMap === null) {
            self::$_ltPaidMap = \App\Modules\Leave\Models\LeaveType::pluck('is_paid', 'code')
                ->mapWithKeys(fn($isPaid, $code) => [strtolower($code) => (bool) $isPaid])
                ->toArray();
        }
        return self::$_ltPaidMap;
    }

    /**
     * Dapatkan kode H/A dari status attendance.
     *
     * Logika "I" (Izin) sekarang berbasis LeaveType.is_paid:
     * - is_paid === false → 'I' (unpaid, potong 25 hari)
     * - is_paid === true  → 'H' (paid, tidak potong 25 hari)
     * - bukan leave type  → fallback ke aturan lama
     */
    protected function getHACode(string $statusRaw, bool $isHoliday, $roster): string
    {
        $ltPaidMap = self::getLeaveTypePaidMap();
        $code      = strtolower($statusRaw);
        $ltPaid    = $ltPaidMap[$code] ?? null; // null = bukan leave type

        return match (true) {
            $statusRaw === 'hadir'          => 'H',
            $statusRaw === 'absent'         => 'A',
            $statusRaw === 'libur',
            $statusRaw === 'off'            => 'OFF',
            $statusRaw === 'skt'            => 'S',
            str_starts_with($statusRaw, 'c') => 'C',
            $statusRaw === 'imt',
            $statusRaw === 'ipa'            => 'H',
            // Unpaid leave type → Izin (potong 25 hari)
            $ltPaid === false               => 'I',
            // Paid leave type → Hadir (tidak potong 25 hari)
            $ltPaid === true                => 'H',
            // Bukan leave type → fallback
            default                         => $statusRaw === '-' ? '-' : 'I',
        };
    }

    /**
     * Apakah hari ini dapat upah?
     */
    protected function dapatUpah(string $ha, bool $isHoliday): bool
    {
        return in_array($ha, ['H', 'C', 'S']);
    }

    /**
     * Hitung total section (untuk detail).
     */
    public static function calculateSectionTotals($employees): array
    {
        return [
            'total_hari_kerja' => round($employees->sum('total_hari_kerja'), 2),
            'total_overtime'   => round($employees->sum('total_overtime'), 2),
            'total_uang_makan' => round($employees->sum('total_uang_makan'), 2),
            'total_terima'     => round($employees->sum('total_terima'), 2),
            'count'            => $employees->count(),
        ];
    }

    /**
     * Proses data lembur + nominal untuk tipe LEMBUR (Printing / SPC).
     */
    protected function processDayLembur(
        string $statusRaw,
        string $ha,
        float $upahPerHari,
        float $hourlyRate,
        bool $isSG,
        $prep
    ): array {
        // Kode
        if (in_array($statusRaw, ['absent', 'libur', 'off', 'itm', 'izn', '-'])) {
            $kode = '';
        } else {
            $kode = $isSG ? 'SG' : 'L';
        }

        // Upah harian
        $upahHarian = ($ha === 'H' || $ha === 'C' || $ha === 'S') ? $upahPerHari : 0;

        // Lembur raw
        $lmRaw       = $prep ? (int)$prep->lm : 0;
        $overtimeRaw = $prep ? (int)$prep->overtime : 0;
        $lmCount      = $prep ? (int)$prep->lm_count : 0;
        $overtimeCount = $prep ? (int)$prep->overtime_count : 0;

        $lmDisplay      = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
        $overtimeDisplay = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;

        $lmNominal       = $lmCount > 0 ? round(($lmCount / 60) * $hourlyRate, 2) : 0;
        $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;
        $totalOvertimeNominal = round($lmNominal + $overtimeNominal, 2);

        return [
            'kode'             => $kode,
            'ha'               => $ha,
            'upah_per_hari'    => $upahHarian,
            'lm'               => $lmDisplay,
            'lembur'           => $overtimeDisplay,
            'nominal'          => $totalOvertimeNominal,
            'overtime_nominal' => $totalOvertimeNominal,
            'uang_makan'       => 0,
        ];
    }

    /**
     * Proses data lembur + uang makan untuk tipe UANG MAKAN (Jakarta / ALL IN).
     */
    protected function processDayUangMakan(
        string $ha,
        float $upahPerHari,
        float $hourlyRate,
        array $umRates,
        bool $isHoliday,
        int $dayOfWeek,
        $prep
    ): array {
        $upahHarian = ($ha === 'H' && $isHoliday) ? 0 : (in_array($ha, ['H', 'C', 'S']) ? $upahPerHari : 0);

        // Lembur raw
        $lmRaw       = $prep ? (int)$prep->lm : 0;
        $overtimeRaw = $prep ? (int)$prep->overtime : 0;
        $lmCount      = $prep ? (int)$prep->lm_count : 0;
        $overtimeCount = $prep ? (int)$prep->overtime_count : 0;

        $lmDisplay      = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
        $overtimeDisplay = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;
        $lemburTotal    = $lmDisplay + $overtimeDisplay;

        $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;

        $kode = in_array($ha, ['H', 'S', 'C']) ? 'L' : '';
        $nominal = 0;
        $lmStr = '';
        $lemburStr = '';

        if ($lemburTotal > 0) {
            $isMingguHoliday = ($dayOfWeek == 0 || $isHoliday);

            if ($isMingguHoliday) {
                if ($lemburTotal >= 8) {
                    $nominal = $umRates['minggu_full'] ?? 0;
                    $lmStr = 'FULL';
                } elseif ($lemburTotal >= 4) {
                    $nominal = $umRates['minggu_half'] ?? 0;
                    $lmStr = 'HALF';
                }
            } elseif ($dayOfWeek == 6) {
                if ($lemburTotal >= 4) {
                    $nominal = $umRates['sabtu_full'] ?? 0;
                    $lemburStr = 'FULL';
                } elseif ($lemburTotal >= 2) {
                    $nominal = $umRates['sabtu_dua'] ?? 0;
                    $lemburStr = '2';
                }
            } else {
                if ($lemburTotal >= 2) {
                    $nominal = $umRates['weekday'] ?? 15000;
                    $lemburStr = 'UM';
                }
            }
        }

        return [
            'kode'              => $kode,
            'ha'                => $ha,
            'upah_per_hari'     => $upahHarian,
            'lm'                => $lmStr,
            'lembur'            => $lemburStr,
            'nominal'           => $nominal,
            'uang_makan'        => $nominal,
            'overtime_nominal'  => $overtimeNominal,
        ];
    }

    /**
     * Resolve uang makan group dari employee.
     */
    protected function resolveUangMakanGroup($employee): array
    {
        $umGroup = $employee->groups->first(fn($g) =>
            $g->master && strtoupper($g->master->group_label ?? '') === 'UANG MAKAN'
        );
        $umGroupName = $umGroup ? strtoupper($umGroup->master->name ?? '') : '';
        $rates = $this->resolveUangMakanRates($umGroupName, 0);

        return [$umGroupName, $rates];
    }

    /**
     * Dapatkan rate uang makan berdasarkan nama group.
     */
    protected function resolveUangMakanRates(string $groupName, float $gajiPokok): array
    {
        $config = app(\App\Modules\Settings\Services\ReportConfigService::class)->getConfig('lembur_uang_makan');
        $upper = strtoupper($groupName);

        if (str_contains($upper, 'KABAG')) {
            return $config['KABAG'] ?? [];
        }
        if (str_contains($upper, 'KEPALA SHIFT') || str_contains($upper, 'KASHIFT')) {
            return $config['KASHIFT'] ?? [];
        }
        if (str_contains($upper, 'ALL IN') || str_contains($upper, 'ALL-IN')) {
            return $config['ALL IN'] ?? [];
        }

        return [
            'weekday'      => 15000,
            'sabtu_dua'    => 0,
            'sabtu_full'   => 0,
            'minggu_half'  => 0,
            'minggu_full'  => 0,
        ];
    }

    /**
     * Build employee item array.
     */
    protected function buildEmployeeItem(
        $employee,
        array $days,
        float $upahPerHari,
        float $upahLemburPerJam,
        float $gaji,
        float $tjMk,
        float $tunjangan,
        float $totalOvertime,
        float $totalUangMakan
    ): array {
        $totalHariKerja = collect($days)->sum('upah_per_hari');

        return [
            'id'                   => $employee->id,
            'name'                 => $employee->name,
            'jabatan'              => $employee->position->name ?? '-',
            'gender'               => $employee->gender ?? '',
            'tj_mk'                => $tjMk,
            'tunjangan'            => $tunjangan,
            'upah_per_hari'        => $upahPerHari,
            'upah_lembur_per_jam'  => $upahLemburPerJam,
            'days'                 => $days,
            'total_hari_kerja'     => round($totalHariKerja, 2),
            'total_overtime'       => round($totalOvertime, 2),
            'total_uang_makan'     => round($totalUangMakan, 2),
            'total_terima'         => round($totalHariKerja + $totalOvertime + $totalUangMakan, 2),
        ];
    }
}
