<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Employee\Models\Employee;

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
}
