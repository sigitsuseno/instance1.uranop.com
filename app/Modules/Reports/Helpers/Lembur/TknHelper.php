<?php

namespace App\Modules\Reports\Helpers\Lembur;

use Carbon\Carbon;

/**
 * KARYAWAN TEKNISI (Flag: KRY-TKN)
 * 
 * BUKAN section terpisah — karyawan tetap di section GRP aslinya.
 * KRY-TKN hanya penanda untuk override formula uang makan.
 * 
 * Formula:
 *   Weekday ≥ 11 jam → flat 15.000
 *   Sabtu           → lemburTotal × (100.000 ÷ 7)
 *   Minggu/Holiday  → lemburTotal × (200.000 ÷ 7)
 */
class TknHelper
{
    use LemburHelperTrait;

    public static function getGroupCodes(): array
    {
        return ['KRY-TKN'];
    }

    public static function getLabel(): string
    {
        return 'TEKNISI';
    }

    public static function getKey(): string
    {
        return 'teknisi';
    }

    public static function getType(): string
    {
        return 'uang_makan';
    }

    /**
     * Cek apakah employee termasuk teknisi.
     */
    public static function matches($employee): bool
    {
        return $employee->groups->contains(fn($g) => $g->reference_code === 'KRY-TKN');
    }

    /**
     * Hitung hourly rate (standard).
     */
    public function calculateHourlyRate(float $gaji, float $tjMk, float $tunjangan): float
    {
        return $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;
    }

    /**
     * Hitung upah per hari.
     */
    public function calculateUpahPerHari(float $gaji, float $tjMk): float
    {
        return ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;
    }

    /**
     * Proses satu employee teknisi → item laporan (struktur uang_makan).
     */
    public function processEmployee(
        $employee,
        $empPrepares,
        $empRosters,
        $payRecord,
        array $dates,
        float $gaji,
        float $tjMk,
        float $tunjangan,
        array $config
    ): array {
        $upahPerHari = $this->calculateUpahPerHari($gaji, $tjMk);
        $hourlyRate  = $this->calculateHourlyRate($gaji, $tjMk, $tunjangan);

        $days = [];
        $totalOvertime = 0;
        $totalUangMakan = 0;

        foreach ($dates as $dateStr) {
            $prep   = $empPrepares->get($dateStr);
            $roster = $empRosters->get($dateStr);
            $parsedDate = Carbon::parse($dateStr);

            $statusRaw = $prep ? $prep->status : '-';
            $isHoliday = $roster && $roster->is_holiday;
            $dayOfWeek = $parsedDate->dayOfWeek; // 0=Minggu, 6=Sabtu

            $ha = $this->getHACode($statusRaw, $isHoliday, $roster);

            // ── Upah harian ─────────────────────────────────
            $upahHarian = ($ha === 'H' && $isHoliday) ? 0
                : (in_array($ha, ['H', 'C', 'S']) ? $upahPerHari : 0);

            // ── Kode ────────────────────────────────────────
            $kode = in_array($ha, ['H', 'S', 'C']) ? 'L' : '';

            // ── Lembur raw ──────────────────────────────────
            $lmRaw         = $prep ? (int)$prep->lm : 0;
            $overtimeRaw   = $prep ? (int)$prep->overtime : 0;
            $workHour      = round(($lmRaw + $overtimeRaw) / 60, 2);

            $lmDisplay        = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
            $overtimeDisplay  = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;
            $lemburTotal      = $lmDisplay + $overtimeDisplay;

            // ── TKN Formula ─────────────────────────────────
            $nominal = 0;
            $isMingguHoliday = ($dayOfWeek == 0 || $isHoliday);

            if ($lemburTotal > 0) {
                if ($isMingguHoliday) {
                    // Minggu / Holiday: per jam × (200.000 ÷ 7)
                    $nominal = round($lemburTotal * (200000 / 7), 2);
                } elseif ($dayOfWeek == 6) {
                    // Sabtu: per jam × (100.000 ÷ 7)
                    $nominal = round($lemburTotal * (100000 / 7), 2);
                } else {
                    // Weekday: ≥ 11 jam → flat 15.000
                    if ($workHour >= 11) {
                        $nominal = 15000;
                    }
                }
            }

            // ── Day result ──────────────────────────────────
            $dayResult = [
                'kode'             => $kode,
                'ha'               => $ha,
                'upah_per_hari'    => $upahHarian,
                'lm'               => '',           // TKN: kosong
                'lembur'           => $nominal > 0 ? 'TKN' : '',
                'nominal'          => $nominal,
                'uang_makan'       => $nominal,
                'overtime_nominal' => 0,            // TKN = uang_makan type
            ];

            $totalOvertime += 0; // TKN = uang_makan only
            $totalUangMakan += $nominal;

            $days[$dateStr] = $dayResult;
        }

        return $this->buildEmployeeItem(
            $employee, $days, $upahPerHari, $hourlyRate,
            $gaji, $tjMk, $tunjangan, $totalOvertime, $totalUangMakan
        );
    }
}
