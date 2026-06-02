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
    Route::post('requests/{id}/approve', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'approve']);
    Route::post('requests/{id}/reject', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'reject']);
    Route::post('requests/{id}/cancel', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'cancel']);
    
    // New Transactions & Period closure routes
    Route::get('balances', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'balances']);
    Route::post('generate-quota', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'generateQuota']);
    Route::post('recap-period', [\App\Modules\Leave\Controllers\Api\V1\LeaveApiController::class, 'recapPeriod']);
});
