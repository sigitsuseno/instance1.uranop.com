<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class ConsecutiveDay extends Model
{
    protected $table = 'att_consecutive_days';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'consecutive_count',
        'break_reason',
        'alert_triggered',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'consecutive_count' => 'integer',
        'alert_triggered' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
