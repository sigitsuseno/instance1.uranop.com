<?php

namespace App\Modules\Instance\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Instance extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'slug',
        'domain',
        'database_name',
        'is_active',
        'address',
        'phone',
        'email',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

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
