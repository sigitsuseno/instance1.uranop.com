<?php

use App\Modules\FileManager\Controllers\Api\V1\FileManagerApiController;
use Illuminate\Support\Facades\Route;

/*
 * File Manager — pengelolaan berkas privat.
 *
 * Terbatas untuk superadmin. Berkas disimpan di disk "file_manager"
 * (storage/app/file-manager) dan tidak pernah dilayani lewat URL publik;
 * semua akses melewati endpoint di bawah ini.
 */
Route::prefix('v1/file-manager')->middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    Route::get('/browse', [FileManagerApiController::class, 'index'])->name('file-manager.browse');
    Route::get('/tree', [FileManagerApiController::class, 'tree'])->name('file-manager.tree');

    Route::post('/folders', [FileManagerApiController::class, 'storeFolder'])->name('file-manager.folders.store');
    Route::post('/rename', [FileManagerApiController::class, 'rename'])->name('file-manager.rename');
    Route::post('/move', [FileManagerApiController::class, 'move'])->name('file-manager.move');
    Route::post('/delete', [FileManagerApiController::class, 'destroy'])->name('file-manager.delete');
    Route::post('/upload', [FileManagerApiController::class, 'upload'])->name('file-manager.upload');

    Route::get('/download', [FileManagerApiController::class, 'download'])->name('file-manager.download');
    Route::get('/preview', [FileManagerApiController::class, 'preview'])->name('file-manager.preview');
    Route::get('/excel', [FileManagerApiController::class, 'excel'])->name('file-manager.excel');
});
