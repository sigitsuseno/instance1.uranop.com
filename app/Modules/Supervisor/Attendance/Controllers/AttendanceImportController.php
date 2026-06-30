<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Imports\AttendanceDataFixImport;
use App\Modules\Supervisor\Attendance\Services\AttendanceImportFromPrepares;
use App\Modules\Supervisor\Attendance\Services\AttendanceOvertimeSyncService;
use App\Modules\Supervisor\Attendance\Services\SupervisorAttPrepareSync;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttendanceImportController extends Controller
{
    protected AttendanceOvertimeSyncService $overtimeSyncService;

    protected AttendanceImportFromPrepares $importFromPrepares;

    protected SupervisorAttPrepareSync $attPrepareSync;

    public function __construct(
        AttendanceOvertimeSyncService $overtimeSyncService,
        AttendanceImportFromPrepares $importFromPrepares,
        SupervisorAttPrepareSync $attPrepareSync
    ) {
        $this->overtimeSyncService = $overtimeSyncService;
        $this->importFromPrepares = $importFromPrepares;
        $this->attPrepareSync = $attPrepareSync;
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

    public function store(Request $request)
    {
        $request->validate([
            'payroll_period_id' => ['required', 'exists:pay_periods,id'],
            'attendance_file' => ['required', 'file'],
        ]);

        $file = $request->file('attendance_file');
        if ($file->getClientOriginalExtension() !== 'bin' && $file->getClientOriginalExtension() !== 'dat') {
            return response()->json(['errors' => ['attendance_file' => 'File harus berformat .bin atau .dat']], 422);
        }

        // Periode 1-6: file XLSX hardcoded (data REAL input manual sebelum sistem)
        $periodFileMap = [
            1 => 'data_januari.xlsx',
            2 => 'februari.xlsx',
            3 => 'sampe_data.xlsx',
            4 => 'april.xlsx',
            5 => 'mei.xlsx',
            6 => 'juni.xlsx',
        ];

        try {
            $periodId = (int) $request->input('payroll_period_id');
            $period = PayPeriod::findOrFail($periodId);

            if (isset($periodFileMap[$periodId])) {
                // PERIODE 1-4: Import dari XLSX hardcoded
                $filePath = $periodFileMap[$periodId];

                $result = AttendanceDataFixImport::runImport(
                    userId: Auth::user()->id,
                    filePath: $filePath
                );

                Log::info('Import result', [
                    'inserted' => $result['inserted'] ?? 0,
                    'updated' => $result['updated'] ?? 0,
                    'warnings_count' => count($result['warnings'] ?? []),
                    'errors_count' => count($result['errors'] ?? []),
                ]);

                if (!empty($result['warnings'])) {
                    Log::warning('Import warnings', $result['warnings']);
                }
                if (!empty($result['errors'])) {
                    Log::error('Import errors', $result['errors']);
                }

                $syncResult = $this->overtimeSyncService->sync(
                    startDate: $period->start_date->toDateString(),
                    endDate: $period->end_date->toDateString(),
                );

                Log::info('Overtime sync result', $syncResult);
            } elseif ($periodId >= 7 && $periodId <= 12) {
                // PERIODE 7-12: Ambil dari att_prepares (data REAL dari fingerprint sync)
                $result = $this->importFromPrepares->import(
                    startDate: $period->start_date->toDateString(),
                    endDate: $period->end_date->toDateString(),
                );

                Log::info('Import from prepares result', $result);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Periode {$periodId} tidak didukung. Hanya periode 1-12 yang tersedia.",
                ], 422);
            }

            // ── Sync dari att_prepares (GRP-JKT only utk 2026 per 1-6) ──
            $syncResult = $this->attPrepareSync->sync(
                periodId: $periodId,
                startDate: $period->start_date->toDateString(),
                endDate: $period->end_date->toDateString(),
            );
            Log::info('AttPrepareSync result', $syncResult);

            // Build message
            $inserted = $result['inserted'] ?? 0;
            $updated = $result['updated'] ?? 0;
            $skipped = $result['skipped'] ?? null;

            $syncInserted = $syncResult['inserted'] ?? 0;
            $syncUpdated = $syncResult['updated'] ?? 0;

            $message = "Import: {$inserted} inserted, {$updated} updated.";
            if ($skipped !== null) {
                $message .= " {$skipped} skipped.";
            }
            $message .= " | Sync: {$syncInserted} inserted, {$syncUpdated} updated.";

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('Attendance import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import attendance data: '.$e->getMessage(),
            ], 500);
        }
    }
}
