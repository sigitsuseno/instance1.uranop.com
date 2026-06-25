<?php

use App\Modules\Attendance\Controllers\Api\V1\AttendanceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ========== ATTENDANCE LOGS ==========

    Route::prefix('attendance/logs')->group(function () {

        // Cek Log endpoint
        Route::get('/cek', [\App\Modules\Attendance\Controllers\Api\V1\RawLogController::class, 'cekLog'])
            ->name('attendance.logs.cek');

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


    // ========== ATTENDANCE PREPARE (Core: Sync, Lengkapi, Hitung Lembur, Lock) ==========

    Route::prefix('attendance/prepare')->group(function () {
        Route::post('/sync', [AttendanceApiController::class, 'prepareSync'])
            ->name('attendance.prepare.sync');
        Route::get('/list', [AttendanceApiController::class, 'prepareList'])
            ->name('attendance.prepare.list');
        Route::get('/stats', [AttendanceApiController::class, 'prepareStats'])
            ->name('attendance.prepare.stats');
        Route::get('overtime-summary', [AttendanceApiController::class, 'prepareOvertimeSummary'])->name('attendance.prepare.overtime-summary');
        Route::get('overtime-navigation', [AttendanceApiController::class, 'prepareOvertimeNavigation'])->name('attendance.prepare.overtime-navigation');
        Route::post('/lengkapi', [AttendanceApiController::class, 'prepareLengkapi'])
            ->name('attendance.prepare.lengkapi');
        Route::post('/auto-lengkapi', [AttendanceApiController::class, 'prepareAutoLengkapi'])
            ->name('attendance.prepare.auto-lengkapi');
        Route::post('/hitung-lembur', [AttendanceApiController::class, 'prepareHitungLembur'])
            ->name('attendance.prepare.hitung-lembur');
        Route::post('/lock', [AttendanceApiController::class, 'prepareLock'])
            ->name('attendance.prepare.lock');
        Route::post('/update-status-legacy', [AttendanceApiController::class, 'prepareUpdateStatusLegacy'])
            ->name('attendance.prepare.update-status-legacy');
        Route::get('/employee-groups', [AttendanceApiController::class, 'prepareEmployeeGroups'])
            ->name('attendance.prepare.employee-groups');
    });

    // ========== CONSECUTIVE DAYS — CRUD ==========

    Route::prefix('attendance/consecutive')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'consecutiveList'])
            ->name('attendance.consecutive.list');
        Route::post('/', [AttendanceApiController::class, 'consecutiveStore'])
            ->name('attendance.consecutive.store');
        Route::put('/{id}', [AttendanceApiController::class, 'consecutiveUpdate'])
            ->name('attendance.consecutive.update');
        Route::delete('/{id}', [AttendanceApiController::class, 'consecutiveDestroy'])
            ->name('attendance.consecutive.destroy');
    });

    // ========== RESUME KEHADIRAN (Attendance Records) ==========

    Route::prefix('attendance/recap')->group(function () {
        Route::get('/', [AttendanceApiController::class, 'recapList'])
            ->name('attendance.recap.list');
        Route::get('/export', [AttendanceApiController::class, 'recapExport'])
            ->name('attendance.recap.export');
        Route::post('/generate', [AttendanceApiController::class, 'recapGenerate'])
            ->name('attendance.recap.generate');
        Route::post('/approve', [AttendanceApiController::class, 'recapApprove'])
            ->name('attendance.recap.approve');
    });


});
