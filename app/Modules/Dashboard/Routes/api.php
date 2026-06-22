<?php

use App\Modules\Dashboard\Controllers\Api\V1\DashboardApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/dashboard')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', [DashboardApiController::class, 'index']);
});

