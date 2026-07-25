<?php

namespace App\Modules\KaryawanTitipan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KaryawanTitipan extends Model
{
    protected $table = 'karyawan_titipan';

    protected $fillable = [
        'uuid',
        'nama',
        'employee_code',
        'start_date',
        'end_date',
        'status',
        'component',
    ];

    protected $casts = [
        'uuid'       => 'string',
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'component'  => 'array',
        'status'     => 'string',
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
