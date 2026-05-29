<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeRuleDetail extends Model
{
    protected $guarded = ['id'];

    public function rule()
    {
        return $this->belongsTo(OvertimeRule::class, 'overtime_rule_id');
    }
}
