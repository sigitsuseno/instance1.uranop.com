<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => config('app.name'),
        'version' => app()->version(),
        'environment' => app()->environment(),
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/v1/push-subscribe', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'subscribe']);

Route::get('/test-api', function() {
    try {
        $stats = app(\App\Modules\Employee\Controllers\Api\V1\Contract\ContractApiController::class)->stats();
        return $stats;
    } catch (\Throwable $e) {
        return ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
    }
});
