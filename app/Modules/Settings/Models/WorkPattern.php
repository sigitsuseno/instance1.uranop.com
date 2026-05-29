<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Modules\Shared\Traits\HasAuditLog;

class WorkPattern extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $guarded = ['id'];
}
