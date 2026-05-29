<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeGroupCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'description',
        'is_multiple_choice',
        'is_active',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'is_multiple_choice' => 'boolean',
        'is_active' => 'boolean',
        'synced_at' => 'datetime',
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

    public function groups()
    {
        return $this->hasMany(EmployeeGroup::class, 'category_id');
    }
}
