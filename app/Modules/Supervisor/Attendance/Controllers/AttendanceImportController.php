<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Actions\ImportAttendanceData;
use App\Modules\Supervisor\Attendance\Imports\AttendanceDataFixImport;
use App\Modules\Supervisor\Attendance\Services\AttendanceOvertimeSyncService;
use App\Modules\Supervisor\Attendance\Services\AuditorLogService;
use App\Modules\Payroll\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AttendanceImportController extends Controller
{
    protected AuditorLogService $auditorLogService;

    protected AttendanceOvertimeSyncService $overtimeSyncService;

    public function __construct(AuditorLogService $auditorLogService, AttendanceOvertimeSyncService $overtimeSyncService)
    {
        $this->auditorLogService = $auditorLogService;
        $this->overtimeSyncService = $overtimeSyncService;
    }

    public function create()
    {
        $periods = PayrollPeriod::select('id', 'name', 'code', 'start_date', 'end_date', 'status')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'periods' => $periods,
        ]);
    }

    public function store(Request $request, ImportAttendanceData $importer)
    {

        $request->validate([
            'payroll_period_id' => ['required', 'exists:payroll_periods,id'],
            'attendance_file' => ['required', 'file'],
        ]);

        $file = $request->file('attendance_file');
        if ($file->getClientOriginalExtension() !== 'bin' && $file->getClientOriginalExtension() !== 'dat') {
            return back()->withErrors(['attendance_file' => 'File harus berformat .bin atau .dat']);
        }

        // Validate session values
        if (! session('company_id') || ! session('branch_id')) {
            return back()->withErrors(['error' => 'Company ID atau Branch ID tidak ditemukan di session. Silakan login kembali.']);
        }

        $periodFileMap = [
            1 => 'data_januari.xlsx',
            2 => 'februari.xlsx',
            3 => 'sampe_data.xlsx',
            4 => 'april.xlsx',
        ];

        try {

            if (isset($periodFileMap[$request->input('payroll_period_id')])) {
                $filePath = $periodFileMap[$request->input('payroll_period_id')];
                $period = PayrollPeriod::findOrFail($request->input('payroll_period_id'));

                $result = AttendanceDataFixImport::runImport(
                    companyId: session('company_id'),
                    branchId: session('branch_id'),
                    userId: Auth::user()->id,
                    filePath: $filePath
                );

                $syncResult = $this->overtimeSyncService->sync(
                    companyId: session('company_id'),
                    startDate: $period->start_date->toDateString(),
                    endDate: $period->end_date->toDateString(),
                    branchId: session('branch_id')
                );

                Log::info('Overtime sync result', $syncResult);
            } elseif (in_array($request->input('payroll_period_id'), ['5', '6'])) {
                $period = PayrollPeriod::findOrFail($request->input('payroll_period_id'));

                Log::info('Generating Auditor Logs via Service', [
                    'payroll_period_id' => $period->id,
                    'start_date' => $period->start_date,
                    'end_date' => $period->end_date,
                ]);

                $logResult = $this->auditorLogService->generateLogs(
                    companyId: session('company_id'),
                    startDate: $period->start_date,
                    endDate: $period->end_date,
                    branchId: session('branch_id')
                );

                $result = [
                    'inserted' => $logResult['processed'] ?? 0,
                    'updated' => 0,
                ];
            }

            return redirect()->route('attendance.absensi.index')->with('success', "Import completed: {$result['inserted']} records inserted, {$result['updated']} records updated.");
        } catch (\Exception $e) {
            return redirect()->route('attendance.absensi.index')->with('error', 'Failed to import attendance data: '.$e->getMessage());
        }
    }
}
