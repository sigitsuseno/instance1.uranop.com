<?php

use App\Modules\Sync\Controllers\Api\V1\SyncApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sync API — Desktop App Endpoints
|--------------------------------------------------------------------------
|
| Endpoint untuk sync data dari server ke desktop (Tauri app).
| Desktop melakukan PULL (download) data dari endpoint ini.
|
*/

// Pre-auth — desktop activation
Route::prefix('sync')->group(function () {
    Route::post('/activate', [SyncApiController::class, 'activate']);
    Route::get('/license-status', [SyncApiController::class, 'licenseStatus']);
});

// Auth required — data sync
Route::prefix('sync')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/organization', [SyncApiController::class, 'organization']);
    Route::get('/settings', [SyncApiController::class, 'settings']);
});
