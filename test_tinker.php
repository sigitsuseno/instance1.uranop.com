<?php
$emp = \App\Modules\Employee\Models\Employee::where('employee_code', '50010')->first(); 
dump("Employee ID: " . $emp->id); 
$att = \App\Modules\Attendance\Models\AttendancePrepare::where('employee_id', $emp->id)->where('date', '2025-12-29')->first(); 
dump($att->toArray());
