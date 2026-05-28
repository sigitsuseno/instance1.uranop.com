<?php

namespace App\Modules\Organization\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasHierarchy;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasHierarchy, HasUserContext;

    protected $fillable = [
        'uuid',
        'parent_id',
        'code',
        'name',
        'description',
        'manager_id',
        'cost_center',
        'is_active',
        'level',
        'path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function manager()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'manager_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }
}
