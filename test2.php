<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $stats = app(\App\Modules\Employee\Controllers\Api\V1\Contract\ContractApiController::class)->stats();
    echo "Stats result:\n";
    echo $stats->getContent() . "\n";
} catch (\Throwable $e) {
    echo "Stats Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "-----------------\n";

try {
    $list = app(\App\Modules\Employee\Services\EmployeeService::class)->getPaginated(['is_active' => '1', 'contract_type' => '']);
    echo "List result count: " . $list->count() . "\n";
    // Check serialization of first item
    if ($list->count() > 0) {
        $first = $list->first();
        echo "First ID: {$first->id}\n";
        $resource = new \App\Modules\Employee\Resources\EmployeeListResource($first);
        json_encode($resource->resolve());
        echo "Resource serialized successfully.\n";
    }
} catch (\Throwable $e) {
    echo "List Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
