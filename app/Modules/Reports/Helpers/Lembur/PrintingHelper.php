<?php

namespace App\Modules\Reports\Helpers\Lembur;

use Carbon\Carbon;

/**
 * C. KARYAWAN BULANAN PRINTING
 * 
 * Tipe: lembur
 * Group: GRP-PS1, GRP-SS
 */
class PrintingHelper
{
    use LemburHelperTrait;

    public static function getGroupCodes(): array
    {
        return ['GRP-PS1', 'GRP-SS'];
    }

    public static function getLabel(): string
    {
        return 'C. KARYAWAN BULANAN PRINTING';
    }

    public static function getKey(): string
    {
        return 'printing';
    }

    public static function getType(): string
    {
        return 'lembur';
    }

    /**
     * Cek apakah employee termasuk group ini.
     */
    public static function matches($employee): bool
    {
        return $employee->groups->contains(fn($g) =>
            in_array($g->reference_code, ['GRP-PS1', 'GRP-SS'])
        );
    }

    /**
     * Hitung hourly rate (standard: gaji + tj_mk + tunjangan / 173).
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
        $hourlyRate  = $this->calculateHourlyRate($gaji, $tjMk, $tunjangan);
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

            $totalOvertime += $dayResult['overtime_nominal'];
            $totalUangMakan += 0; // Printing = Lembur only

            $days[$dateStr] = $dayResult;
        }

        return $this->buildEmployeeItem(
            $employee, $days, $upahPerHari, $hourlyRate,
            $gaji, $tjMk, $tunjangan, $totalOvertime, $totalUangMakan
        );
    }
}
