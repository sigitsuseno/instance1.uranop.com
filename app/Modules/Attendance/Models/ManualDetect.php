<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManualDetect extends Model
{
    protected $table = 'att_manual_detect';

    protected $fillable = [
        'uuid',
        'employee_id',
        'date',
        'roster_id',
        'work_pattern_id',
        'work_pattern_type',
        'shift_id',
        'check_in',
        'check_out',
        'time_scan_result',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'              => 'date:Y-m-d',
        'check_in'          => 'datetime',
        'check_out'         => 'datetime',
        'time_scan_result'  => 'array',
    ];

    // ─── Status Constants ─────────────────────────────────────────

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_PERHATIAN = 'perhatian';
    public const STATUS_LENGKAP   = 'lengkap';

    // ─── Boot ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relations ─────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function roster()
    {
        return $this->belongsTo(EmployeeShiftRoster::class, 'roster_id');
    }

    public function workPattern()
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePerhatian($query)
    {
        return $query->where('status', self::STATUS_PERHATIAN);
    }

    public function scopeLengkap($query)
    {
        return $query->where('status', self::STATUS_LENGKAP);
    }
}
