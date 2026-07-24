<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Sync\Traits\SyncTimestampable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeContract extends Model
{
    use HasAuditLog, HasFactory, SoftDeletes, SyncTimestampable;

    protected $table = 'employee_contracts';

    protected $fillable = [
        'uuid',
        'employee_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'duration_months',
        'document_path',
        'notes',
        'version',
        'is_latest',
        'status',
        'expiry_notified_at',
        'compensation_paid_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date'           => 'date',
        'end_date'             => 'date',
        'is_latest'            => 'boolean',
        'duration_months'      => 'integer',
        'version'              => 'integer',
        'expiry_notified_at'   => 'datetime',
        'compensation_paid_at' => 'datetime',
    ];

    protected $attributes = [
        'status'    => 'draft',
        'is_latest' => true,
        'version'   => 1,
    ];

    // ========== RELATIONSHIPS ==========

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLatestOnly($query)
    {
        return $query->where('is_latest', true);
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<=', now()->addDays($days))
            ->whereDate('end_date', '>', now());
    }

    // ========== ACCESSORS ==========

    public function getContractTypeLabelAttribute(): string
    {
        return match ($this->contract_type) {
            'pkwt'        => 'PKWT',
            'pkwtt'       => 'PKWTT',
            'outsourcing' => 'Outsourcing',
            'freelance'   => 'Freelance',
            default       => $this->contract_type,
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'      => 'Draft',
            'active'     => 'Aktif',
            'expired'    => 'Kadaluarsa',
            'terminated' => 'Dihentikan',
            default      => $this->status,
        };
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (! $this->end_date || $this->status !== 'active') {
            return false;
        }

        $daysLeft = now()->diffInDays($this->end_date, false);

        return $daysLeft > 0 && $daysLeft <= 30;
    }

    public function getDaysLeftAttribute(): ?int
    {
        if (! $this->end_date || $this->status !== 'active') {
            return null;
        }

        return (int) now()->diffInDays($this->end_date, false);
    }

    public function getIsCompensationPaidAttribute(): bool
    {
        return $this->compensation_paid_at !== null;
    }

    // ========== BOOT ==========

    protected static function booted(): void
    {
        static::bootTimestampable();

        static::creating(function (EmployeeContract $contract) {
            if (empty($contract->uuid)) {
                $contract->uuid = (string) Str::uuid();
            }
            if ($contract->start_date && $contract->end_date) {
                $contract->duration_months = $contract->start_date->diffInMonths($contract->end_date);
            }
        });

        static::created(function (EmployeeContract $contract) {
            // Set kontrak sebelumnya tidak latest
            if ($contract->is_latest) {
                static::where('employee_id', $contract->employee_id)
                    ->where('id', '!=', $contract->id)
                    ->update(['is_latest' => false]);
            }
        });

        static::updating(function (EmployeeContract $contract) {
            if ($contract->isDirty(['start_date', 'end_date'])) {
                if ($contract->start_date && $contract->end_date) {
                    $contract->duration_months = $contract->start_date->diffInMonths($contract->end_date);
                }
            }
        });
    }
}
