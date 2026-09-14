<?php

namespace App\Modules\Organization\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes, HasAuditLog;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'pic_name',
        'nama_pimpinan',
        'ttd_pimpinan',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
