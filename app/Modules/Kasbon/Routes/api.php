<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Kasbon\Controllers\Api\V1\KasbonApiController;

Route::prefix('v1/kasbon')->middleware(['auth:sanctum'])->group(function () {

    // Requests (Pengajuan)
    Route::get('requests', [KasbonApiController::class, 'index']);
    Route::post('requests', [KasbonApiController::class, 'store']);
    Route::get('requests/{id}', [KasbonApiController::class, 'show']);
    Route::put('requests/{id}', [KasbonApiController::class, 'update']);
    Route::delete('requests/{id}', [KasbonApiController::class, 'destroy']);

    // Approval
    Route::post('requests/{id}/approve', [KasbonApiController::class, 'approve']);
    Route::post('requests/{id}/reject', [KasbonApiController::class, 'reject']);
    Route::post('requests/{id}/disburse', [KasbonApiController::class, 'disburse']);
    Route::post('requests/bulk-approve', [KasbonApiController::class, 'bulkApprove']);

    // Approvals view
    Route::get('approvals', [KasbonApiController::class, 'approvals']);

    // Installments (Pelunasan)
    Route::get('installments', [KasbonApiController::class, 'installments']);
    Route::post('installments/{id}/pay', [KasbonApiController::class, 'payInstallment']);
    Route::post('installments/bulk-pay', [KasbonApiController::class, 'bulkPay']);
    Route::post('installments/{id}/push', [KasbonApiController::class, 'pushToPayroll']);

    // History (Riwayat)
    Route::get('history', [KasbonApiController::class, 'history']);

    // Utilities
    Route::get('pay-periods', [KasbonApiController::class, 'payPeriods']);
});
