<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Shared\Traits\HasAuditLog;

class PphConfig extends Model
{
    use HasAuditLog;
    // use SoftDeletes; // uncomment if table has softDeletes

    protected $guarded = ['id'];
}
