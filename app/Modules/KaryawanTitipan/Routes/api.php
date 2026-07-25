<?php

use Illuminate\Support\Facades\Route;
use App\Modules\KaryawanTitipan\Controllers\Api\V1\KaryawanTitipanController;

Route::prefix('v1/karyawan-titipan')->middleware(['auth:sanctum'])->group(function () {
    // Roster — spesifik dulu sebelum wildcard {id}
    Route::get('periods', [KaryawanTitipanController::class, 'periods']);
    Route::post('roster/generate', [KaryawanTitipanController::class, 'generateRoster']);
    Route::post('roster/regenerate', [KaryawanTitipanController::class, 'regenerateRoster']);
    Route::get('roster', [KaryawanTitipanController::class, 'getRoster']);
    Route::put('roster/{id}', [KaryawanTitipanController::class, 'updateRoster']);

    // CRUD Karyawan — wildcard {id} di akhir
    Route::get('/', [KaryawanTitipanController::class, 'index']);
    Route::post('/', [KaryawanTitipanController::class, 'store']);
    Route::get('{id}', [KaryawanTitipanController::class, 'show']);
    Route::put('{id}', [KaryawanTitipanController::class, 'update']);
    Route::delete('{id}', [KaryawanTitipanController::class, 'destroy']);
});
