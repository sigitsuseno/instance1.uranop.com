<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Schedule\Enums\ShiftCode;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeShiftRoster extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $table = 'sch_employee_shift_rosters';

    protected $fillable = [
        'uuid',
        'employee_id',
        'shift_id',
        'work_pattern_id',
        'date',
        'shift_code',
        'work_pattern_type',
        'external_code',
        'is_holiday',
        'is_sat',
        'is_sun',
        'is_half_day',
        'is_leave',
        'is_permit',
        'leave_id',
        'status',
        'source',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'is_holiday' => 'boolean',
        'is_sat' => 'boolean',
        'is_sun' => 'boolean',
        'is_half_day' => 'boolean',
        'is_leave' => 'boolean',
        'is_permit' => 'boolean',
        'metadata' => 'array',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

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

    public function prepare()
    {
        return $this->hasOne(AttendancePrepare::class, 'employee_shift_roster_id');
    }

    public function leave()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_id');
    }

    public function getShiftCodeAttribute()
    {
        return $this->shift?->code ?? $this->attributes['shift_code'] ?? null;
    }

    public function getEffectiveShiftAttribute()
    {
        $shift = $this->shift;

        if ($this->is_sun && $shift && ! $this->is_holiday) {
            $sundayShift = Shift::where('is_minggu_lembur', true)
                ->where('work_pattern_id', $shift->work_pattern_id)
                ->first();

            return $sundayShift ?? $shift;
        }

        return $shift;
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }
}
