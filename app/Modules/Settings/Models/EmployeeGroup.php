<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Support\Str;
use App\Modules\Employee\Models\Employee;

class EmployeeGroup extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'uuid',
        'employee_id',
        'reference_code',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
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

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function master()
    {
        return $this->belongsTo(EmployeeGroupMaster::class, 'reference_code', 'code');
    }
}
