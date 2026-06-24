<?php

use App\Modules\Supervisor\Controllers\Api\V1\SupervisorDashboardController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorBpjsConfigController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorPphConfigController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorThrConfigController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorScanDetectionConfigController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorDepartmentController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorPositionController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorLeaveSettingController;
use App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/supervisor')
    ->middleware(['auth:sanctum', 'role_or_permission:superadmin|adm_manager|hrbranch|view supervisor dashboard'])
    ->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('/', [SupervisorDashboardController::class, 'index']);
        });

        Route::prefix('master')->group(function () {
            Route::apiResource('work-schedules', SupervisorWorkScheduleController::class);
            Route::post('work-schedules/{id}/activate', [SupervisorWorkScheduleController::class, 'activate']);
            Route::post('work-schedules/{id}/deactivate', [SupervisorWorkScheduleController::class, 'deactivate']);

            Route::get('shifts', [SupervisorShiftController::class, 'index']);
            Route::post('shifts', [SupervisorShiftController::class, 'store']);
            Route::put('shifts/{id}', [SupervisorShiftController::class, 'update']);
            Route::delete('shifts/{id}', [SupervisorShiftController::class, 'destroy']);
        });

        Route::prefix('attendance')->group(function () {
            Route::get('import', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceImportController::class, 'create'])->name('supervisor.attendance.import');
            Route::post('import', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceImportController::class, 'store']);
            
            Route::get('absensi', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'index'])->name('supervisor.attendance.absensi');
            Route::post('absensi/adjustment', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'adjustment']);
            Route::post('absensi/sync', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'sync']);
            Route::get('absensi/export', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'export']);
            Route::get('absensi/print', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'print']);
            Route::get('absensi/{id}', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'show']);
            Route::get('absensi/{id}/print', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceAutologController::class, 'printDetail']);

            Route::get('snapshoot', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceSnapshotController::class, 'index'])->name('supervisor.attendance.snapshot');
            Route::post('snapshoot', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceSnapshotController::class, 'store']);
            Route::post('snapshoot/bulk', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceSnapshotController::class, 'storeBulk']);
            Route::get('snapshoot/print', [\App\Modules\Supervisor\Attendance\Controllers\AttendanceSnapshotController::class, 'print']);

            Route::get('rekap-absensi', [\App\Modules\Supervisor\Attendance\Controllers\RekapAbsensiController::class, 'index'])->name('supervisor.attendance.rekap');
            Route::get('rekap-absensi/print', [\App\Modules\Supervisor\Attendance\Controllers\RekapAbsensiController::class, 'print']);
            Route::get('rekap-absensi/export', [\App\Modules\Supervisor\Attendance\Controllers\RekapAbsensiController::class, 'export']);

            Route::get('lembur-staf', [\App\Modules\Supervisor\Attendance\Controllers\StaffOvertimeController::class, 'index'])->name('supervisor.attendance.lembur');
            Route::get('lembur-staf/print', [\App\Modules\Supervisor\Attendance\Controllers\StaffOvertimeController::class, 'print']);
            Route::get('lembur-staf/{employee_id}', [\App\Modules\Supervisor\Attendance\Controllers\StaffOvertimeController::class, 'show']);
            Route::get('lembur-staf/{employee_id}/print', [\App\Modules\Supervisor\Attendance\Controllers\StaffOvertimeController::class, 'printDetail']);
            Route::post('lembur-staf/adjustment', [\App\Modules\Supervisor\Attendance\Controllers\StaffOvertimeController::class, 'adjustment']);
        });

        Route::prefix('master')->group(function () {
            Route::apiResource('bpjs-configs', SupervisorBpjsConfigController::class);
            Route::post('bpjs-configs/{id}/activate', [SupervisorBpjsConfigController::class, 'activate']);
            Route::post('bpjs-configs/{id}/deactivate', [SupervisorBpjsConfigController::class, 'deactivate']);

            Route::get('pph-configs', [SupervisorPphConfigController::class, 'index']);
            Route::post('pph-configs', [SupervisorPphConfigController::class, 'storeOrUpdate']);

            Route::apiResource('thr-configs', SupervisorThrConfigController::class);
            Route::apiResource('scan-detection', SupervisorScanDetectionConfigController::class);

            Route::get('departments/options', [SupervisorDepartmentController::class, 'options']);
            Route::apiResource('departments', SupervisorDepartmentController::class);

            Route::get('positions/options', [SupervisorPositionController::class, 'options']);
            Route::apiResource('positions', SupervisorPositionController::class);

            Route::prefix('leave-settings')->group(function () {
                Route::get('types', [SupervisorLeaveSettingController::class, 'getTypes']);
                Route::post('types', [SupervisorLeaveSettingController::class, 'storeType']);
                Route::put('types/{id}', [SupervisorLeaveSettingController::class, 'updateType']);
                Route::delete('types/{id}', [SupervisorLeaveSettingController::class, 'destroyType']);

                Route::get('policies', [SupervisorLeaveSettingController::class, 'getPolicies']);
                Route::post('policies', [SupervisorLeaveSettingController::class, 'storePolicy']);
                Route::put('policies/{id}', [SupervisorLeaveSettingController::class, 'updatePolicy']);
                Route::delete('policies/{id}', [SupervisorLeaveSettingController::class, 'destroyPolicy']);

                Route::get('periods', [SupervisorLeaveSettingController::class, 'getPeriods']);
                Route::post('periods', [SupervisorLeaveSettingController::class, 'storePeriod']);
                Route::put('periods/{id}', [SupervisorLeaveSettingController::class, 'updatePeriod']);
                Route::delete('periods/{id}', [SupervisorLeaveSettingController::class, 'destroyPeriod']);
            });
        });

        // Employee Data
        Route::prefix('employee-data')->group(function () {
            // Karyawan
            Route::get('karyawan/stats', [SupervisorEmployeeController::class, 'stats']);
            Route::patch('karyawan/{id}/deactivate', [SupervisorEmployeeController::class, 'deactivate']);
            Route::post('karyawan/{id}/families', [SupervisorEmployeeController::class, 'storeFamily']);
            Route::delete('karyawan/families/{id}', [SupervisorEmployeeController::class, 'destroyFamily']);
            Route::apiResource('karyawan', SupervisorEmployeeController::class);

            // Kontrak Kerja
            Route::get('karyawan/contracts/stats', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'stats']);
            Route::post('karyawan/contracts/import', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'import']);
            Route::get('karyawan/{employee}/contracts', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'index']);
            Route::post('karyawan/{employee}/contracts', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'store']);
            Route::get('karyawan/{employee}/contracts/{contract}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'show']);
            Route::put('karyawan/{employee}/contracts/{contract}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'update']);
            Route::delete('karyawan/{employee}/contracts/{contract}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'destroy']);
            Route::patch('karyawan/{employee}/contracts/{contract}/mark-paid', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'markPaid']);
            Route::patch('karyawan/{employee}/contracts/{contract}/mark-unpaid', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorContractController::class, 'markUnpaid']);
            
            // Gaji Karyawan
            Route::get('salaries', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'globalIndex']);
            Route::post('salaries', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'globalStore']);
            Route::get('salaries/{salary}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'globalShow']);
            Route::put('salaries/{salary}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'globalUpdate']);
            Route::delete('salaries/{salary}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'globalDestroy']);
            Route::post('salaries/import', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'import']);
            Route::get('karyawan/{employee}/salaries', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'index']);
            Route::post('karyawan/{employee}/salaries', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeSalaryController::class, 'store']);

            // Kompensasi
            Route::prefix('kompensasi')->group(function () {
                Route::get('/', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorCompensationController::class, 'index']);
                Route::get('/export', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorCompensationController::class, 'export']);
                Route::get('/print', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorCompensationController::class, 'print']);
                Route::patch('/{contract}/mark-paid', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorCompensationController::class, 'markPaid']);
                Route::patch('/{contract}/mark-unpaid', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorCompensationController::class, 'markUnpaid']);
            });

            // BPJS Karyawan
            Route::prefix('bpjs-karyawan')->group(function () {
                Route::get('/keanggotaan', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorBpjsMembershipController::class, 'index']);
                Route::get('/keanggotaan/{employee}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorBpjsMembershipController::class, 'show']);
                Route::post('/keanggotaan/{employee}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorBpjsMembershipController::class, 'store']);
                Route::put('/keanggotaan/{employee}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorBpjsMembershipController::class, 'update']);
                Route::delete('/keanggotaan/{employee}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorBpjsMembershipController::class, 'destroy']);
            });

            // PPh Karyawan
            Route::get('pph/employees', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeTaxController::class, 'index']);
            Route::put('pph/employees/{id}', [\App\Modules\Supervisor\Controllers\Api\V1\SupervisorEmployeeTaxController::class, 'update']);
        });
    });
