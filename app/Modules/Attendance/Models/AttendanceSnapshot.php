<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSnapshot extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_snapshots';

    protected $fillable = [
        'uuid',
        'period',
        'employee_id',
        'snapshot_data',
        'context',
        'notes',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'synced_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    public function scopeContext($query, string $context)
    {
        return $query->where('context', $context);
    }
}
