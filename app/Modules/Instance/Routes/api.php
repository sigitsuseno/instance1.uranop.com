<?php

use App\Modules\Instance\Controllers\Api\V1\InstanceApiController;
use Illuminate\Support\Facades\Route;

// Pre-auth endpoints (no token needed — desktop setup flow)
Route::prefix('v1/instance')->group(function () {
    Route::post('/store', [InstanceApiController::class, 'store']);
    Route::post('/verify', [InstanceApiController::class, 'verify']);
});

// Authenticated endpoints
Route::prefix('v1/instance')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/show', [InstanceApiController::class, 'show']);
});