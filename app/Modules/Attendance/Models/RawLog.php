<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;

class RawLog extends Model
{
    protected $table = 'att_raw_logs';

    protected $fillable = [
        'pin',
        'employee_code',
        'employee_name',
        'scan_datetime',
        'scan_type',
        'machine_sn',
        'machine_name',
        'verify_type',
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

    /**
     * User who processed this log.
     */
    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
