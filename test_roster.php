<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = new \Illuminate\Http\Request(['year' => 2026, 'month' => 6]);
$controller = app(\App\Modules\Schedule\Controllers\Api\V1\ScheduleApiController::class);
echo $controller->getRoster($request)->getContent();
