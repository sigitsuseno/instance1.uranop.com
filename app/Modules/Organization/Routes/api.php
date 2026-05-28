<?php

use App\Modules\Organization\Controllers\Api\V1\OrganizationApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('organization')->group(function () {
    // Departments
    Route::get('/departments', [OrganizationApiController::class, 'indexDepartments']);
    Route::post('/departments', [OrganizationApiController::class, 'storeDepartment']);
    Route::get('/departments/{id}', [OrganizationApiController::class, 'showDepartment']);
    Route::put('/departments/{id}', [OrganizationApiController::class, 'updateDepartment']);
    Route::delete('/departments/{id}', [OrganizationApiController::class, 'destroyDepartment']);
    
    // Positions
    Route::get('/positions', [OrganizationApiController::class, 'indexPositions']);
    Route::post('/positions', [OrganizationApiController::class, 'storePosition']);
    Route::get('/positions/{id}', [OrganizationApiController::class, 'showPosition']);
    Route::put('/positions/{id}', [OrganizationApiController::class, 'updatePosition']);
    Route::delete('/positions/{id}', [OrganizationApiController::class, 'destroyPosition']);

    // Company Profile (Single Record)
    Route::get('/company-profile', [\App\Modules\Organization\Controllers\Api\V1\CompanyProfileApiController::class, 'show']);
    Route::post('/company-profile', [\App\Modules\Organization\Controllers\Api\V1\CompanyProfileApiController::class, 'update']);
});
