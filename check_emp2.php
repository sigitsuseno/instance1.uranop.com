<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$employee = App\Modules\Employee\Models\Employee::find(47);
echo json_encode($employee->toArray(), JSON_PRETTY_PRINT);
