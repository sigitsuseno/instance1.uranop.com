<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Settings\Controllers\Api\V1\SettingsApiController;
use App\Modules\Settings\Controllers\Api\V1\SalaryGradeApiController;
use App\Modules\Settings\Controllers\Api\V1\EmployeeDataApiController;
use App\Modules\Settings\Controllers\Api\V1\PayrollConfigApiController;
use App\Modules\Settings\Controllers\Api\V1\BpjsConfigController;
use App\Modules\Settings\Controllers\Api\V1\ReportConfigApiController;

Route::prefix('v1/settings')->middleware('auth:sanctum')->group(function () {
    Route::get('/general', [SettingsApiController::class, 'getSystemSettings']);
    Route::post('/general', [SettingsApiController::class, 'updateSystemSettings']);
    
    Route::get('/employee', [SettingsApiController::class, 'getEmployeeDefaults']);
    Route::post('/employee', [SettingsApiController::class, 'updateEmployeeDefaults']);

    Route::get('/payroll', [SettingsApiController::class, 'getPayrollSettings']);
    Route::post('/payroll', [SettingsApiController::class, 'updatePayrollSettings']);
    
    Route::get('/work-pattern-types', [SettingsApiController::class, 'getWorkPatternTypes']);
    Route::put('/work-pattern-types/{id}', [SettingsApiController::class, 'updateWorkPatternType']);
    
    Route::apiResource('salary-grades', SalaryGradeApiController::class);

    // Employee Data
    Route::prefix('employee-data')->group(function () {
        Route::get('/categories', [EmployeeDataApiController::class, 'getCategories']);
        Route::post('/categories', [EmployeeDataApiController::class, 'storeCategory']);
        Route::put('/categories/{id}', [EmployeeDataApiController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [EmployeeDataApiController::class, 'destroyCategory']);

        Route::get('/groups', [EmployeeDataApiController::class, 'getGroups']);
        Route::post('/groups', [EmployeeDataApiController::class, 'storeGroup']);
        Route::put('/groups/{id}', [EmployeeDataApiController::class, 'updateGroup']);
        Route::delete('/groups/{id}', [EmployeeDataApiController::class, 'destroyGroup']);

        Route::get('/group-settings', [EmployeeDataApiController::class, 'getGroupSettings']);
        Route::post('/group-settings', [EmployeeDataApiController::class, 'storeGroupSetting']);
        Route::put('/group-settings/{id}', [EmployeeDataApiController::class, 'updateGroupSetting']);
        Route::delete('/group-settings/{id}', [EmployeeDataApiController::class, 'destroyGroupSetting']);
    });

    // Payroll Configs
    Route::prefix('payroll-configs')->group(function () {
        Route::get('/components', [PayrollConfigApiController::class, 'getComponents']);
        Route::post('/components', [PayrollConfigApiController::class, 'storeComponent']);
        Route::put('/components/{id}', [PayrollConfigApiController::class, 'updateComponent']);
        Route::delete('/components/{id}', [PayrollConfigApiController::class, 'destroyComponent']);

        Route::get('/bpjs', [PayrollConfigApiController::class, 'getBpjs']);
        Route::post('/bpjs', [PayrollConfigApiController::class, 'updateBpjs']);

        Route::get('/ptkp', [PayrollConfigApiController::class, 'getPtkp']);
        Route::post('/ptkp', [PayrollConfigApiController::class, 'updatePtkp']);

        Route::get('/pph-config', [PayrollConfigApiController::class, 'getPphConfig']);
        Route::post('/pph-config', [PayrollConfigApiController::class, 'updatePphConfig']);

        Route::get('/ter', [PayrollConfigApiController::class, 'getTer']);
        Route::post('/ter', [PayrollConfigApiController::class, 'updateTer']);
        
        Route::get('/progressive', [PayrollConfigApiController::class, 'getProgressive']);
        Route::post('/progressive', [PayrollConfigApiController::class, 'updateProgressive']);

        Route::get('/work-patterns', [PayrollConfigApiController::class, 'getWorkPatterns']);

        Route::get('/overtime', [PayrollConfigApiController::class, 'getOvertime']);
        Route::post('/overtime', [PayrollConfigApiController::class, 'storeOvertime']);
        Route::put('/overtime/{id}', [PayrollConfigApiController::class, 'updateOvertime']);
        Route::delete('/overtime/{id}', [PayrollConfigApiController::class, 'destroyOvertime']);
        
        Route::get('/thr', [PayrollConfigApiController::class, 'getThrConfigs']);
        Route::post('/thr', [PayrollConfigApiController::class, 'storeThrConfig']);
        Route::put('/thr/{id}', [PayrollConfigApiController::class, 'updateThrConfig']);
        Route::delete('/thr/{id}', [PayrollConfigApiController::class, 'destroyThrConfig']);
    });

    // BPJS Configs (versioned persentase)
    Route::apiResource('bpjs-configs', BpjsConfigController::class);
    Route::post('bpjs-configs/{config}/activate',   [BpjsConfigController::class, 'activate']);
    Route::post('bpjs-configs/{config}/deactivate', [BpjsConfigController::class, 'deactivate']);
    Route::get('bpjs-configs-active',               [BpjsConfigController::class, 'active']);

    // Report Configs
    Route::prefix('report-configs')->group(function () {
        Route::get('/',              [ReportConfigApiController::class, 'index']);
        Route::get('/{reportType}',  [ReportConfigApiController::class, 'show']);
        Route::put('/{reportType}',  [ReportConfigApiController::class, 'update']);
    });
});
