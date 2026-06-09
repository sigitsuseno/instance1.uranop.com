<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reports\Controllers\Api\V1\UangMakanReportController;

Route::prefix('v1/reports')->middleware('auth:sanctum')->group(function () {
    Route::get('/uang-makan', [UangMakanReportController::class, 'index'])->name('api.reports.uang-makan.index');
});
