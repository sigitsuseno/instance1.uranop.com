<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Leave\Controllers\Api\V1\LeaveSettingApiController;

Route::prefix('v1/leave')->middleware(['auth:sanctum'])->group(function () {
    // Settings: Types
    Route::get('types', [LeaveSettingApiController::class, 'getTypes']);
    Route::post('types', [LeaveSettingApiController::class, 'storeType']);
    Route::put('types/{type}', [LeaveSettingApiController::class, 'updateType']);
    Route::delete('types/{type}', [LeaveSettingApiController::class, 'destroyType']);

    // Settings: Policies
    Route::get('policies', [LeaveSettingApiController::class, 'getPolicies']);
    Route::post('policies', [LeaveSettingApiController::class, 'storePolicy']);
    Route::put('policies/{policy}', [LeaveSettingApiController::class, 'updatePolicy']);
    Route::delete('policies/{policy}', [LeaveSettingApiController::class, 'destroyPolicy']);

    // Settings: Periods
    Route::get('periods', [LeaveSettingApiController::class, 'getPeriods']);
    Route::post('periods', [LeaveSettingApiController::class, 'storePeriod']);
    Route::put('periods/{period}', [LeaveSettingApiController::class, 'updatePeriod']);
    Route::delete('periods/{period}', [LeaveSettingApiController::class, 'destroyPeriod']);

    // Leave Requests (Transactions)
    Route::get('requests', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'index']);
    Route::post('requests', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'store']);
    Route::put('requests/{id}', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'update']);
    Route::post('requests/{id}/approve', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'approve']);
    Route::post('requests/{id}/reject', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'reject']);
    Route::post('requests/{id}/cancel', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'cancel']);
    Route::post('requests/bulk-approve', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'bulkApprove']);
    Route::post('requests/bulk-reject', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'bulkReject']);
    Route::post('requests/bulk-cancel', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'bulkCancel']);
    Route::post('requests/approve-print', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'approveAndPrint']);
    Route::get('requests/{id}/print', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'printForm']);
    Route::patch('requests/{id}/tanggal-masuk', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'updateTanggalMasuk']);

    // Leave Change Requests
    Route::get('change-requests', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'indexChangeRequests']);
    Route::post('requests/{id}/change', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'storeChangeRequest']);
    Route::post('change-requests/{id}/approve', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'approveChangeRequest']);
    Route::post('change-requests/{id}/reject', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'rejectChangeRequest']);

    // Balances & Transactions
    Route::get('balances', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'balances']);
    Route::get('employee-balance', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'employeeBalance']);
    Route::post('generate-quota', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'generateQuota']);
    Route::post('recap-period', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'recapPeriod']);

    // Export routes
    Route::get('export/requests', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'exportRequests']);
    Route::get('export/balances', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'exportBalances']);
});
