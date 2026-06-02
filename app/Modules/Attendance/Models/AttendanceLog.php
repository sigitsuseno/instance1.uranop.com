<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceLog extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_logs';

    protected $fillable = [
        'employee_id',
        'employee_code',
        'employee_name',
        'scan_datetime',
        'scan_type',
        'machine_sn',
        'machine_name',
        'verify_type',
        'pin',
        'is_processed',
        'processed_at',
        'processed_by',
        'import_batch',
        'source_file',
        'raw_data',
    ];

    protected $casts = [
        'scan_datetime' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean',
        'raw_data' => 'array',
    ];

    /**
     * Employee relation.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * User who processed this log.
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope: unprocessed logs.
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * Scope: logs within date range.
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('scan_datetime', [
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59',
        ]);
    }
}
