<?php

namespace App\Modules\Kasbon\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KasbonInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'kasbon_request_id',
        'pay_period_id',
        'installment_number',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // --- Relations ---

    public function kasbonRequest()
    {
        return $this->belongsTo(KasbonRequest::class, 'kasbon_request_id');
    }

    public function payPeriod()
    {
        return $this->belongsTo(\App\Modules\Payroll\Models\PayPeriod::class, 'pay_period_id');
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeByPayPeriod($query, $payPeriodId)
    {
        return $query->where('pay_period_id', $payPeriodId);
    }

    // --- Helpers ---

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
