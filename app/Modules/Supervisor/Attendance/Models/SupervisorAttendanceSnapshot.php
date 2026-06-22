<?php

namespace App\Modules\Supervisor\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisorAttendanceSnapshot extends Model
{
    use SoftDeletes;

    protected $table = 'supervisor_attendance_snapshots';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'period_code',
        'period_start',
        'period_end',
        'total_working_days',
        'total_present_days',
        'total_absent_days',
        'total_late_days',
        'total_late_minutes',
        'total_early_leave_minutes',
        'total_overtime_minutes',
        'total_holiday_overtime',
        'overtime_breakdown',
        'total_leave_days',
        'total_unpaid_days',
        'total_sick_days',
        'total_permit_days',
        'snapshot',
        'status',
        'is_locked',
        'locked_by',
        'locked_at',
        'payroll_id',
        'payroll_period_id',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_working_days' => 'integer',
        'total_present_days' => 'integer',
        'total_absent_days' => 'integer',
        'total_late_days' => 'integer',
        'total_late_minutes' => 'integer',
        'total_early_leave_minutes' => 'integer',
        'total_overtime_minutes' => 'integer',
        'total_holiday_overtime' => 'integer',
        'total_leave_days' => 'integer',
        'total_unpaid_days' => 'integer',
        'total_sick_days' => 'integer',
        'total_permit_days' => 'integer',
        'overtime_breakdown' => 'array',
        'snapshot' => 'array',
        'metadata' => 'array',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== SCOPES ==========

    public function scopeByPeriod($query, $periodCode)
    {
        return $query->where('period_code', $periodCode);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('is_locked', false);
    }

    public function scopeByPayrollPeriod($query, $payrollPeriodId)
    {
        return $query->where('payroll_period_id', $payrollPeriodId);
    }
}
