<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EmployeeSalaryComponent — komponen gaji detail per karyawan.
 * Setiap karyawan memiliki nilai yang berbeda-beda.
 * Diakses via accessor di model Employee:
 *   $employee->gaji_pokok()
 *   $employee->premi_component()
 *   $employee->tunjangan_masa_kerja()
 *   $employee->tunjangan_tetap()
 */
class EmployeeSalaryComponent extends Model
{
    use SoftDeletes;

    protected $table = 'employee_salary_components';

    protected $fillable = [
        'employee_id',
        'gaji_pokok',
        'premi',
        'tunjangan_masa_kerja',
        'hari_kerja',
        'lembur_minggu_holiday',
        'lembur',
        'tunjangan',
        'tunjangan_lain',
        'bpjs_tk',
        'bpjs_ks',
        'bpjs_pensiun',
        'pph',
        'kompensasi_pph',
        'kasbon',
        'is_active',
        'effective_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gaji_pokok'           => 'decimal:2',
        'premi'                => 'decimal:2',
        'tunjangan_masa_kerja' => 'decimal:2',
        'lembur_minggu_holiday'=> 'decimal:2',
        'lembur'               => 'decimal:2',
        'tunjangan'            => 'decimal:2',
        'tunjangan_lain'       => 'decimal:2',
        'bpjs_tk'              => 'decimal:2',
        'bpjs_ks'              => 'decimal:2',
        'bpjs_pensiun'         => 'decimal:2',
        'pph'                  => 'decimal:2',
        'kompensasi_pph'       => 'decimal:2',
        'kasbon'               => 'decimal:2',
        'is_active'            => 'boolean',
        'effective_date'       => 'date',
        'end_date'             => 'date',
        'hari_kerja'           => 'integer',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Total penghasilan bruto.
     */
    public function getTotalPenghasilanAttribute(): float
    {
        return (float) ($this->gaji_pokok
            + $this->premi
            + $this->tunjangan_masa_kerja
            + $this->lembur_minggu_holiday
            + $this->lembur
            + $this->tunjangan
            + $this->tunjangan_lain);
    }

    /**
     * Total potongan.
     */
    public function getTotalPotonganAttribute(): float
    {
        return (float) ($this->bpjs_tk + $this->bpjs_ks + $this->bpjs_pensiun + $this->pph + $this->kasbon);
    }

    /**
     * Take home pay.
     */
    public function getTakeHomePayAttribute(): float
    {
        return $this->total_penghasilan - $this->total_potongan + (float) $this->kompensasi_pph;
    }
}
