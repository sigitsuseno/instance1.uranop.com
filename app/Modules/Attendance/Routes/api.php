<?php

use App\Modules\Attendance\Controllers\Api\V1\AttendanceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ========== ATTENDANCE LOGS ==========

    Route::prefix('attendance/logs')->group(function () {

        // Import endpoints (harus SEBELUM {batch} agar tidak konflik)
        Route::get('/import/template', [AttendanceApiController::class, 'template'])
            ->name('attendance.logs.import.template');
        Route::post('/import', [AttendanceApiController::class, 'import'])
            ->name('attendance.logs.import.store');
        Route::get('/import/status/{batch}', [AttendanceApiController::class, 'importStatus'])
            ->name('attendance.logs.import.status');

        // Stats
        Route::get('/stats', [AttendanceApiController::class, 'stats'])
            ->name('attendance.logs.stats');

        // Bin import (fingerprint machine)
        Route::post('/import-bin', [AttendanceApiController::class, 'importBin'])
            ->name('attendance.logs.import-bin');

        // CRUD
        Route::get('/', [AttendanceApiController::class, 'index'])
            ->name('attendance.logs.index');
        Route::delete('/batch/{batch}', [AttendanceApiController::class, 'deleteBatch'])
            ->name('attendance.logs.delete-batch');
    });

    // ========== ATTENDANCE CONFIGS ==========

    Route::prefix('attendance/configs')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'configs'])
            ->name('attendance.configs.index');
        Route::put('/', [AttendanceApiController::class, 'updateConfigs'])
            ->name('attendance.configs.update');
    });

    // ========== SCAN DETECTION CONFIGS ==========

    Route::prefix('attendance/scan-configs')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'scanConfigs'])
            ->name('attendance.scan-configs.index');
        Route::post('/', [AttendanceApiController::class, 'storeScanConfig'])
            ->name('attendance.scan-configs.store');
        Route::put('/{id}', [AttendanceApiController::class, 'updateScanConfig'])
            ->name('attendance.scan-configs.update');
        Route::delete('/{id}', [AttendanceApiController::class, 'deleteScanConfig'])
            ->name('attendance.scan-configs.destroy');
    });

    // ========== ATTENDANCE RECORDS ==========

    Route::prefix('attendance/records')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'records'])
            ->name('attendance.records.index');
        Route::get('/{id}', [AttendanceApiController::class, 'showRecord'])
            ->name('attendance.records.show');
        Route::put('/{id}', [AttendanceApiController::class, 'updateRecord'])
            ->name('attendance.records.update');
    });

    // ========== ATTENDANCE SUMMARIES ==========

    Route::prefix('attendance/summaries')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'summaries'])
            ->name('attendance.summaries.index');
    });

    // ========== ATTENDANCE PREPARE (Core: Sync, Lengkapi, Hitung Lembur, Lock) ==========

    Route::prefix('attendance/prepare')->group(function () {
        Route::post('/sync', [AttendanceApiController::class, 'prepareSync'])
            ->name('attendance.prepare.sync');
        Route::get('/list', [AttendanceApiController::class, 'prepareList'])
            ->name('attendance.prepare.list');
        Route::get('/stats', [AttendanceApiController::class, 'prepareStats'])
            ->name('attendance.prepare.stats');
        Route::post('/lengkapi', [AttendanceApiController::class, 'prepareLengkapi'])
            ->name('attendance.prepare.lengkapi');
        Route::post('/hitung-lembur', [AttendanceApiController::class, 'prepareHitungLembur'])
            ->name('attendance.prepare.hitung-lembur');
        Route::post('/lock', [AttendanceApiController::class, 'prepareLock'])
            ->name('attendance.prepare.lock');
    });

    // ========== ATTENDANCE PROCESSING ==========

    Route::prefix('attendance/process')->group(function () {
        Route::post('/autolog', [AttendanceApiController::class, 'processAutolog'])
            ->name('attendance.process.autolog');
        Route::post('/generate-records', [AttendanceApiController::class, 'processGenerateRecords'])
            ->name('attendance.process.generate-records');
        Route::post('/generate-summary', [AttendanceApiController::class, 'processGenerateSummary'])
            ->name('attendance.process.generate-summary');
    });

    // ========== OVERTIME ==========

    Route::prefix('attendance/overtimes')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'overtimes'])
            ->name('attendance.overtimes.index');
        Route::post('/', [AttendanceApiController::class, 'storeOvertime'])
            ->name('attendance.overtimes.store');
        Route::put('/{id}/approve', [AttendanceApiController::class, 'approveOvertime'])
            ->name('attendance.overtimes.approve');
        Route::delete('/{id}', [AttendanceApiController::class, 'deleteOvertime'])
            ->name('attendance.overtimes.destroy');
    });

});
