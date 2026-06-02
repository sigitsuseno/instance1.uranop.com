<?php

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use App\Modules\Shared\Traits\HasSearch;

class LeaveRequest extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext, HasSearch;

    protected $fillable = [
        'uuid',
        'employee_id',
        'leave_type_id',
        'leave_period_id',
        'start_date',
        'end_date',
        'days_requested',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by'
    ];

    protected $searchable = [
        'reason',
        'status',
        'rejection_reason'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Modules\Employee\Models\Employee::class, 'employee_id');
    }

    public function leavePeriod()
    {
        return $this->belongsTo(LeavePeriod::class, 'leave_period_id');
    }

    public function documents()
    {
        return $this->hasMany(LeaveDocument::class);
    }
}
