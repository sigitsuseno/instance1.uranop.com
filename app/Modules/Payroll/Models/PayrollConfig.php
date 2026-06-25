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
