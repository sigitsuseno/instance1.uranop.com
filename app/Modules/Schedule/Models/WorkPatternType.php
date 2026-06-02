<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkPatternType extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $table = 'sch_work_pattern_types';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'label',
        'keterangan',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
