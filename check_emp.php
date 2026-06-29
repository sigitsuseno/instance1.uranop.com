<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employee = App\Modules\Employee\Models\Employee::with('workPattern')->find(47);
echo "Employee 47 Work Pattern Type: " . ($employee->workPattern ? $employee->workPattern->employee_type : 'None') . "\n";
