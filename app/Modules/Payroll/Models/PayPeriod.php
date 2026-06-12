<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Settings\Models\SystemSetting;
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
        'is_split',
        'system_setting_id',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_year'  => 'integer',
        'period_month' => 'integer',
        'is_split'     => 'boolean',
        'start_date'   => 'date',
        'end_date'     => 'date',
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
            'active'   => 'Aktif',
            'inactive' => 'Tidak Aktif',
            default    => $this->status,
        };
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function systemSetting()
    {
        return $this->belongsTo(SystemSetting::class, 'system_setting_id');
    }

    public static function getPeriods()
    {
        return static::orderBy('start_date', 'desc')->get();
    }

    public function pphRecords()
    {
        return $this->hasMany(EmployeePph::class, 'pay_period_id');
    }
}
