<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Employee\Models\EmployeeSalary;

class EmployeePph extends Model
{
    use HasAuditLog;

    protected $table = 'employee_pph';

    protected $guarded = ['id'];

    /**
     * Get the employee associated with the tax record.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the pay period associated with the tax record.
     */
    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class);
    }

    /**
     * Get the pay record associated with the tax record.
     */
    public function payRecord()
    {
        return $this->belongsTo(PayRecord::class);
    }

    /**
     * Referensi ke data gaji sumber perhitungan (employee_salaries).
     */
    public function sourceSalary()
    {
        return $this->belongsTo(EmployeeSalary::class, 'source_salary_id');
    }

    /**
     * Referensi ke data BPJS per periode yang menjadi sumber perhitungan.
     */
    public function sourceBpjs()
    {
        return $this->belongsTo(EmployeeBpjs::class, 'source_bpjs_id');
    }
}
