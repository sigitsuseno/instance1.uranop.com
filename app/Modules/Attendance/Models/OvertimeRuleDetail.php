<?php

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRuleDetail extends Model
{
    protected $table = 'att_overtime_rule_details';

    protected $fillable = [
        'uuid',
        'overtime_rule_id',
        'hour',
        'multiplier',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'hour' => 'integer',
        'multiplier' => 'float',
        'synced_at' => 'datetime',
    ];

    /**
     * Parent overtime rule.
     */
    public function overtimeRule()
    {
        return $this->belongsTo(OvertimeRule::class, 'overtime_rule_id');
    }
}
