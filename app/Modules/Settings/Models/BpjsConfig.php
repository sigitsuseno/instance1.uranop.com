<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BpjsConfig extends Model
{
    use SoftDeletes;

    protected $table = 'bpjs_configs';

    protected $fillable = [
        'uuid',
        'effective_date',
        'is_active',
        'jht_employer',
        'jht_employee',
        'jkk',
        'jkm',
        'jp_employer',
        'jp_employee',
        'kesehatan_employer',
        'kesehatan_employee',
        'max_wage_cap',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date'       => 'date',
        'is_active'            => 'boolean',
        'jht_employer'         => 'decimal:2',
        'jht_employee'         => 'decimal:2',
        'jkk'                  => 'decimal:2',
        'jkm'                  => 'decimal:2',
        'jp_employer'          => 'decimal:2',
        'jp_employee'          => 'decimal:2',
        'kesehatan_employer'   => 'decimal:2',
        'kesehatan_employee'   => 'decimal:2',
        'max_wage_cap'         => 'decimal:2',
    ];

    // === Scopes ===

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // === Calculation Methods ===

    /**
     * Kalkulasi JHT (Jaminan Hari Tua).
     * Dipotong dari karyawan + ditanggung perusahaan.
     */
    public function calculateJHT(float $baseSalary): array
    {
        $base = $this->max_wage_cap ? min($baseSalary, (float) $this->max_wage_cap) : $baseSalary;

        return [
            'employer' => round($base * (float) $this->jht_employer / 100, 2),
            'employee' => round($base * (float) $this->jht_employee / 100, 2),
        ];
    }

    /**
     * Kalkulasi JP (Jaminan Pensiun).
     * Dipotong dari karyawan + ditanggung perusahaan.
     */
    public function calculateJP(float $baseSalary): array
    {
        $base = $this->max_wage_cap ? min($baseSalary, (float) $this->max_wage_cap) : $baseSalary;

        return [
            'employer' => round($base * (float) $this->jp_employer / 100, 2),
            'employee' => round($base * (float) $this->jp_employee / 100, 2),
        ];
    }

    /**
     * Kalkulasi BPJS Kesehatan.
     * Dipotong dari karyawan + ditanggung perusahaan.
     * Includes tambahan 1% per tanggungan keluarga.
     */
    public function calculateKesehatan(float $baseSalary, int $dependents = 0): array
    {
        $base = $this->max_wage_cap ? min($baseSalary, (float) $this->max_wage_cap) : $baseSalary;
        $extraPct = $dependents * 1.0; // +1% per tanggungan

        return [
            'employer' => round($base * (float) $this->kesehatan_employer / 100, 2),
            'employee' => round($base * ((float) $this->kesehatan_employee + $extraPct) / 100, 2),
        ];
    }

    /**
     * Kalkulasi JKK (Kecelakaan Kerja) — perusahaan only.
     */
    public function calculateJKK(float $baseSalary): float
    {
        return round($baseSalary * (float) $this->jkk / 100, 2);
    }

    /**
     * Kalkulasi JKM (Kematian) — perusahaan only.
     */
    public function calculateJKM(float $baseSalary): float
    {
        return round($baseSalary * (float) $this->jkm / 100, 2);
    }

    // === Helpers ===

    /** Total persentase porsi perusahaan */
    public function getEmployerTotalPct(): float
    {
        return (float) $this->jht_employer + (float) $this->jkk + (float) $this->jkm
            + (float) $this->jp_employer + (float) $this->kesehatan_employer;
    }

    /** Total persentase porsi karyawan */
    public function getEmployeeTotalPct(): float
    {
        return (float) $this->jht_employee + (float) $this->jp_employee + (float) $this->kesehatan_employee;
    }
}
