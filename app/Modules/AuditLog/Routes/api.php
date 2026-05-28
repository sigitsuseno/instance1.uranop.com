<?php

use App\Modules\AuditLog\Controllers\Api\V1\AuditLogApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('audit-logs')->group(function () {
    Route::get('/', [AuditLogApiController::class, 'index']);
});
