<?php

use App\Modules\Sync\Controllers\Api\V1\SyncApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sync API — Desktop App Endpoints
|--------------------------------------------------------------------------
|
| Endpoint untuk sync data antara server (hrisweb) dan desktop (Tauri app).
|
| PRE-AUTH (tanpa token):
|   POST   /api/sync/activate        — Aktivasi lisensi desktop
|   GET    /api/sync/license-status  — Cek status lisensi
|
| AUTH (dengan desktop.token):
|   GET    /api/sync/organization    — PULL data organisasi (kompatibilitas)
|   GET    /api/sync/settings        — PULL data settings
|   GET    /api/sync/users           — PULL data users
|   GET    /api/sync/permissions     — PULL data permissions
|   GET    /api/sync/changes         — Daftar perubahan per modul
|   GET    /api/sync/{module}        — PULL data per modul (dengan ?since=)
|   POST   /api/sync/{module}/batch  — PUSH data dari desktop
|
*/

// === Pre-auth (tanpa token) ===
Route::prefix('sync')->group(function () {
    Route::post('/activate', [SyncApiController::class, 'activate']);
    Route::get('/license-status', [SyncApiController::class, 'licenseStatus']);
});

// === Auth required — via desktop.token middleware ===
Route::prefix('sync')->middleware(['desktop.token'])->group(function () {
    // Kompatibilitas (endpoint spesifik)
    Route::get('/organization', [SyncApiController::class, 'organization']);
    Route::get('/settings', [SyncApiController::class, 'settings']);
    Route::get('/users', [SyncApiController::class, 'users']);
    Route::get('/permissions', [SyncApiController::class, 'permissions']);

    // Generic module endpoints
    Route::get('/changes', [SyncApiController::class, 'changes']);
    Route::get('/quick-changes', [SyncApiController::class, 'quickChanges']);
    Route::get('{module}', [SyncApiController::class, 'pull'])
        ->where('module', '[a-z-]+');
    Route::post('{module}/batch', [SyncApiController::class, 'push'])
        ->where('module', '[a-z-]+');
});
