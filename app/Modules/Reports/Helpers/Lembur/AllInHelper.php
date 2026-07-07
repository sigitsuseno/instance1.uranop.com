<?php

namespace App\Modules\Reports\Helpers\Lembur;

use Carbon\Carbon;

/**
 * B. KARYAWAN ALL IN
 * 
 * Tipe: uang_makan
 * Group: sisanya (bukan JKT, bukan Printing, bukan SPC)
 */
class AllInHelper
{
    use LemburHelperTrait;

    public static function getGroupCodes(): array
    {
        return ['GRP-ALLIN', 'GRP-GD', 'GRP-SPR'];
    }

    public static function getLabel(): string
    {
        return 'B. KARYAWAN ALL IN';
    }

    public static function getKey(): string
    {
        return 'all_in';
    }

    public static function getType(): string
    {
        return 'uang_makan';
    }

    /**
     * Cek apakah employee termasuk group ini.
     * ALL IN = bukan JKT, bukan Printing, bukan SPC.
     */
    public static function matches($employee): bool
    {
        $isJakarta  = $employee->groups->contains(fn($g) => $g->reference_code === 'GRP-JKT');
        $isPrinting = $employee->groups->contains(fn($g) =>
            in_array($g->reference_code, ['GRP-PS1', 'GRP-SS'])
        );
        $isSPC = $employee->groups->contains(fn($g) => $g->reference_code === 'KRY-SPC');
        
        return !$isJakarta && !$isPrinting && !$isSPC;
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

        // ── Pengecualian Uang Lembur Sabtu/Minggu/Holiday ──────────
        // Karyawan yang dicentang di pengaturan → tidak dapat uang lembur
        // di hari Sabtu, Minggu, dan Holiday (weekday tetap dapat).
        $excludedIds = $config['allin_no_overtime_employees'] ?? [];
        if (in_array($employee->id, $excludedIds)) {
            $umRates['sabtu_dua']   = 0;
            $umRates['sabtu_full']  = 0;
            $umRates['minggu_half'] = 0;
            $umRates['minggu_full'] = 0;
        }

        // ── Uang Lembur Manual Driver ──────────────────────────────
        // Driver dapat tambahan uang lembur manual dari config.
        $driverOvertimeMap = $config['allin_driver_overtime'] ?? [];
        $manualDriverOvertime = isset($driverOvertimeMap[$employee->id])
            ? (int) $driverOvertimeMap[$employee->id]
            : 0;

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

            $totalOvertime += 0; // ALL IN = Uang Makan only
            $totalUangMakan += $dayResult['uang_makan'];

            $days[$dateStr] = $dayResult;
        }

        // Tambahkan uang lembur manual driver
        $totalUangMakan += $manualDriverOvertime;

        return $this->buildEmployeeItem(
            $employee, $days, $upahPerHari, $hourlyRate,
            $gaji, $tjMk, $tunjangan, $totalOvertime, $totalUangMakan
        );
    }
}
