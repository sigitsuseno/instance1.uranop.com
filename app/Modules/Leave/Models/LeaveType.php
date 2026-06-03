<?php

namespace App\Modules\Leave\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use App\Modules\Shared\Traits\HasSearch;

class LeaveType extends Model
{
    use SoftDeletes, HasAuditLog, HasUserContext, HasSearch;

    protected $fillable = [
        'code',
        'name',
        'category',
        'balance_type',
        'is_paid',
        'max_days',
        'affects_daily_worker',
        'affects_monthly_worker',
        'requires_medical_doc',
        'color_hex',
        'is_active',
    ];

    protected $searchableFields = ['code', 'name', 'category'];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'max_days' => 'integer',
            'affects_daily_worker' => 'boolean',
            'affects_monthly_worker' => 'boolean',
            'requires_medical_doc' => 'boolean',
            'is_active' => 'boolean',
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
