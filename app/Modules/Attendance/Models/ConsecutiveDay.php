<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ConsecutiveDay extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_consecutive_days';

    protected $fillable = [
        'uuid',
        'employee_id',
        'start_date',
        'end_date',
        'total_days',
        'type',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'total_days' => 'integer',
    ];

    // ─── Type Constants ────────────────────────────────────────────

    public const TYPE_WORKED = 'worked';
    public const TYPE_ABSENT = 'absent';

    // ─── Status Constants ──────────────────────────────────────────

    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_REVIEWED   = 'reviewed';
    public const STATUS_FLAGGED    = 'flagged';

    // ─── Boot ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (ConsecutiveDay $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // ─── Scopes ────────────────────────────────────────────────────

    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            // Streak yang overlap dengan periode
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        });
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
