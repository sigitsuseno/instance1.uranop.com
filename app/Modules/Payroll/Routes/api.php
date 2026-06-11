<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payroll\Controllers\Api\V1\PayPeriodApiController;
use App\Modules\Payroll\Controllers\Api\V1\GajiKaryawanController;

Route::prefix('v1/payroll')->middleware(['api'])->group(function () {
    Route::apiResource('periods', PayPeriodApiController::class);
    Route::get('gaji-karyawan', [GajiKaryawanController::class, 'index']);
});
