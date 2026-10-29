<?php

namespace App\Modules\Supervisor\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasSearch;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SpvRequestCuti extends Model
{
    use HasAuditLog, HasSearch, HasUserContext, SoftDeletes;

    protected $table = 'spv_request_cuti';

    protected $fillable = [
        'uuid',
        'employee_id',
        'leave_type_id',
        'leave_period_id',
        'start_date',
        'end_date',
        'days_requested',
        'sisa_cuti',
        'reason',
        'note',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected $searchable = [
        'reason',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'note' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leavePeriod()
    {
        return $this->belongsTo(LeavePeriod::class, 'leave_period_id');
    }
}
