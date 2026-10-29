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

class SpvLedgerCuti extends Model
{
    use HasAuditLog, HasSearch, HasUserContext, SoftDeletes;

    protected $table = 'spv_ledger_cuti';

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
        'updated_by',
    ];

    protected $searchable = [
        'description',
        'transaction_type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
        return $this->belongsTo(LeavePeriod::class);
    }
}
