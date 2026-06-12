<?php

use App\Modules\Employee\Controllers\Api\V1\Bpjs\BpjsApiController;
use App\Modules\Employee\Controllers\Api\V1\Bpjs\BpjsEmployeeController;
use App\Modules\Employee\Controllers\Api\V1\Contract\ContractApiController;
use App\Modules\Employee\Controllers\Api\V1\Document\DocumentApiController;
use App\Modules\Employee\Controllers\Api\V1\EmployeeApiController;
use App\Modules\Employee\Controllers\Api\V1\Family\FamilyApiController;
use App\Modules\Employee\Controllers\Api\V1\PositionHistory\PositionHistoryApiController;
use App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController;
use App\Modules\Employee\Controllers\Api\V1\SalaryComponent\SalaryComponentApiController;
use App\Modules\Employee\Controllers\Api\V1\Termination\TerminationApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ========== EMPLOYEES ==========

    Route::prefix('employees')->group(function () {

        // Utility endpoints (harus SEBELUM {employee} agar tidak konflik)
        Route::get('/stats',   [EmployeeApiController::class, 'stats'])->name('employees.stats');
        Route::get('/options', [EmployeeApiController::class, 'options'])->name('employees.options');

        // CRUD utama
        Route::get('/',    [EmployeeApiController::class, 'index'])->name('employees.index');
        Route::post('/',   [EmployeeApiController::class, 'store'])->name('employees.store');

        // Global Contracts
        Route::prefix('contracts')->group(function () {
            Route::get('/stats', [ContractApiController::class, 'stats'])->name('employees.contracts.global.stats');
            Route::post('/import', [ContractApiController::class, 'import'])->name('employees.contracts.global.import');
        });

        // Global Salaries
        Route::prefix('salaries')->group(function () {
            Route::get('/', [\App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController::class, 'globalIndex'])->name('employees.salaries.global.index');
            Route::post('/', [\App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController::class, 'globalStore'])->name('employees.salaries.global.store');
            Route::post('/import', [\App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController::class, 'import'])->name('employees.salaries.global.import');
            Route::get('/{salary}', [\App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController::class, 'globalShow'])->name('employees.salaries.global.show');
            Route::put('/{salary}', [\App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController::class, 'globalUpdate'])->name('employees.salaries.global.update');
            Route::delete('/{salary}', [\App\Modules\Employee\Controllers\Api\V1\Salary\SalaryApiController::class, 'globalDestroy'])->name('employees.salaries.global.destroy');
        });

        // Compensation
        Route::prefix('compensation')->group(function () {
            Route::get('/', [\App\Modules\Employee\Controllers\Api\V1\Compensation\CompensationApiController::class, 'index'])->name('employees.compensation.index');
            Route::get('/export', [\App\Modules\Employee\Controllers\Api\V1\Compensation\CompensationApiController::class, 'export'])->name('employees.compensation.export');
            Route::get('/print', [\App\Modules\Employee\Controllers\Api\V1\Compensation\CompensationApiController::class, 'print'])->name('employees.compensation.print');
            Route::patch('/{contract}/mark-paid', [\App\Modules\Employee\Controllers\Api\V1\Compensation\CompensationApiController::class, 'markPaid'])->name('employees.compensation.mark-paid');
            Route::patch('/{contract}/mark-unpaid', [\App\Modules\Employee\Controllers\Api\V1\Compensation\CompensationApiController::class, 'markUnpaid'])->name('employees.compensation.mark-unpaid');
        });

        // Grouping & Kanban
        Route::prefix('grouping')->group(function () {
            Route::get('/', [\App\Modules\Employee\Controllers\Api\V1\Grouping\EmployeeGroupingApiController::class, 'index'])->name('employees.grouping.index');
            Route::post('/bulk-update', [\App\Modules\Employee\Controllers\Api\V1\Grouping\EmployeeGroupingApiController::class, 'bulkUpdate'])->name('employees.grouping.bulk-update');
            Route::post('/auto-enroll', [\App\Modules\Employee\Controllers\Api\V1\Grouping\EmployeeGroupingApiController::class, 'autoEnroll'])->name('employees.grouping.auto-enroll');
            Route::get('/export', [\App\Modules\Employee\Controllers\Api\V1\Grouping\EmployeeGroupingApiController::class, 'export'])->name('employees.grouping.export');
            Route::post('/preview-import', [\App\Modules\Employee\Controllers\Api\V1\Grouping\EmployeeGroupingApiController::class, 'previewImport'])->name('employees.grouping.preview-import');
            Route::post('/import', [\App\Modules\Employee\Controllers\Api\V1\Grouping\EmployeeGroupingApiController::class, 'processImport'])->name('employees.grouping.import');
        });

        // Submodules
        require __DIR__ . '/../Submodules/Import/Routes/api.php';


        Route::prefix('{employee}')->group(function () {
            Route::get('/',    [EmployeeApiController::class, 'show'])->name('employees.show');
            Route::put('/',    [EmployeeApiController::class, 'update'])->name('employees.update');
            Route::delete('/', [EmployeeApiController::class, 'destroy'])->name('employees.destroy');
            Route::patch('/toggle-status', [EmployeeApiController::class, 'toggleStatus'])->name('employees.toggle-status');
            Route::patch('/deactivate', [EmployeeApiController::class, 'deactivate'])->name('employees.deactivate');

            // Sub-resource: Contracts
            Route::prefix('contracts')->group(function () {
                Route::get('/',      [ContractApiController::class, 'index'])->name('employees.contracts.index');
                Route::post('/',     [ContractApiController::class, 'store'])->name('employees.contracts.store');
                Route::get('/{contract}',    [ContractApiController::class, 'show'])->name('employees.contracts.show');
                Route::put('/{contract}',    [ContractApiController::class, 'update'])->name('employees.contracts.update');
                Route::delete('/{contract}', [ContractApiController::class, 'destroy'])->name('employees.contracts.destroy');
                Route::patch('/{contract}/mark-paid',   [ContractApiController::class, 'markPaid'])->name('employees.contracts.mark-paid');
                Route::patch('/{contract}/mark-unpaid', [ContractApiController::class, 'markUnpaid'])->name('employees.contracts.mark-unpaid');
            });

            // Sub-resource: Families
            Route::prefix('families')->group(function () {
                Route::get('/',          [FamilyApiController::class, 'index'])->name('employees.families.index');
                Route::post('/',         [FamilyApiController::class, 'store'])->name('employees.families.store');
                Route::put('/{family}',  [FamilyApiController::class, 'update'])->name('employees.families.update');
                Route::delete('/{family}', [FamilyApiController::class, 'destroy'])->name('employees.families.destroy');
            });

            // Sub-resource: Documents
            Route::prefix('documents')->group(function () {
                Route::get('/',             [DocumentApiController::class, 'index'])->name('employees.documents.index');
                Route::post('/',            [DocumentApiController::class, 'store'])->name('employees.documents.store');
                Route::delete('/{document}',[DocumentApiController::class, 'destroy'])->name('employees.documents.destroy');
            });

            // Sub-resource: Salaries (history)
            Route::prefix('salaries')->group(function () {
                Route::get('/',  [SalaryApiController::class, 'index'])->name('employees.salaries.index');
                Route::post('/', [SalaryApiController::class, 'store'])->name('employees.salaries.store');
            });

            // Sub-resource: Salary Components
            Route::prefix('salary-components')->group(function () {
                Route::get('/',              [SalaryComponentApiController::class, 'index'])->name('employees.salary-components.index');
                Route::post('/',             [SalaryComponentApiController::class, 'store'])->name('employees.salary-components.store');
                Route::put('/{component}',   [SalaryComponentApiController::class, 'update'])->name('employees.salary-components.update');
            });

            // Sub-resource: Position Histories
            Route::prefix('position-histories')->group(function () {
                Route::get('/',  [PositionHistoryApiController::class, 'index'])->name('employees.position-histories.index');
                Route::post('/', [PositionHistoryApiController::class, 'store'])->name('employees.position-histories.store');
            });

            // Sub-resource: Terminations
            Route::prefix('terminations')->group(function () {
                Route::get('/',                    [TerminationApiController::class, 'index'])->name('employees.terminations.index');
                Route::post('/',                   [TerminationApiController::class, 'store'])->name('employees.terminations.store');
                Route::patch('/{termination}/approve', [TerminationApiController::class, 'approve'])->name('employees.terminations.approve');
                Route::patch('/{termination}/reject',  [TerminationApiController::class, 'reject'])->name('employees.terminations.reject');
            });

            // Sub-resource: BPJS
            Route::prefix('bpjs')->group(function () {
                Route::get('/',      [BpjsApiController::class, 'show'])->name('employees.bpjs.show');
                Route::post('/',     [BpjsApiController::class, 'store'])->name('employees.bpjs.store');
                Route::put('/{bpjs}',[BpjsApiController::class, 'update'])->name('employees.bpjs.update');
            });
        });
    });

    // ========== BPJS STANDALONE ==========
    // Keanggotaan & Iuran BPJS — halaman independen
    Route::prefix('bpjs')->group(function () {
        Route::get('/keanggotaan', [BpjsEmployeeController::class, 'index']);
        Route::get('/keanggotaan/{employee}', [BpjsEmployeeController::class, 'show']);
        Route::post('/keanggotaan/{employee}', [BpjsEmployeeController::class, 'store']);
        Route::put('/keanggotaan/{employee}', [BpjsEmployeeController::class, 'update']);
        Route::delete('/keanggotaan/{employee}', [BpjsEmployeeController::class, 'destroy']);
        Route::post('/generate-iuran', [BpjsEmployeeController::class, 'generateIuran']);
        Route::get('/iuran', [BpjsEmployeeController::class, 'iuranIndex']);
        Route::get('/reports', [BpjsEmployeeController::class, 'reports']);
    });

});
