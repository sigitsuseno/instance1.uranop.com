<?php

namespace App\Modules\Employee\Services;

use App\Modules\Payroll\Models\PayPeriod;
use Carbon\Carbon;
use Exception;

class CompensationPeriodService
{
    /**
     * Calculate compensation dates based on PayPeriod.
     *
     * @param int $year
     * @param int $month
     * @param string $periode ('auto', 'awal', 'akhir')
     * @return array
     * @throws Exception
     */
    public function calculateCompensationDates($year, $month, $periode): array
    {
        // 1. Get current pay period
        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$payPeriod) {
            throw new Exception("Pay Period untuk bulan {$month} tahun {$year} belum di-generate.");
        }

        $start_date = Carbon::parse($payPeriod->start_date);
        $end_date = Carbon::parse($payPeriod->end_date);

        // Calculate tanggal_tengah_periode: ceil(jarak / 2) - 1
        $totalDays = $start_date->diffInDays($end_date) + 1; // distance in days inclusive
        $jarak = ceil($totalDays / 2) - 1;
        $tanggal_tengah_periode = $start_date->copy()->addDays($jarak);

        if ($periode === 'auto') {
            $today = Carbon::today();
            // If today is before or equal to tanggal_tengah_periode, it's 'awal', else 'akhir'
            if ($today->lte($tanggal_tengah_periode)) {
                $periode = 'awal';
            } else {
                $periode = 'akhir';
            }
        }

        if ($periode === 'awal') {
            // Periode Awal
            // Waktu pelaksanaan: start_date s/d (tanggal_tengah_periode - 1) -> not strictly needed for query, but this is the logical phase
            // Filter Kontrak yang dicari: tanggal_tengah_periode s/d end_date
            $filterStartDate = $tanggal_tengah_periode->copy();
            $filterEndDate = $end_date->copy();
            $label = 'Awal (Pembayaran Maju)';
        } else {
            // Periode Akhir
            // Waktu pelaksanaan: tanggal_tengah_periode s/d end_date
            // Filter Kontrak yang dicari: start_date bulan selanjutnya s/d tanggal_tengah_periode bulan selanjutnya
            
            // Need to get next month's Pay Period
            $nextMonth = $month + 1;
            $nextYear = $year;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }

            $nextPayPeriod = PayPeriod::where('period_year', $nextYear)
                ->where('period_month', $nextMonth)
                ->first();

            if (!$nextPayPeriod) {
                throw new Exception("Pay Period untuk bulan {$nextMonth} tahun {$nextYear} belum di-generate (dibutuhkan untuk kompensasi akhir periode).");
            }

            $next_start_date = Carbon::parse($nextPayPeriod->start_date);
            $next_end_date = Carbon::parse($nextPayPeriod->end_date);

            $nextTotalDays = $next_start_date->diffInDays($next_end_date) + 1;
            $nextJarak = ceil($nextTotalDays / 2) - 1;
            $next_tanggal_tengah_periode = $next_start_date->copy()->addDays($nextJarak);

            $filterStartDate = $next_start_date->copy();
            $filterEndDate = $next_tanggal_tengah_periode->copy();
            $label = 'Akhir (Pembayaran Maju)';
        }

        return [
            'start' => $filterStartDate,
            'end' => $filterEndDate,
            'label' => $label
        ];
    }
}
