<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkPattern extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $table = 'sch_work_patterns';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'employee_type',
        'cut_off_date',
        'sun_overtime',
        'work_day',
        'sat_type',
        'is_half_day_all',
        'work_day_hours',
        'half_day_hours',
        'wd_rest_hours',
        'hd_rest_hours',
        'is_active',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'employee_type' => 'string',
        'cut_off_date' => 'integer',
        'sun_overtime' => 'boolean',
        'work_day' => 'integer',
        'is_half_day_all' => 'boolean',
        'work_day_hours' => 'integer',
        'half_day_hours' => 'integer',
        'wd_rest_hours' => 'integer',
        'hd_rest_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class, 'work_pattern_id');
    }

    public function details()
    {
        return $this->hasMany(WorkPatternDetail::class, 'work_pattern_id');
    }

    public function workPatternType()
    {
        return $this->belongsTo(WorkPatternType::class, 'employee_type', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEmployeeType($query, $type)
    {
        return $query->where('employee_type', $type);
    }
}
