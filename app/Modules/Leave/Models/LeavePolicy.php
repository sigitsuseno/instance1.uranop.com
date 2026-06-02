<?php

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;

class LeavePolicy extends Model
{
    use SoftDeletes, HasAuditLog, HasUserContext;

    protected $fillable = [
        'leave_type_id',
        'name',
        'description',
        'requires_one_year_service',
        'can_carry_forward',
        'max_carry_forward_days',
        'entitlement_days',
    ];

    protected function casts(): array
    {
        return [
            'requires_one_year_service' => 'boolean',
            'can_carry_forward' => 'boolean',
            'max_carry_forward_days' => 'integer',
            'entitlement_days' => 'integer',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
