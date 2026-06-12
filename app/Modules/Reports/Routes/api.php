<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reports\Controllers\Api\V1\AttendanceReportController;

// Note: Laravel already prefixes with /api, so NO prefix needed here
Route::prefix('v1/laporan')->name('api.laporan.')->group(function () {

    Route::get('/kehadiran', [AttendanceReportController::class, 'index'])
        ->name('kehadiran');

});
