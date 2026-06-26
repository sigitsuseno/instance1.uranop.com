<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emp = \App\Modules\Employee\Models\Employee::where('nip', '171')->first();
if (!$emp) {
    echo "Employee NIP 171 not found.\n";
} else {
    $empCode = $emp->employee_code;
    echo "Found employee code: " . $empCode . "\n";
    $logs = \App\Modules\Attendance\Models\RawLog::whereDate('scan_datetime', '2025-12-25')
        ->where(function($q) use ($empCode) {
            $q->where('pin', '171')
              ->orWhere('employee_code', '171')
              ->orWhere('employee_code', $empCode);
        })->get()->toArray();
    
    echo "Records found: " . count($logs) . "\n";
    echo json_encode($logs, JSON_PRETTY_PRINT);
}
