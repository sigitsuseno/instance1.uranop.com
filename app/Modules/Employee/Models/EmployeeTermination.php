<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Sync\Traits\SyncTimestampable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeTermination extends Model
{
    use SoftDeletes, SyncTimestampable;

    protected $table = 'employee_terminations';

    protected $fillable = [
        'uuid',
        'employee_id',
        'termination_type',
        'termination_date',
        'effective_date',
        'reason',
        'settlement_amount',
        'settlement_notes',
        'is_eligible_for_rehire',
        'clearance_asset',
        'clearance_finance',
        'clearance_it',
        'clearance_notes',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'document_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'termination_date'      => 'date',
        'effective_date'        => 'date',
        'settlement_amount'     => 'decimal:2',
        'is_eligible_for_rehire'=> 'boolean',
        'clearance_asset'       => 'boolean',
        'clearance_finance'     => 'boolean',
        'clearance_it'          => 'boolean',
        'approved_at'           => 'datetime',
    ];

    protected $attributes = [
        'approval_status'       => 'pending',
        'is_eligible_for_rehire'=> false,
    ];

    // ========== RELATIONSHIPS ==========

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ========== SCOPES ==========

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    // ========== ACCESSORS ==========

    public function getTerminationTypeLabelAttribute(): string
    {
        return match ($this->termination_type) {
            'resign'       => 'Resign',
            'retirement'   => 'Pensiun',
            'fired'        => 'PHK',
            'contract_end' => 'Kontrak Habis',
            'death'        => 'Meninggal',
            default        => 'Lainnya',
        };
    }

    public function getClearanceCompleteAttribute(): bool
    {
        return $this->clearance_asset && $this->clearance_finance && $this->clearance_it;
    }

    // ========== BOOT ==========

    protected static function booted(): void
    {
        static::bootTimestampable();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::updated(function (EmployeeTermination $termination) {
            // Update employee status saat terminasi disetujui
            if ($termination->wasChanged('approval_status') && $termination->approval_status === 'approved') {
                $termination->employee()->update([
                    'is_active'   => false,
                    'resign_date' => $termination->effective_date,
                    'end_date'    => $termination->effective_date,
                ]);
            }
        });
    }
}
