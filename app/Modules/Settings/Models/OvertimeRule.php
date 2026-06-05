<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Shared\Traits\HasAuditLog;

class OvertimeRule extends Model
{
    use HasAuditLog;
    // use SoftDeletes; // uncomment if table has softDeletes

    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(OvertimeRuleDetail::class, 'overtime_rule_id')->orderBy('hour');
    }

    public function workPattern()
    {
        return $this->belongsTo(\App\Modules\Schedule\Models\WorkPattern::class, 'work_pattern_id');
    }
}
