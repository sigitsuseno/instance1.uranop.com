<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OvertimeRule extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'att_overtime_rules';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'is_active',
        'is_holiday',
        'work_pattern_id',
        'description',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_holiday' => 'boolean',
        'synced_at' => 'datetime',
    ];

    /**
     * Overtime rule detail records.
     */
    public function details()
    {
        return $this->hasMany(OvertimeRuleDetail::class, 'overtime_rule_id');
    }

    /**
     * Get multiplier for given hour.
     */
    public function getMultiplier(int $hour): float
    {
        $detail = $this->details()
            ->where('hour', '<=', $hour)
            ->orderBy('hour', 'desc')
            ->first();

        return $detail ? (float) $detail->multiplier : 1.0;
    }
}
