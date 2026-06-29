<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$wp = App\Modules\Schedule\Models\WorkPattern::find(3);
echo json_encode($wp ? $wp->toArray() : null, JSON_PRETTY_PRINT);
