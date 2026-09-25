<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payroll\Controllers\Api\V1\PayPeriodApiController;
use App\Modules\Payroll\Controllers\Api\V1\GajiKaryawanController;
use App\Modules\Payroll\Controllers\Api\V1\PayrollConfigApiController;
use App\Modules\Payroll\Controllers\Api\V1\PayslipController;
use App\Modules\Payroll\Controllers\Api\V1\WorkingDayOverrideController;

Route::prefix('v1/payroll')->middleware(['api'])->group(function () {
    Route::apiResource('periods', PayPeriodApiController::class);
    Route::get('gaji-karyawan', [GajiKaryawanController::class, 'index']);

    // Lifecycle payroll 2-mode: Simpan (snapshot) → Finalisasi → Lock → Unlock
    // Ditaruh SEBELUM route {id} (specific routes before wildcard)
    Route::post('gaji-karyawan/simpan', [GajiKaryawanController::class, 'simpan']);
    Route::post('gaji-karyawan/finalisasi', [GajiKaryawanController::class, 'finalisasi']);
    Route::post('gaji-karyawan/lock', [GajiKaryawanController::class, 'lock']);
    Route::post('gaji-karyawan/unlock', [GajiKaryawanController::class, 'unlock']);
    Route::post('gaji-karyawan/sync-missing', [GajiKaryawanController::class, 'syncMissingRecords']);

    // Pengaturan khusus hari_kerja per karyawan per periode — acuan saat Finalisasi
    Route::get('working-day-overrides', [WorkingDayOverrideController::class, 'index']);
    Route::post('working-day-overrides', [WorkingDayOverrideController::class, 'store']);
    Route::delete('working-day-overrides/{id}', [WorkingDayOverrideController::class, 'destroy']);

    Route::put('gaji-karyawan/{id}/upah-lembur', [GajiKaryawanController::class, 'updateUpahLembur']);
    Route::put('gaji-karyawan/{id}/transfer-info', [GajiKaryawanController::class, 'updateTransferInfo']);
    Route::put('gaji-karyawan/bulk-update-cabang', [GajiKaryawanController::class, 'bulkUpdateCabang']);

    // Konfigurasi Payroll
    Route::get('configs/{type}', [PayrollConfigApiController::class, 'show']);
    Route::put('configs/{type}', [PayrollConfigApiController::class, 'update']);

    // Slip Gaji
    Route::get('payslips', [PayslipController::class, 'index']);
    
    // Pajak Karyawan
    Route::get('pph/employees', [\App\Modules\Payroll\Controllers\Api\V1\EmployeeTaxController::class, 'index']);
    Route::put('pph/employees/{id}', [\App\Modules\Payroll\Controllers\Api\V1\EmployeeTaxController::class, 'update']);

    // Data PPh — Generate & Kelola PPh per periode
    Route::get('pph/data', [\App\Modules\Payroll\Controllers\Api\V1\EmployeePphController::class, 'index']);
    Route::post('pph/generate', [\App\Modules\Payroll\Controllers\Api\V1\EmployeePphController::class, 'generate']);
    Route::put('pph/data/{id}', [\App\Modules\Payroll\Controllers\Api\V1\EmployeePphController::class, 'update']);
    Route::delete('pph/data/{id}', [\App\Modules\Payroll\Controllers\Api\V1\EmployeePphController::class, 'destroy']);

    // THR
    Route::get('thr', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'index']);
    Route::post('thr/generate', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'generate']);
    Route::put('thr/{id}', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'update']);
    Route::delete('thr/{id}', [\App\Modules\Payroll\Controllers\Api\V1\ThrApiController::class, 'destroy']);
});
