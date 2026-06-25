<?php

namespace App\Modules\Supervisor\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupervisorAttendance extends Model
{
    use SoftDeletes;

    protected $table = 'attendance_autologs';

    protected $fillable = [
        'company_id',
        'branch_id',
        'employee_id',
        'employee_shift_roster_id',
        'date',
        'check_in',
        'check_out',
        'check_in_log_id',
        'check_out_log_id',
        'import_batch',
        'status',
        'late_duration',
        'early_leave_duration',
        'lembur',
        'lembur_calc',
        'lm',
        'lm_calc',
        'deduct_attendance',
        'actual_in',
        'actual_out',
        'is_half_day',
        'holiday_overtime',
        'is_sun',
        'is_sat',
        'is_holiday',
        'is_manual_edit',
        'last_edited_at',
        'last_edited_by',
        'is_locked',
        'locked_at',
        'locked_by',
        'notes',
        'metadata',
        'scan_count',
        'is_leave',
        'leave_id',
        'deduct_day',
        'izin_duration',
        'sakit_duration',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'last_edited_at' => 'datetime',
        'locked_at' => 'datetime',
        'is_half_day' => 'boolean',
        'is_sun' => 'boolean',
        'is_holiday' => 'boolean',
        'is_leave' => 'boolean',
        'is_manual_edit' => 'boolean',
        'is_locked' => 'boolean',
        'metadata' => 'array',
        'scan_count' => 'integer',
        'late_duration' => 'integer',
        'early_leave_duration' => 'integer',
        'lembur' => 'integer',
        'lm' => 'integer',
        'deduct_attendance' => 'integer',
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function leave()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employeeShiftRoster()
    {
        return $this->belongsTo(EmployeeShiftRoster::class, 'employee_shift_roster_id');
    }

    public function lastEditedBy()
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
