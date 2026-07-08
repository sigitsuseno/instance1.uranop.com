<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceConfig extends Model
{
    protected $table = 'attendance_configs';

    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AttendanceConfig $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────

    public function updatedBy()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'updated_by');
    }

    // ─── Scopes ────────────────────────────────────────────

    public function scopeByPage($query, string $page)
    {
        return $query->where('page', $page);
    }

    // ─── Helpers ───────────────────────────────────────────

    /**
     * Get or create config for a page with defaults merged.
     */
    public static function forPage(string $page, array $defaults = []): array
    {
        $config = static::where('page', $page)->first();

        if (!$config || !$config->config) {
            return $defaults;
        }

        return array_merge($defaults, $config->config);
    }

    /**
     * Save config for a page (upsert).
     */
    public static function saveForPage(string $page, array $config, ?int $userId = null): self
    {
        $model = static::firstOrNew(['page' => $page]);
        $model->config = $config;

        if ($userId) {
            $model->updated_by = $userId;
        }

        $model->save();

        return $model;
    }
}
