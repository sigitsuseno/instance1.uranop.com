<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/api/v1/settings/bpjs-configs', 'POST', [
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
    'description' => '',
]);
$app->make('auth')->loginUsingId(1);
$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
