<?php

namespace App\Modules\Supervisor\Attendance\Models;

use App\Modules\Employee\Models\Employee;

class SupervisorEmployee extends Employee
{
    protected $table = 'employees';

    public function autologs()
    {
        return $this->hasMany(SupervisorAttendance::class, 'employee_id');
    }

    public function supervisorSnapshots()
    {
        return $this->hasMany(SupervisorAttendanceSnapshot::class, 'employee_id');
    }
}
