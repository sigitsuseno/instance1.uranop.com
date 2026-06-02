<?php

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use App\Modules\Shared\Traits\HasStatus;

class LeavePeriod extends Model
{
    use SoftDeletes, HasAuditLog, HasUserContext, HasStatus;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_carry_forward',
        'is_generated',
        'status', // active, recap, closed
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_carry_forward' => 'boolean',
            'is_generated' => 'boolean',
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
}
