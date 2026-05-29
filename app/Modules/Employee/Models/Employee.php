<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Shared\Traits\HasAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Employee extends Model
{
    use HasAuditLog, HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'uuid',
        'user_id',
        'employee_group_id',
        'department_id',
        'position_id',
        'employee_code',
        'nip',
        'nik',
        'name',
        'photo',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'religion',
        'blood_type',
        'email',
        'phone',
        'address',
        'postal_code',
        'npwp',
        'bpjs_ketenagakerjaan',
        'bpjs_kesehatan',
        'has_npwp',
        'ptkp',
        'employment_status',
        'payroll_cycle',
        'join_date',
        'end_date',
        'permanent_date',
        'resign_date',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'base_salary',
        'premi',
        'tunjangan',
        'is_active',
        'synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth'  => 'date',
        'join_date'      => 'date',
        'end_date'       => 'date',
        'permanent_date' => 'date',
        'resign_date'    => 'date',
        'is_active'      => 'boolean',
        'has_npwp'       => 'boolean',
        'base_salary'    => 'decimal:2',
        'premi'          => 'decimal:2',
        'tunjangan'      => 'decimal:2',
        'synced_at'      => 'datetime',
    ];

    protected $appends = ['photo_url'];

    // ========== BOOT ==========

    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            if (empty($employee->uuid)) {
                $employee->uuid = (string) Str::uuid();
            }
        });
    }

    // ========== RELATIONSHIPS ==========

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(EmployeeGroup::class, 'employee_group_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function contracts()
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function latestContract()
    {
        return $this->hasOne(EmployeeContract::class)->where('is_latest', true);
    }

    public function families()
    {
        return $this->hasMany(EmployeeFamily::class);
    }

    public function dependents()
    {
        return $this->hasMany(EmployeeFamily::class)->where('is_dependent', true);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function salaries()
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    public function salaryComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function positionHistories()
    {
        return $this->hasMany(EmployeePositionHistory::class);
    }

    public function terminations()
    {
        return $this->hasMany(EmployeeTermination::class);
    }

    public function latestTermination()
    {
        return $this->hasOne(EmployeeTermination::class)->latestOfMany();
    }

    public function bpjs()
    {
        return $this->hasOne(EmployeeBpjs::class);
    }

    public function thr()
    {
        return $this->hasMany(EmployeeThr::class);
    }

    // ========== EFFECTIVE DATE PATTERN — METHODS ==========

    /**
     * Ambil gaji pokok per periode.
     * Contoh: $employee->baseSalary()        → gaji bulan ini
     *         $employee->baseSalary('2026-05') → gaji Mei 2026
     */
    public function baseSalary(?string $period = null): float
    {
        $date = $period
            ? Carbon::parse($period.'-01')->endOfMonth()
            : now();

        return (float) ($this->salaries()
            ->where('effective_date', '<=', $date)
            ->where('is_active', true)
            ->latest('effective_date')
            ->value('base_salary') ?? $this->base_salary ?? 0);
    }

    /**
     * Ambil komponen gaji aktif terbaru.
     * Dipakai oleh method-method di bawah.
     */
    public function activeSalaryComponent(?string $period = null): ?EmployeeSalaryComponent
    {
        $query = $this->salaryComponents()->where('is_active', true);

        if ($period) {
            $date = Carbon::parse($period.'-01')->endOfMonth();
            $query->where('effective_date', '<=', $date);
        }

        return $query->latest('effective_date')->first();
    }

    /**
     * Ambil gaji pokok dari employee_salary_components.
     * $employee->gaji_pokok()        → komponen aktif saat ini
     * $employee->gaji_pokok('2026-05') → komponen Mei 2026
     */
    public function gaji_pokok(?string $period = null): float
    {
        return (float) ($this->activeSalaryComponent($period)?->gaji_pokok ?? $this->base_salary ?? 0);
    }

    /**
     * Ambil premi dari employee_salary_components.
     */
    public function premi_component(?string $period = null): float
    {
        return (float) ($this->activeSalaryComponent($period)?->premi ?? $this->premi ?? 0);
    }

    /**
     * Ambil tunjangan masa kerja dari employee_salary_components.
     */
    public function tunjangan_masa_kerja(?string $period = null): float
    {
        return (float) ($this->activeSalaryComponent($period)?->tunjangan_masa_kerja ?? 0);
    }

    /**
     * Ambil tunjangan tetap dari employee_salary_components.
     */
    public function tunjangan_tetap(?string $period = null): float
    {
        return (float) ($this->activeSalaryComponent($period)?->tunjangan ?? $this->tunjangan ?? 0);
    }

    /**
     * Ambil kontrak yang sedang aktif.
     */
    public function activeContract(): ?EmployeeContract
    {
        return $this->contracts()
            ->where('status', 'active')
            ->where('is_latest', true)
            ->latest('start_date')
            ->first();
    }

    /**
     * Ambil posisi/jabatan saat ini dari position histories.
     * Menggunakan effective date pattern.
     */
    public function currentPosition(): ?EmployeePositionHistory
    {
        return $this->positionHistories()
            ->where('effective_date', '<=', now())
            ->latest('effective_date')
            ->first();
    }

    /**
     * Total gaji (gaji pokok + premi + tunjangan masa kerja + tunjangan).
     */
    public function totalGaji(?string $period = null): float
    {
        $comp = $this->activeSalaryComponent($period);
        if (! $comp) {
            return (float) ($this->base_salary + $this->premi + $this->tunjangan);
        }

        return (float) ($comp->gaji_pokok + $comp->premi + $comp->tunjangan_masa_kerja + $comp->tunjangan);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('employment_status', $status);
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByPosition($query, int $positionId)
    {
        return $query->where('position_id', $positionId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // ========== ACCESSORS ==========

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        return asset('storage/'.$this->photo);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function getFullAddressAttribute(): string
    {
        return $this->address.($this->postal_code ? ', '.$this->postal_code : '');
    }

    public function getYearsOfServiceAttribute(): int
    {
        return $this->join_date?->diffInYears(now()) ?? 0;
    }

    public function getMonthsOfServiceAttribute(): int
    {
        return $this->join_date?->diffInMonths(now()) ?? 0;
    }

    public function getEmploymentStatusLabelAttribute(): string
    {
        $labels = [
            'permanent'  => 'Permanen',
            'contract'   => 'Kontrak',
            'probation'  => 'Percobaan',
            'outsource'  => 'Outsource',
            'freelance'  => 'Freelance',
            'resigned'   => 'Resign',
            'terminated' => 'PHK',
        ];

        return $labels[$this->employment_status] ?? $this->employment_status;
    }
}
