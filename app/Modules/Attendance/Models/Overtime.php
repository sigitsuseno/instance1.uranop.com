<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Overtime extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_overtimes';

    protected $fillable = [
        'uuid',
        'employee_id',
        'date',
        'overtime_rule_id',
        'start_time',
        'end_time',
        'total_hours',
        'multiplier',
        'calculated_hours',
        'status',
        'approved_by',
        'approved_at',
        'reject_reason',
        'notes',
        'raw_data',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_hours' => 'float',
        'multiplier' => 'float',
        'calculated_hours' => 'float',
        'approved_at' => 'datetime',
        'raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function overtimeRule()
    {
        return $this->belongsTo(OvertimeRule::class, 'overtime_rule_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeDateBetween($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
