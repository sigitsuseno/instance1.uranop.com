<?php

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use App\Modules\Shared\Traits\HasSearch;

class EmployeeLeave extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext, HasSearch;

    protected $fillable = [
        'uuid',
        'employee_id',
        'leave_type_id',
        'leave_period_id',
        'reference_id',
        'transaction_type',
        'amount',
        'description',
        'created_by',
        'updated_by'
    ];

    protected $searchable = [
        'description',
        'transaction_type'
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
        return $this->belongsTo(LeavePeriod::class);
    }
}
