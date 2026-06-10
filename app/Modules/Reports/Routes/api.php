<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reports\Controllers\Api\V1\UangMakanReportController;
use App\Modules\Reports\Controllers\Api\V1\LaporanLemburController;

Route::prefix('v1/reports')->middleware('auth:sanctum')->group(function () {
    Route::get('/uang-makan', [UangMakanReportController::class, 'index'])->name('api.reports.uang-makan.index');
    Route::get('/uang-makan/export', [UangMakanReportController::class, 'export']);
    Route::get('/uang-makan/print', [UangMakanReportController::class, 'print']);

    // Laporan Lembur
    Route::prefix('lembur')->group(function () {
        Route::get('/harian', [LaporanLemburController::class, 'harian']);
        Route::get('/harian/export', [LaporanLemburController::class, 'exportHarian']);
        Route::get('/harian/print', [LaporanLemburController::class, 'printHarian']);
        Route::get('/bulanan', [LaporanLemburController::class, 'bulanan']);
        Route::get('/bulanan/export', [LaporanLemburController::class, 'exportBulanan']);
        Route::get('/bulanan/print', [LaporanLemburController::class, 'printBulanan']);
        Route::get('/resume', [LaporanLemburController::class, 'resume']);
        Route::get('/resume/export', [LaporanLemburController::class, 'exportResume']);
        Route::get('/resume/print', [LaporanLemburController::class, 'printResume']);
    });
});
