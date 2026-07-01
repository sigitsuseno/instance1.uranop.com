<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Services\AttendanceImportService;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceImportController extends Controller
{
    protected AttendanceImportService $importService;

    public function __construct(AttendanceImportService $importService)
    {
        $this->importService = $importService;
    }

    public function create()
    {
        $periods = PayPeriod::select('id', 'name', 'start_date', 'end_date', 'status')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'periods' => $periods,
        ]);
    }

    /**
     * Import attendance dari att_prepares (filter 6 group, rules per hari).
     * Periode 1-6: overwrite dengan Excel XLSX.
     */
    public function store(Request $request)
    {
        $request->validate([
            'payroll_period_id' => ['required', 'exists:pay_periods,id'],
        ]);

        try {
            $periodId  = (int) $request->input('payroll_period_id');
            $period    = PayPeriod::findOrFail($periodId);

            $result = $this->importService->import(
                startDate: $period->start_date->toDateString(),
                endDate:   $period->end_date->toDateString(),
                periodId:  $periodId,
            );

            Log::info('Import completed', $result);

            $inserted = $result['inserted'] ?? 0;
            $updated  = $result['updated'] ?? 0;
            $skipped  = $result['skipped'] ?? 0;
            $hasExcel = isset($result['excel']);

            $message = "Import: {$inserted} inserted, {$updated} updated";
            if ($skipped > 0) {
                $message .= ", {$skipped} skipped";
            }
            if ($hasExcel) {
                $message .= ' 1';
            }
            $message .= '. Jangan lupa jalankan Perhitungan Lembur!';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Attendance import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import gagal: '.$e->getMessage(),
            ], 500);
        }
    }
}
