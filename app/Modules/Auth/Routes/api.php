<?php

use App\Modules\Auth\Controllers\Api\V1\AuthApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/user', [AuthApiController::class, 'user']);
    
    // Notifications
    Route::get('/notifications', [\App\Modules\Auth\Controllers\Api\V1\NotificationApiController::class, 'index']);
    Route::post('/notifications/mark-all-read', [\App\Modules\Auth\Controllers\Api\V1\NotificationApiController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/mark-read', [\App\Modules\Auth\Controllers\Api\V1\NotificationApiController::class, 'markAsRead']);
});
