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

        // CRUD
        Route::get('/', [AttendanceApiController::class, 'index'])
            ->name('attendance.logs.index');
        Route::delete('/batch/{batch}', [AttendanceApiController::class, 'deleteBatch'])
            ->name('attendance.logs.delete-batch');
    });
});
