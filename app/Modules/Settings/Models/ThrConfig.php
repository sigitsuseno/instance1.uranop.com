<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ThrConfig extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    
    protected $casts = [
        'is_prorated' => 'boolean',
        'is_active' => 'boolean',
        'percentage' => 'decimal:2',
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
