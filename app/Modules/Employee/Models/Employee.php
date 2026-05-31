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

    public function groups()
    {
        return $this->hasMany(EmployeeGroup::class, 'employee_id');
    }

    public function hasGroup(string $referenceCode): bool
    {
        return $this->groups()->where('reference_code', $referenceCode)->exists();
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
     * Ambil data EmployeeSalary aktif pada periode tertentu.
     * Mengabaikan is_active agar histori masa lalu tetap terbaca dengan valid.
     */
    public function activeSalary(?string $period = null)
    {
        $query = $this->salaries();

        if ($period) {
            $date = Carbon::parse($period.'-01')->endOfMonth();
            $query->where('effective_date', '<=', $date);
        } else {
            $query->where('effective_date', '<=', now());
        }

        return $query->orderBy('effective_date', 'desc')->orderBy('id', 'desc')->first();
    }

    /**
     * Ambil gaji pokok per periode.
     * Contoh: $employee->baseSalary()        → gaji bulan ini
     *         $employee->baseSalary('2026-05') → gaji Mei 2026
     */
    public function baseSalary(?string $period = null): float
    {
        return (float) ($this->activeSalary($period)?->base_salary ?? $this->base_salary ?? 0);
    }

    /**
     * Alias untuk baseSalary()
     */
    public function gaji_pokok(?string $period = null): float
    {
        return $this->baseSalary($period);
    }

    /**
     * Ambil premi dari employee_salaries.
     */
    public function premi_component(?string $period = null): float
    {
        return (float) ($this->activeSalary($period)?->premi ?? $this->premi ?? 0);
    }

    /**
     * Ambil tunjangan masa kerja (dinamis berdasarkan join_date).
     */
    public function tunjangan_masa_kerja(?string $period = null): float
    {
        return $this->tjMasaKerja($period);
    }

    /**
     * Hitung tunjangan masa kerja berdasarkan join_date dan periode.
     * 0-11 bulan = 0
     * 12-23 bulan = 1000
     * 24-35 bulan = 2000
     * 36-47 bulan = 3000
     * 48-59 bulan = 4000
     * >= 60 bulan = 5000
     */
    public function tjMasaKerja(?string $period = null): float
    {
        if (!$this->join_date) {
            return 0;
        }

        $endDate = now();
        if ($period) {
            $parts = explode('-', $period);
            if (count($parts) === 2) {
                $payPeriod = \App\Modules\Payroll\Models\PayPeriod::where('period_year', $parts[0])
                    ->where('period_month', $parts[1])
                    ->first();
                $endDate = $payPeriod?->end_date ? Carbon::parse($payPeriod->end_date) : Carbon::parse($period.'-01')->endOfMonth();
            } else {
                $endDate = Carbon::parse($period.'-01')->endOfMonth();
            }
        }
        
        // Jika join_date lebih dari endDate, artinya belum join
        if ($this->join_date->gt($endDate)) {
            return 0;
        }

        $months = $this->join_date->diffInMonths($endDate);

        if ($months < 12) return 0;
        if ($months < 24) return 1000;
        if ($months < 36) return 2000;
        if ($months < 48) return 3000;
        if ($months < 60) return 4000;
        return 5000;
    }

    /**
     * Ambil tunjangan tetap dari employee_salaries.
     */
    public function tunjangan_tetap(?string $period = null): float
    {
        return (float) ($this->activeSalary($period)?->tunjangan ?? $this->tunjangan ?? 0);
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
        $salary = $this->activeSalary($period);
        if (! $salary) {
            return (float) ($this->base_salary + $this->premi + $this->tjMasaKerja($period) + $this->tunjangan);
        }

        return (float) ($salary->base_salary + $salary->premi + $this->tjMasaKerja($period) + $salary->tunjangan);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Mengambil karyawan yang aktif pada suatu rentang periode tertentu.
     * Menggunakan join_date dan end_date (kapan karyawan resign/berakhir), tanpa melihat is_active.
     */
    public function scopeActiveInPeriod($query, $startDate, $endDate)
    {
        return $query->where('join_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $startDate);
            });
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
