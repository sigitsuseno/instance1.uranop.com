<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayrollConfig extends Model
{

    protected $table = 'payroll_configs';

    protected $fillable = [
        'config_type',
        'config',
        'updated_by',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('config_type', $type);
    }

    /**
     * Default config per config_type
     */
    public static function getDefault(string $type): array
    {
        return match ($type) {
            'gaji_karyawan' => [
                'sections' => [
                    'A' => ['GRP-ALLIN', 'GRP-SPR'],
                    'B' => ['GRP-GD', 'GRP-SS', 'GRP-PS1'],
                ],
            ],
            'attendance_overtime_setting' => [
                'formulas' => [
                    'FIXED' => 'rumus_1',
                    'FLEX_S' => 'rumus_1',
                    'FLEX_P' => 'rumus_1'
                ],
                'special_employees' => [
                    'ids' => [],
                    'formula' => 'rumus_1'
                ],
                'technician_rule' => [
                    'employee_ids' => [31, 115, 174],
                    'start_date' => '2026-06-01',
                    'max_holiday_minutes' => 1200
                ],
                'zero_late_shift_codes' => ['S', 'P'],
                'work_hours' => [
                    'FIXED' => ['weekday' => 540, 'saturday' => 360],
                    'FLEX-SHIFT' => ['weekday' => 480, 'saturday' => 360],
                    'SHIFT' => ['weekday' => 480, 'saturday' => 360],
                ]
            ],
            'kasbon' => [
                'limit_type' => 'salary_multiplier',
                'limit_value' => 3,
                'max_tenor' => 12,
                'interest_rate' => 0,
                'allow_multi' => false,
                'auto_deduct' => true,
                'approval_roles' => ['superadmin', 'hrmanager'],
            ],
            default => [],
        };
    }

    /**
     * Get config (with fallback to default)
     */
    public static function getConfig(string $type): array
    {
        $row = static::byType($type)->first();
        return $row?->config ?? static::getDefault($type);
    }
}
