<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Support\Str;

class EmployeeGroup extends Model
{
    use HasAuditLog;
    // use SoftDeletes; // uncomment if table has softDeletes

    protected $fillable = [
        'uuid',
        'category_id',
        'name',
        'code',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
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

    public function category()
    {
        return $this->belongsTo(EmployeeGroupCategory::class, 'category_id');
    }
}
