<?php

namespace App\Modules\Organization\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes, HasAuditLog;

    protected $fillable = [
        'name',
        'logo_path',
        'npwp',
        'address',
        'phone',
        'email',
        'website',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }
}
