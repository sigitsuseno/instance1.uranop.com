<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$r = App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', 47)->whereNotNull('work_pattern_id')->first();
echo "Employee 47 normal type: " . ($r && $r->workPattern ? $r->workPattern->employee_type : 'None') . "\n";
