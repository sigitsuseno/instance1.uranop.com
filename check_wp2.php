<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$types = App\Modules\Schedule\Models\WorkPattern::select('employee_type')->distinct()->pluck('employee_type');
echo json_encode($types);
