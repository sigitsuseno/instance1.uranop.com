<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSummary extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_summaries';

    protected $fillable = [
        'employee_id',
        'period_start',
        'period_end',
        'total_days',
        'present_days',
        'late_days',
        'absent_days',
        'off_days',
        'leave_days',
        'holiday_days',
        'overtime_hours',
        'total_late_minutes',
        'summary_data',
        'is_locked',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_days' => 'integer',
        'present_days' => 'integer',
        'late_days' => 'integer',
        'absent_days' => 'integer',
        'off_days' => 'integer',
        'leave_days' => 'integer',
        'holiday_days' => 'integer',
        'overtime_hours' => 'float',
        'total_late_minutes' => 'integer',
        'summary_data' => 'array',
        'is_locked' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start);
    }
}
