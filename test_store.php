<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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

$controller = $app->make(\App\Modules\Settings\Controllers\Api\V1\BpjsConfigController::class);

try {
    // mock auth
    $user = \App\Modules\Auth\Models\User::first() ?? \App\Modules\Auth\Models\User::factory()->create();
    $app->make('auth')->guard('sanctum')->setUser($user);
    $request->setUserResolver(function () use ($user) { return $user; });
    
    $response = $controller->store($request);
    echo "Success: " . $response->getStatusCode() . "\n";
    echo $response->getContent();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
