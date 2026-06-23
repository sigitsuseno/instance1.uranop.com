<?php

namespace App\Modules\Reports\Helpers\Lembur;

use Carbon\Carbon;

/**
 * A. KARYAWAN JAKARTA
 * 
 * Tipe: uang_makan
 * Group: GRP-JKT
 */
class JakartaHelper
{
    use LemburHelperTrait;

    public static function getGroupCodes(): array
    {
        return ['GRP-JKT'];
    }

    public static function getLabel(): string
    {
        return 'A. KARYAWAN JAKARTA';
    }

    public static function getKey(): string
    {
        return 'jakarta';
    }

    public static function getType(): string
    {
        return 'uang_makan';
    }

    /**
     * Cek apakah employee termasuk group ini.
     */
    public static function matches($employee): bool
    {
        return $employee->groups->contains(fn($g) => $g->reference_code === 'GRP-JKT');
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

        [$umGroupName, $umRates] = $this->resolveUangMakanGroup($employee);

        $days = [];
        $totalOvertime = 0;
        $totalUangMakan = 0;

        foreach ($dates as $dateStr) {
            $prep   = $empPrepares->get($dateStr);
            $roster = $empRosters->get($dateStr);
            $parsedDate = Carbon::parse($dateStr);

            $statusRaw = $prep ? $prep->status : '-';
            $isHoliday = $roster && $roster->is_holiday;
            $dayOfWeek = $parsedDate->dayOfWeek;

            $ha = $this->getHACode($statusRaw, $isHoliday, $roster);

            $dayResult = $this->processDayUangMakan(
                $ha, $upahPerHari, $hourlyRate, $umRates, $isHoliday, $dayOfWeek, $prep
            );

            $totalOvertime += 0; // Jakarta = Uang Makan only
            $totalUangMakan += $dayResult['uang_makan'];

            $days[$dateStr] = $dayResult;
        }

        return $this->buildEmployeeItem(
            $employee, $days, $upahPerHari, $hourlyRate,
            $gaji, $tjMk, $tunjangan, $totalOvertime, $totalUangMakan
        );
    }
}
