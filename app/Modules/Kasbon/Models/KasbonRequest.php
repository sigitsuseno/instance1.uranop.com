<?php

namespace App\Modules\Kasbon\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use App\Modules\Shared\Traits\HasSearch;

class KasbonRequest extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext, HasSearch;

    protected $fillable = [
        'uuid',
        'employee_id',
        'amount',
        'tenor',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'disbursed_at',
        'remaining_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $searchable = [
        'reason',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
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

    // --- Relations ---

    public function employee()
    {
        return $this->belongsTo(\App\Modules\Employee\Models\Employee::class, 'employee_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Modules\Auth\Models\User::class, 'created_by');
    }

    public function installments()
    {
        return $this->hasMany(KasbonInstallment::class, 'kasbon_request_id');
    }

    // --- Scopes ---

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['approved', 'disbursed'])->where('remaining_amount', '>', 0);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // --- Helpers ---

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'pending';
    }

    public function hasOutstanding(): bool
    {
        return $this->remaining_amount > 0;
    }
}
