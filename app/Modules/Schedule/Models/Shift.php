<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $table = 'sch_shifts';

    protected $fillable = [
        'uuid',
        'work_pattern_id',
        'code',
        'name',
        'external_code',
        'work_hour_start',
        'work_hour_end',
        'shift_checkin_options',
        'check_in_start',
        'check_in_end',
        'check_out_start',
        'check_out_end',
        'is_overnight',
        'check_out_overnight_start',
        'check_out_overnight_end',
        'tolerance_minutes',
        'min_work_hours',
        'has_overtime',
        'overtime_multiplier',
        'is_weekend',
        'is_dayoff',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'is_overnight' => 'boolean',
        'has_overtime' => 'boolean',
        'is_dayoff' => 'boolean',
        'is_weekend' => 'boolean',
        'is_active' => 'boolean',
        'tolerance_minutes' => 'integer',
        'min_work_hours' => 'integer',
        'overtime_multiplier' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function workPattern()
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotDayoff($query)
    {
        return $query->where('is_dayoff', false);
    }

    public function scopeByExternalCode($query, $code)
    {
        return $query->where('external_code', $code);
    }
}
