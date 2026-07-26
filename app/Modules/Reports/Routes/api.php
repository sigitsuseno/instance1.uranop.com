<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reports\Controllers\Api\V1\AttendanceReportController;
use App\Modules\Reports\Controllers\Api\V1\LaporanLemburController;
use App\Modules\Reports\Controllers\Api\V1\UangMakanReportController;
use App\Modules\Reports\Controllers\Api\V1\PphReportController;
use App\Modules\Reports\Controllers\Api\V1\RekapGajiController;
use App\Modules\Reports\Controllers\Api\V1\RekapKerjaController;
use App\Modules\Reports\Controllers\Api\V1\RekapPphKompensasiController;

// Note: Laravel already prefixes with /api, so NO prefix needed here
Route::prefix('v1/reports')->name('api.reports.')->group(function () {

    // --- Lembur ---
    Route::prefix('lembur')->name('lembur.')->group(function () {
        Route::get('/harian', [LaporanLemburController::class, 'harian']);
        Route::get('/harian/export', [LaporanLemburController::class, 'exportHarian']);
        Route::get('/harian/print', [LaporanLemburController::class, 'printHarian']);
        Route::get('/bulanan', [LaporanLemburController::class, 'bulanan']);
        Route::get('/bulanan/export', [LaporanLemburController::class, 'exportBulanan']);
        Route::get('/bulanan/print', [LaporanLemburController::class, 'printBulanan']);
        Route::get('/resume', [LaporanLemburController::class, 'resume']);
        Route::get('/resume/export', [LaporanLemburController::class, 'exportResume']);
        Route::get('/resume/print', [LaporanLemburController::class, 'printResume']);

        // Combined (Lembur + Uang Makan)
        Route::get('/combined-detail', [LaporanLemburController::class, 'combinedDetail']);
        Route::get('/combined-detail/export', [LaporanLemburController::class, 'exportCombinedDetail']);
        Route::get('/combined-detail-pre', [LaporanLemburController::class, 'combinedDetailPre']);
        Route::get('/combined-detail-pre/export', [LaporanLemburController::class, 'exportCombinedDetailPre']);
        Route::get('/combined-resume', [LaporanLemburController::class, 'combinedResume']);
        Route::get('/combined-resume/export', [LaporanLemburController::class, 'exportCombinedResume']);

        // Update Data
        Route::post('/update-data', [LaporanLemburController::class, 'updateData']);
        Route::post('/combined-detail-pre/insentif', [LaporanLemburController::class, 'saveInsentif']);
    });

    // --- Uang Makan ---
    Route::prefix('uang-makan')->name('uang-makan.')->group(function () {
        Route::get('/harian', [UangMakanReportController::class, 'harian']);
        Route::get('/harian/export', [UangMakanReportController::class, 'exportHarian']);
        Route::get('/harian/print', [UangMakanReportController::class, 'printHarian']);
        Route::get('/bulanan', [UangMakanReportController::class, 'bulanan']);
        Route::get('/bulanan/export', [UangMakanReportController::class, 'exportBulanan']);
        Route::get('/bulanan/print', [UangMakanReportController::class, 'printBulanan']);
        Route::get('/resume', [UangMakanReportController::class, 'resume']);
        Route::get('/resume/export', [UangMakanReportController::class, 'exportResume']);
        Route::get('/resume/print', [UangMakanReportController::class, 'printResume']);
        Route::get('/rekab', [UangMakanReportController::class, 'rekab']);
        Route::get('/rekab/export', [UangMakanReportController::class, 'exportRekab']);
        Route::get('/rekab/print', [UangMakanReportController::class, 'printRekab']);
        Route::get('/rekab-resume', [UangMakanReportController::class, 'rekabResume']);
        Route::get('/rekab-resume/export', [UangMakanReportController::class, 'exportRekabResume']);
        Route::get('/rekab-resume/print', [UangMakanReportController::class, 'printRekabResume']);
    });

    // --- PPh 21 ---
    Route::get('/pph', [PphReportController::class, 'index'])->name('pph');

    // --- Rekap Gaji ---
    Route::get('/rekap-gaji', [RekapGajiController::class, 'index'])->name('rekap-gaji');
    Route::get('/rekap-gaji/export', [RekapGajiController::class, 'export'])->name('rekap-gaji.export');
    Route::get('/rekap-gaji/groups', [RekapGajiController::class, 'groups'])->name('rekap-gaji.groups');

    // --- Rekap Kerja ---
    Route::get('/rekap-kerja', [RekapKerjaController::class, 'index'])->name('rekap-kerja');
    Route::get('/rekap-kerja/groups', [RekapKerjaController::class, 'groups'])->name('rekap-kerja.groups');
    Route::get('/rekap-kerja/extra-employees', [RekapKerjaController::class, 'extraEmployees'])->name('rekap-kerja.extra-employees');

    // --- Rekap PPH & Kompensasi ---
    Route::get('/rekap-pph-kompensasi', [RekapPphKompensasiController::class, 'index'])->name('rekap-pph-kompensasi');
    Route::get('/rekap-pph-kompensasi/groups', [RekapPphKompensasiController::class, 'groups'])->name('rekap-pph-kompensasi.groups');

});

Route::prefix('v1/laporan')->name('api.laporan.')->group(function () {

    Route::get('/kehadiran', [AttendanceReportController::class, 'index'])
        ->name('kehadiran');
    Route::get('/kehadiran/export', [AttendanceReportController::class, 'export'])
        ->name('kehadiran.export');

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/kirim-audit', [\App\Modules\Reports\Controllers\Api\V1\PayrollReportController::class, 'kirimAudit'])->name('kirim-audit');
        Route::get('/detail', [\App\Modules\Reports\Controllers\Api\V1\PayrollReportController::class, 'payroll'])->name('detail');
        Route::get('/detail/export', [\App\Modules\Reports\Controllers\Api\V1\PayrollReportController::class, 'exportPayroll'])->name('detail.export');
        Route::get('/resume', [\App\Modules\Reports\Controllers\Api\V1\PayrollReportController::class, 'resume'])->name('resume');
        Route::get('/resume/export', [\App\Modules\Reports\Controllers\Api\V1\PayrollReportController::class, 'exportResume'])->name('resume.export');
    });

});
