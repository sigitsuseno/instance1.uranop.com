<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_records';

    protected $fillable = [
        'uuid',
        'employee_id',
        'date',
        'shift_id',
        'work_pattern_id',
        'schedule_in',
        'schedule_out',
        'actual_in',
        'actual_out',
        'late_minutes',
        'early_minutes',
        'overtime_minutes',
        'status',
        'is_manual',
        'notes',
        'raw_data',
        'is_locked',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'actual_in' => 'datetime',
        'actual_out' => 'datetime',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'is_manual' => 'boolean',
        'is_locked' => 'boolean',
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE = 'late';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_OFF = 'off';
    public const STATUS_HOLIDAY = 'holiday';
    public const STATUS_PERMIT = 'permit';
    public const STATUS_SICK = 'sick';
    public const STATUS_HALF_DAY = 'half_day';

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeDateBetween($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    public function scopePresent($query)
    {
        return $query->whereIn('status', [self::STATUS_PRESENT, self::STATUS_LATE, self::STATUS_HALF_DAY]);
    }

    public function scopeAbsent($query)
    {
        return $query->where('status', self::STATUS_ABSENT);
    }
}
