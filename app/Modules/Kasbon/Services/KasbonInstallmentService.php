<?php

namespace App\Modules\Kasbon\Services;

use App\Modules\Kasbon\Models\KasbonRequest;
use App\Modules\Kasbon\Models\KasbonInstallment;
use Illuminate\Support\Facades\DB;

class KasbonInstallmentService
{
    /**
     * Generate cicilan setelah kasbon disetujui
     */
    public function generateInstallments(KasbonRequest $kasbon): array
    {
        $tenor = $kasbon->tenor;
        $totalAmount = $kasbon->amount;

        // Hitung nominal per cicilan (pembulatan ke bawah)
        $perInstallment = floor($totalAmount / $tenor);

        // Cicilan terakhir menyerap selisih pembulatan
        $lastInstallment = $totalAmount - ($perInstallment * ($tenor - 1));

        $installments = [];
        for ($i = 1; $i <= $tenor; $i++) {
            $amount = ($i === $tenor) ? $lastInstallment : $perInstallment;

            $installments[] = KasbonInstallment::create([
                'kasbon_request_id' => $kasbon->id,
                'pay_period_id' => null,
                'installment_number' => $i,
                'amount' => $amount,
                'status' => 'pending',
            ]);
        }

        return $installments;
    }

    /**
     * Bayar 1 cicilan
     */
    public function payInstallment(KasbonInstallment $installment): KasbonInstallment
    {
        if ($installment->isPaid()) {
            throw new \Exception('Cicilan ini sudah dibayar.');
        }

        return DB::transaction(function () use ($installment) {
            $installment->markAsPaid();

            // Kurangi remaining_amount di kasbon_request
            $kasbon = $installment->kasbonRequest;
            $remaining = max(0, $kasbon->remaining_amount - $installment->amount);
            $kasbon->update(['remaining_amount' => $remaining]);

            // Jika semua cicilan lunas, tandai completed
            if ($remaining <= 0) {
                $kasbon->update(['status' => 'completed']);
            }

            return $installment->fresh();
        });
    }

    /**
     * Push cicilan ke periode payroll tertentu (manual)
     */
    public function pushToPayPeriod(KasbonInstallment $installment, int $payPeriodId): KasbonInstallment
    {
        if ($installment->isPaid()) {
            throw new \Exception('Cicilan ini sudah dibayar.');
        }

        $installment->update([
            'pay_period_id' => $payPeriodId,
        ]);

        return $installment->fresh();
    }
}
