<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReportConfig extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'employee_groups' => 'array',
        'config'          => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ReportConfig $model) {
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

    public function scopeByType($query, string $reportType)
    {
        return $query->where('report_type', $reportType);
    }
}
