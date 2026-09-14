<?php

namespace App\Modules\Employee\Services;

use App\Modules\Payroll\Models\PayPeriod;
use Carbon\Carbon;
use Exception;

class CompensationPeriodService
{
    /**
     * Rentang tanggal kontrak untuk kompensasi pada satu pay period.
     *
     * Window-nya adalah periode penuh sesuai tabel pay_periods
     * (contoh: periode September = 25 Agustus s/d 24 September),
     * tanpa dipecah lagi menjadi fase awal/akhir.
     *
     * @param int $year
     * @param int $month
     * @return array{start: Carbon, end: Carbon, label: string}
     * @throws Exception
     */
    public function calculateCompensationDates($year, $month): array
    {
        $payPeriod = PayPeriod::where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if (!$payPeriod) {
            throw new Exception("Pay Period untuk bulan {$month} tahun {$year} belum di-generate.");
        }

        return [
            'start' => Carbon::parse($payPeriod->start_date),
            'end'   => Carbon::parse($payPeriod->end_date),
            'label' => 'Periode Penuh',
        ];
    }
}
