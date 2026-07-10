<?php

namespace App\Modules\Supervisor\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupervisorEmployeeGroup extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'supervisor_employee_groups';

    protected $fillable = [
        'uuid',
        'employee_id',
        'group_name',
        'group_code',
        'group_component',
        'period_start',
        'period_end',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'group_component' => 'array',
        'period_start'    => 'date',
        'period_end'      => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function (self $model) {
            if (empty($model->updated_by) && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    // ========== RELATIONSHIPS ==========

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // ========== SCOPES ==========

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->where('period_start', $startDate)
            ->where('period_end', $endDate);
    }

    public function scopeForGroup($query, string $groupCode)
    {
        return $query->where('group_code', $groupCode);
    }
}
