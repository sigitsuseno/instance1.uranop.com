<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Payroll\Controllers\Api\V1\PayPeriodApiController;

Route::prefix('v1/payroll')->middleware(['api'])->group(function () {
    Route::apiResource('periods', PayPeriodApiController::class)->except(['show']);
});
