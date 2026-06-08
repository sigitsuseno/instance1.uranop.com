<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBpjs extends Model
{
    use SoftDeletes;

    protected $table = 'employee_bpjs';

    protected $fillable = [
        'employee_id',
        'pay_period_id',

        // Nomor
        'bpjs_ketenagakerjaan_no',
        'bpjs_kesehatan_no',

        // Checkbox
        'has_bpjs_tk',
        'has_bpjs_ks',
        'has_bpjs_pen',

        // Dasar
        'bpjs_base_type',
        'bpjs_base_salary',
        'tj_masa_kerja',
        'tunjangan',

        // Employer (5)
        'employer_jht',
        'employer_jkk',
        'employer_jkm',
        'employer_kesehatan',
        'employer_jp',

        // Employee (3)
        'employee_jht',
        'employee_kesehatan',
        'employee_jp',

        // Status
        'status_ketenagakerjaan',
        'status_kesehatan',

        // Tanggal
        'date_joined_ketenagakerjaan',
        'date_joined_kesehatan',
        'date_left_ketenagakerjaan',
        'date_left_kesehatan',

        // Kesehatan
        'bpjs_kesehatan_class',
        'kesehatan_dependents',
        'faskes_tingkat_1',
        'faskes_tingkat_1_code',

        'last_generated_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $attributes = [
        'has_bpjs_tk'  => true,
        'has_bpjs_ks'  => true,
        'has_bpjs_pen' => true,
    ];

    protected $casts = [
        'has_bpjs_tk'                => 'boolean',
        'has_bpjs_ks'                => 'boolean',
        'has_bpjs_pen'               => 'boolean',
        'bpjs_base_salary'           => 'decimal:2',
        'tj_masa_kerja'              => 'decimal:2',
        'tunjangan'                  => 'decimal:2',
        'employer_jht'               => 'decimal:2',
        'employer_jkk'               => 'decimal:2',
        'employer_jkm'               => 'decimal:2',
        'employer_kesehatan'         => 'decimal:2',
        'employer_jp'                => 'decimal:2',
        'employee_jht'               => 'decimal:2',
        'employee_kesehatan'         => 'decimal:2',
        'employee_jp'                => 'decimal:2',
        'date_joined_ketenagakerjaan' => 'date',
        'date_joined_kesehatan'      => 'date',
        'date_left_ketenagakerjaan'   => 'date',
        'date_left_kesehatan'         => 'date',
        'kesehatan_dependents'       => 'integer',
        'last_generated_at'          => 'datetime',
    ];

    // === Relations ===

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // === Accessors ===

    /** Total potongan karyawan (3 komponen) */
    public function getTotalEmployeeAttribute(): float
    {
        return (float) ($this->employee_jht + $this->employee_kesehatan + $this->employee_jp);
    }

    /** Total tanggungan perusahaan (5 komponen) */
    public function getTotalEmployerAttribute(): float
    {
        return (float) (
            $this->employer_jht + $this->employer_jkk + $this->employer_jkm
            + $this->employer_kesehatan + $this->employer_jp
        );
    }

    /** Grand total iuran BPJS */
    public function getGrandTotalAttribute(): float
    {
        return $this->total_employee + $this->total_employer;
    }

    /** BPJS TK — potongan karyawan (JHT) untuk pay_record */
    public function getBpjsTkKaryawanAttribute(): float
    {
        return (float) ($this->employee_jht ?? 0);
    }

    /** BPJS Kesehatan — potongan karyawan untuk pay_record */
    public function getBpjsKesKaryawanAttribute(): float
    {
        return (float) ($this->employee_kesehatan ?? 0);
    }

    /** BPJS Pensiun — potongan karyawan (JP) untuk pay_record */
    public function getBpjsPensiunAttribute(): float
    {
        return (float) ($this->employee_jp ?? 0);
    }

    // === Helpers ===

    public function isActiveTk(): bool
    {
        return $this->status_ketenagakerjaan === 'active';
    }

    public function isActiveKs(): bool
    {
        return $this->status_kesehatan === 'active';
    }
}
