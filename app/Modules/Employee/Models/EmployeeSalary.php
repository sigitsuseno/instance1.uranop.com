<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Sync\Traits\SyncTimestampable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeSalary extends Model
{
    use SoftDeletes, SyncTimestampable;

    protected $table = 'employee_salaries';

    protected $fillable = [
        'uuid',
        'employee_id',
        'base_salary',
        'premi',
        'tunjangan',
        'previous_basic_salary',
        'allowance_transport',
        'allowance_meal',
        'allowance_position',
        'effective_date',
        'end_date',
        'change_type',
        'letter_number',
        'reason',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'base_salary'           => 'decimal:2',
        'premi'                 => 'decimal:2',
        'tunjangan'             => 'decimal:2',
        'previous_basic_salary' => 'decimal:2',
        'allowance_transport'   => 'decimal:2',
        'allowance_meal'        => 'decimal:2',
        'allowance_position'    => 'decimal:2',
        'effective_date'        => 'date',
        'end_date'              => 'date',
        'is_active'             => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function getTotalSalaryAttribute(): float
    {
        return (float) ($this->base_salary
            + $this->premi
            + $this->tunjangan
            + $this->allowance_transport
            + $this->allowance_meal
            + $this->allowance_position);
    }

    public function getChangeTypeLabelAttribute(): string
    {
        return match ($this->change_type) {
            'initial'    => 'Gaji Awal',
            'increase'   => 'Kenaikan Gaji',
            'decrease'   => 'Penurunan Gaji',
            'promotion'  => 'Kenaikan Jabatan',
            'demotion'   => 'Penurunan Jabatan',
            'adjustment' => 'Penyesuaian',
            default      => $this->change_type ?? '-',
        };
    }

    // ========== BOOT ==========

    protected static function booted(): void
    {
        static::bootTimestampable();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
