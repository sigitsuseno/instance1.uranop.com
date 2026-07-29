<?php

namespace App\Modules\Reports\Helpers\Lembur;

use App\Modules\Settings\Services\ReportConfigService;
use Carbon\Carbon;

/**
 * D. KARYAWAN SPESIFIK
 * 
 * Tipe: lembur
 * Group: KRY-SPC
 * 
 * Spesial: hourlyRate = spc_base_salary / 173 (dari config)
 *          section hanya tampil jika periode >= spc_start_period_id
 */
class SpcHelper
{
    use LemburHelperTrait;

    public static function getGroupCodes(): array
    {
        return ['KRY-SPC'];
    }

    public static function getLabel(): string
    {
        return 'D. KARYAWAN SPESIFIK';
    }

    public static function getKey(): string
    {
        return 'spc';
    }

    public static function getType(): string
    {
        return 'lembur';
    }

    /**
     * Cek apakah section ini harus ditampilkan untuk periode tertentu.
     */
    public static function shouldShow(?int $periodId): bool
    {
        $config = app(ReportConfigService::class)->getConfig('lembur_uang_makan');
        $startPeriodId = $config['spc_start_period_id'] ?? null;

        if ($startPeriodId === null) {
            return true; // Belum di-set → tampil di semua periode
        }

        return $periodId !== null && $periodId >= $startPeriodId;
    }

    /**
     * Cek apakah employee termasuk group ini.
     */
    public static function matches($employee): bool
    {
        return $employee->groups->contains(fn($g) => $g->reference_code === 'KRY-SPC');
    }

    /**
     * Hitung hourly rate spesifik: spc_base_salary / 173.
     * Fallback ke gaji / 173 jika spc_base_salary tidak di-set.
     */
    public function calculateHourlyRate(float $gaji): float
    {
        $config = app(ReportConfigService::class)->getConfig('lembur_uang_makan');
        $baseSalary = $config['spc_base_salary'] ?? null;

        if ($baseSalary !== null && $baseSalary > 0) {
            return round($baseSalary / 173, 2);
        }

        // Fallback: gaji / 173
        return $gaji > 0 ? round($gaji / 173, 2) : 0;
    }

    /**
     * Hitung upah per hari.
     */
    public function calculateUpahPerHari(float $gaji, float $tjMk): float
    {
        return ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;
    }

    /**
     * Proses satu employee menjadi item laporan.
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
        $hourlyRate  = $this->calculateHourlyRate($gaji);
        $isSG = $employee->groups->contains('reference_code', 'SG');

        $days = [];
        $totalOvertime = 0;
        $totalUangMakan = 0;

        foreach ($dates as $dateStr) {
            $prep   = $empPrepares->get($dateStr);
            $roster = $empRosters->get($dateStr);
            $parsedDate = Carbon::parse($dateStr);

            $statusRaw = $prep ? $prep->status : '-';
            $isHoliday = $roster && $roster->is_holiday;

            $ha = $this->getHACode($statusRaw, $isHoliday, $roster);

            $dayResult = $this->processDayLembur(
                $statusRaw, $ha, $upahPerHari, $hourlyRate, $isSG, $prep
            );

            $totalOvertime += 0;
            $totalUangMakan += $dayResult['overtime_nominal']; // SPC nominal masuk ke Total U. MKN+INS

            $days[$dateStr] = $dayResult;
        }

        return $this->buildEmployeeItem(
            $employee, $days, $upahPerHari, $hourlyRate,
            $gaji, $tjMk, $tunjangan, $totalOvertime, $totalUangMakan
        );
    }
}
