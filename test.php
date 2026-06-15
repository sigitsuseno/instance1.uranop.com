<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $config = App\Modules\Settings\Models\BpjsConfig::create([
        'uuid' => \Illuminate\Support\Str::uuid(),
        'effective_date' => '2026-06-15',
        'jht_employer' => 3.70,
        'jht_employee' => 2.00,
        'jkk' => 0.24,
        'jkm' => 0.30,
        'jp_employer' => 2.00,
        'jp_employee' => 1.00,
        'kesehatan_employer' => 4.00,
        'kesehatan_employee' => 1.00,
        'max_wage_cap' => 12000000,
    ]);
    echo 'Success: ' . $config->id . "\n";
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
