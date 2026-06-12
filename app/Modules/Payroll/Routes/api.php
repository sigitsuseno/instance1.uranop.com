<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payroll\Controllers\Api\V1\PayPeriodApiController;
use App\Modules\Payroll\Controllers\Api\V1\GajiKaryawanController;
use App\Modules\Payroll\Controllers\Api\V1\PayslipController;

Route::prefix('v1/payroll')->middleware(['api'])->group(function () {
    Route::apiResource('periods', PayPeriodApiController::class);
    Route::get('gaji-karyawan', [GajiKaryawanController::class, 'index']);

    // Slip Gaji
    Route::get('payslips', [PayslipController::class, 'index']);
    
    // Pajak Karyawan
    Route::get('pph/employees', [\App\Modules\Payroll\Controllers\Api\V1\EmployeeTaxController::class, 'index']);
    Route::put('pph/employees/{id}', [\App\Modules\Payroll\Controllers\Api\V1\EmployeeTaxController::class, 'update']);

    // THR
    Route::get('thr', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'index']);
    Route::post('thr/generate', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'generate']);
    Route::put('thr/{id}', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'update']);
    Route::delete('thr/{id}', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'destroy']);
});
