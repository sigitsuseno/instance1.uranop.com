<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * PayPeriod — Periode Payroll.
 * Model minimal untuk Fase 4 (prerequisite FK employee_bpjs).
 * Akan di-expand lebih detail di Fase 8 (Payroll).
 */
class PayPeriod extends Model
{
    use SoftDeletes;

    protected $table = 'pay_periods';

    protected $fillable = [
        'uuid',
        'name',
        'period_year',
        'period_month',
        'status',
        'started_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_year'  => 'integer',
        'period_month' => 'integer',
        'started_at'   => 'date',
        'closed_at'    => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (PayPeriod $period) {
            if (empty($period->uuid)) {
                $period->uuid = (string) Str::uuid();
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'      => 'Draft',
            'processing' => 'Diproses',
            'locked'     => 'Terkunci',
            'closed'     => 'Ditutup',
            default      => $this->status,
        };
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'processing']);
    }
}
