<?php

use App\Modules\Statistik\Controllers\Api\V1\StatistikController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/statistik')->middleware(['auth:sanctum'])->group(function () {
    Route::get('kehadiran', [StatistikController::class, 'kehadiran']);
    Route::get('payroll', [StatistikController::class, 'payroll']);
    Route::get('payroll/komposisi', [StatistikController::class, 'komposisi']);
});
