<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceAutolog extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_autologs';

    protected $fillable = [
        'employee_id',
        'date',
        'first_scan',
        'last_scan',
        'total_scans',
        'status',
        'late_minutes',
        'early_minutes',
        'overtime_minutes',
        'scan_data',
        'source',
        'notes',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'first_scan' => 'datetime',
        'last_scan' => 'datetime',
        'total_scans' => 'integer',
        'late_minutes' => 'integer',
        'early_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'scan_data' => 'array',
        'synced_at' => 'datetime',
    ];

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
}
