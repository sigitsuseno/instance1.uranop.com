<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;

class SalaryGrade extends Model
{
    use HasAuditLog;
    // use SoftDeletes; // uncomment if table has softDeletes

    protected $guarded = ['id'];

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
