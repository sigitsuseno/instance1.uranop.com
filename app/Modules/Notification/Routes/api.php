<?php

use App\Modules\Notification\Controllers\Api\V1\NotificationApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationApiController::class, 'index']);
    Route::post('/{id}/mark-read', [NotificationApiController::class, 'markAsRead']);
    Route::post('/mark-all-read', [NotificationApiController::class, 'markAllAsRead']);
});
