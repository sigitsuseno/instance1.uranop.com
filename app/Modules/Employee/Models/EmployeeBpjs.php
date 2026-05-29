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
        'bpjs_ketenagakerjaan_no',
        'bpjs_kesehatan_no',
        'bpjs_base_type',
        'jht_setting',
        'jp_setting',
        'jkk_setting',
        'jkm_setting',
        'kesehatan_setting',
        'kesehatan_dependents',
        'status_ketenagakerjaan',
        'status_kesehatan',
        'date_joined_ketenagakerjaan',
        'date_joined_kesehatan',
        'date_left_ketenagakerjaan',
        'date_left_kesehatan',
        'bpjs_kesehatan_class',
        'faskes_tingkat_1',
        'faskes_tingkat_1_code',
        'potongan_jht',
        'potongan_jp',
        'potongan_kesehatan',
        'tanggungan_jht',
        'tanggungan_jp',
        'tanggungan_jkk',
        'tanggungan_jkm',
        'tanggungan_kesehatan',
        'bpjs_base_salary',
        'last_generated_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_joined_ketenagakerjaan' => 'date',
        'date_joined_kesehatan'       => 'date',
        'date_left_ketenagakerjaan'   => 'date',
        'date_left_kesehatan'         => 'date',
        'potongan_jht'                => 'decimal:2',
        'potongan_jp'                 => 'decimal:2',
        'potongan_kesehatan'          => 'decimal:2',
        'tanggungan_jht'              => 'decimal:2',
        'tanggungan_jp'               => 'decimal:2',
        'tanggungan_jkk'              => 'decimal:2',
        'tanggungan_jkm'              => 'decimal:2',
        'tanggungan_kesehatan'        => 'decimal:2',
        'bpjs_base_salary'            => 'decimal:2',
        'last_generated_at'           => 'datetime',
        'kesehatan_dependents'        => 'integer',
    ];

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

    public function getTotalPotonganKaryawanAttribute(): float
    {
        return (float) ($this->potongan_jht + $this->potongan_jp + $this->potongan_kesehatan);
    }

    public function getTotalTanggunganPerusahaanAttribute(): float
    {
        return (float) ($this->tanggungan_jht + $this->tanggungan_jp
            + $this->tanggungan_jkk + $this->tanggungan_jkm + $this->tanggungan_kesehatan);
    }
}
