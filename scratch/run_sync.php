<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Modules\Attendance\Services\AttendanceSyncService::class);
$result = $service->syncSingleDay(48, '2025-12-30');
echo json_encode($result, JSON_PRETTY_PRINT);
