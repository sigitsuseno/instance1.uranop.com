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
        'comp_group',
        'pot_admin',
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
        'pot_admin'            => 'decimal:2',
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

    /**
     * Masa kerja efektif (bulan penuh, dibulatkan ke bawah) yang dipakai
     * untuk menghitung uang kompensasi PKWT.
     *
     * Dihitung dari start_date kontrak sampai tanggal berhenti efektif:
     * end_date kontrak, atau lebih awal bila karyawan resign/berhenti
     * (employees.resign_date / employees.end_date) sebelum kontrak selesai.
     * Nilai terkecil 1 bulan, mengikuti konvensi duration_months.
     */
    public function compensationMonths(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return max(1, (int) ($this->duration_months ?? 0));
        }

        $effectiveEnd = $this->end_date;

        $exitDate = $this->employee?->resign_date ?? $this->employee?->end_date;
        if ($exitDate && $exitDate->lt($effectiveEnd)) {
            $effectiveEnd = $exitDate;
        }

        if ($effectiveEnd->lt($this->start_date)) {
            return 0;
        }

        return max(1, (int) $this->start_date->diffInMonths($effectiveEnd));
    }

    /**
     * Filter kontrak yang uang kompensasinya jatuh pada periode tertentu.
     *
     * Memakai tanggal berhenti efektif: end_date kontrak, atau lebih awal bila
     * karyawan resign/berhenti (employees.resign_date / employees.end_date)
     * sebelum kontrak selesai. Contoh: kontrak berakhir 1 Nov tetapi resign
     * 31 Okt → masuk periode yang memuat 31 Okt (mis. 25 Okt s/d 24 Nov).
     */
    public function scopeDueInCompensationPeriod($query, $start, $end)
    {
        return $query->whereRaw(
            'LEAST(
                employee_contracts.end_date,
                COALESCE(
                    (SELECT COALESCE(e.resign_date, e.end_date) FROM employees e WHERE e.id = employee_contracts.employee_id),
                    employee_contracts.end_date
                )
            ) BETWEEN ? AND ?',
            [$start, $end]
        );
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
