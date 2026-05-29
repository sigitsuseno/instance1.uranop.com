<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Modules\Auth\Models\User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}

$request1 = Illuminate\Http\Request::create('/api/v1/employees?is_active=1', 'GET');
$request1->headers->set('Accept', 'application/json');
$request1->setUserResolver(function () use ($user) { return $user; });

$response1 = $app->handle($request1);
if ($response1->getStatusCode() >= 400) {
    echo "ERROR /employees: " . json_decode($response1->getContent(), true)['message'] . "\n";
    echo $response1->getContent() . "\n";
} else {
    echo "OK /employees\n";
}

$request2 = Illuminate\Http\Request::create('/api/v1/employees/contracts/stats', 'GET');
$request2->headers->set('Accept', 'application/json');
$request2->setUserResolver(function () use ($user) { return $user; });

$response2 = $app->handle($request2);
if ($response2->getStatusCode() >= 400) {
    echo "ERROR /contracts/stats: " . json_decode($response2->getContent(), true)['message'] . "\n";
    echo $response2->getContent() . "\n";
} else {
    echo "OK /contracts/stats\n";
}
