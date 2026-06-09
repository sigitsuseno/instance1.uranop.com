<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reports\Controllers\Api\V1\UangMakanReportController;
use App\Modules\Reports\Controllers\Api\V1\LaporanLemburController;

Route::prefix('v1/reports')->middleware('auth:sanctum')->group(function () {
    Route::get('/uang-makan', [UangMakanReportController::class, 'index'])->name('api.reports.uang-makan.index');

    // Laporan Lembur
    Route::prefix('lembur')->group(function () {
        Route::get('/harian', [LaporanLemburController::class, 'harian']);
        Route::get('/bulanan', [LaporanLemburController::class, 'bulanan']);
    });
});
