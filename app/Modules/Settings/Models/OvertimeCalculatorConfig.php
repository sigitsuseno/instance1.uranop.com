<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeCalculatorConfig extends Model
{
    protected $table = 'attendance_calculator_configs';

    protected $guarded = ['id'];

    protected $casts = [
        'normal_work_minutes'     => 'integer',
        'saturday_work_minutes'   => 'integer',
        'holiday_max_minutes'     => 'integer',
        'shift_saturday_flat'     => 'integer',
        'late_deducts_overtime'   => 'boolean',
        'late_tolerance'          => 'integer',
        'lm_rest_deduction'       => 'integer',
        'rounding_interval'       => 'integer',
        'rounding_threshold'      => 'integer',
        'hourly_divisor'          => 'integer',
        'is_active'               => 'boolean',
    ];

    /**
     * Lookup config: cari spesifik work_pattern_id dulu, fallback ke global.
     * Kalau tidak ada sama sekali, return instance kosong (pakai default).
     */
    public static function forPattern(?int $workPatternId): self
    {
        // Cari override spesifik
        $config = static::where('is_active', true)
            ->where('work_pattern_id', $workPatternId)
            ->first();

        // Fallback ke global
        if (! $config) {
            $config = static::where('is_active', true)
                ->whereNull('work_pattern_id')
                ->first();
        }

        // Kalau bener-bener kosong, return empty model (nilai default dari DB)
        return $config ?? new static();
    }

    public function workPattern()
    {
        return $this->belongsTo(\App\Modules\Schedule\Models\WorkPattern::class, 'work_pattern_id');
    }
}
