<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExtraEmployee extends Model
{
    protected $table = 'extra_employees';

    protected $fillable = [
        'uuid',
        'nama',
        'kode',
        'komponen_gaji',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'komponen_gaji' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (empty($model->komponen_gaji)) {
                $model->komponen_gaji = self::defaultKomponenGaji();
            }

            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public static function defaultKomponenGaji(): array
    {
        return [
            'gaji_pokok'   => 0,
            'premi'        => 0,
            'tj_mk'        => 0,
            'tunjangan'    => 0,
            'ttl_bpjs'     => 0,
            'ttl_pph'      => 0,
            'total_gaji'   => 0,
            'cashbon'      => 0,
            'total_terima' => 0,
        ];
    }
}
