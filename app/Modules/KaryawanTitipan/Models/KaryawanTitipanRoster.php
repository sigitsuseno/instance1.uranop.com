<?php

namespace App\Modules\KaryawanTitipan\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KaryawanTitipanRoster extends Model
{
    protected $table = 'karyawan_titipan_rosters';

    protected $fillable = [
        'karyawan_titipan_id',
        'date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function karyawanTitipan(): BelongsTo
    {
        return $this->belongsTo(KaryawanTitipan::class);
    }
}
