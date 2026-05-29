<?php

use App\Modules\Employee\Submodules\Import\Controllers\ImportApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('import')->group(function () {
    Route::get('/template', [ImportApiController::class, 'template'])->name('employees.import.template');
    Route::post('/',        [ImportApiController::class, 'store'])->name('employees.import.store');
});
