<?php

use App\Modules\Mobile\Controllers\Api\V1\MobileApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes — Khusus Karyawan (Employee Self-Service)
|--------------------------------------------------------------------------
|
| Semua endpoint di sini hanya bisa diakses oleh user yang punya employee
| record. Data otomatis di-scope ke karyawan yang sedang login.
|
*/

Route::prefix('mobile')->middleware(['auth:sanctum'])->group(function () {

    // Profile
    Route::get('/profile', [MobileApiController::class, 'profile'])
        ->name('mobile.profile');

    // Attendance
    Route::get('/attendance', [MobileApiController::class, 'attendance'])
        ->name('mobile.attendance');

    Route::get('/attendance/summary', [MobileApiController::class, 'attendanceSummary'])
        ->name('mobile.attendance.summary');

});
