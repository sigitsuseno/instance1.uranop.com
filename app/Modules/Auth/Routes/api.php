<?php

use App\Modules\Auth\Controllers\Api\V1\AuthApiController;
use App\Modules\Auth\Controllers\Api\V1\AdminManagementApiController;
use App\Modules\Auth\Controllers\Api\V1\RolePermissionApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthApiController::class, 'login']);

// Mobile login — khusus karyawan
Route::post('/mobile/login', [AuthApiController::class, 'mobileLogin'])->name('mobile.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/user', [AuthApiController::class, 'user']);

    // === Desktop Sync Endpoints ===
    Route::get('/users', [AuthApiController::class, 'listUsers']);
    Route::get('/permissions', [RolePermissionApiController::class, 'indexPermissions']);

    // Notifications
    Route::get('/notifications', [\App\Modules\Auth\Controllers\Api\V1\NotificationApiController::class, 'index']);
    Route::post('/notifications/mark-all-read', [\App\Modules\Auth\Controllers\Api\V1\NotificationApiController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/mark-read', [\App\Modules\Auth\Controllers\Api\V1\NotificationApiController::class, 'markAsRead']);
    
    // Admin Management (Superadmin Only)
    Route::middleware('role:superadmin')->group(function () {
        Route::apiResource('admins', AdminManagementApiController::class);
        Route::post('admins/{admin}/sync-roles', [AdminManagementApiController::class, 'syncRoles']);
        
        // Roles & Permissions
        Route::get('roles', [RolePermissionApiController::class, 'indexRoles']);
        Route::post('roles', [RolePermissionApiController::class, 'storeRole']);
        Route::put('roles/{id}', [RolePermissionApiController::class, 'updateRole']);
        Route::delete('roles/{id}', [RolePermissionApiController::class, 'destroyRole']);
        
        Route::get('permissions', [RolePermissionApiController::class, 'indexPermissions']);
        Route::post('permissions', [RolePermissionApiController::class, 'storePermission']);
        Route::put('permissions/{id}', [RolePermissionApiController::class, 'updatePermission']);
        Route::delete('permissions/{id}', [RolePermissionApiController::class, 'destroyPermission']);
    });
});
