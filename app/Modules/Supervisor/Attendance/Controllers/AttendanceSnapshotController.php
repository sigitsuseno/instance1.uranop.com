<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendanceSnapshot;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendanceSnapshot as AttendanceSnapshot;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Organization\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceSnapshotController extends Controller
{
    public function index(Request $request)
    {
        $userType = Auth::user()->user_type;

        // Get period
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod($request->input('period'));
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        // Fetch payroll periods for period selector (no company/branch filter)
        $payrollPeriods = PayPeriod::orderBy('start_date', 'desc')->get();

        // Default to latest payroll period if no dates specified
        $selectedPeriodId = null;
        if (! $request->has('start_date') && ! $request->has('end_date') && $payrollPeriods->isNotEmpty()) {
            $latestPeriod = $payrollPeriods->first();
            $startDate = $latestPeriod->start_date->toDateString();
            $endDate = $latestPeriod->end_date->toDateString();
            $selectedPeriodId = $latestPeriod->id;
        }

        // Resolve pay_period_id dari date range (untuk cek existing snapshot)
        if (! $selectedPeriodId) {
            $matchedPeriod = PayPeriod::where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->first();
            $selectedPeriodId = $matchedPeriod?->id;
        }

        $leaveTypeIds = LeaveType::all();
        $cutiTypeIds = $leaveTypeIds->where('category', 'leave')->pluck('id');
        $izinTypeIds = $leaveTypeIds->where('category', 'permit')->pluck('id');
        $sakitTypeId = $leaveTypeIds->firstWhere('category', 'sick')?->id;

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        // Ambil employee ID dari supervisor_employee_groups per periode
        $groupEmployeeIds = \App\Modules\Supervisor\Models\SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->pluck('employee_id')
            ->unique()
            ->values();

        // Build base query: hanya employee yang terdaftar di supervisor_employee_groups
        $employeesQuery = Employee::whereIn('id', $groupEmployeeIds)
            ->with([
                'department',
                'position',
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate])->with('leave.leaveType');
                },
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code');

        // Get all employees for stats (no pagination)
        $allEmployees = (clone $employeesQuery)->get();
        $allEmployeeIds = $allEmployees->pluck('id');

        $allLeaveRequests = LeaveRequest::whereIn('employee_id', $allEmployeeIds)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Transform all employees for stats calculation
        $allSummaryData = [];
        $totalActualOvertime = 0;
        $totalCalculatedOvertime = 0;
        foreach ($allEmployees as $employee) {
            $logs = $employee->autologs;
            $employeeLeaves = $allLeaveRequests[$employee->id] ?? collect();
            $presentDays = $logs->where('status', 'present')->count();
            $absentDays = $logs->where('status', 'absent')->count();
            $offDays = $logs->where('status', 'off')->count();
            $holidayDays = $logs->where('status', 'holiday')->count();

            $leaveDays = $employeeLeaves->whereIn('leave_type_id', $cutiTypeIds)->sum('days_requested');
            $permitDays = $employeeLeaves->whereIn('leave_type_id', $izinTypeIds)->sum('days_requested');
            $sickDays = $employeeLeaves->where('leave_type_id', $sakitTypeId)->sum('days_requested');

            $lateDays = $logs->where('late_duration', '>', 0)->count();
            $totalLateMinutes = $logs->sum('late_duration');
            $totalEarlyLeaveMinutes = $logs->sum('early_leave_duration');
            $totalOvertimeMinutes = $logs->sum('lembur');

            $overtimeHours = $totalOvertimeMinutes / 60;
            $calculatedOvertime = $logs->sum('lembur_calc') + $logs->sum('lm_calc');

            $totalActualOvertime += $overtimeHours;
            $totalCalculatedOvertime += $calculatedOvertime;

            $allSummaryData[] = [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'employment_status' => $employee->employment_status,
                'position' => $employee->position?->name,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'permit_days' => $permitDays,
                'sick_days' => $sickDays,
                'off_days' => $offDays,
                'holiday_days' => $holidayDays,
                'late_days' => $lateDays,
                'total_late_minutes' => $totalLateMinutes,
                'total_early_leave_minutes' => $totalEarlyLeaveMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'overtime_hours' => round($overtimeHours, 2),
                'calculated_overtime' => round($calculatedOvertime, 2),
            ];
        }

        // Stats from ALL employees (not affected by pagination)
        $stats = [
            'total_employees' => count($allSummaryData),
            'present' => collect($allSummaryData)->sum('present_days'),
            'absent' => collect($allSummaryData)->sum('absent_days'),
            'leave' => collect($allSummaryData)->sum('leave_days'),
            'permit' => collect($allSummaryData)->sum('permit_days'),
            'sick' => collect($allSummaryData)->sum('sick_days'),
            'late_days' => collect($allSummaryData)->sum('late_days'),
            'total_overtime_hours' => round(collect($allSummaryData)->sum('total_overtime_minutes') / 60, 1),
            'total_late_minutes' => collect($allSummaryData)->sum('total_late_minutes'),
            'total_actual_overtime' => round($totalActualOvertime, 2),
            'total_calculated_overtime' => round($totalCalculatedOvertime, 2),
        ];

        // Paginated employees for table display
        $employees = $employeesQuery->paginate(20)->withQueryString();
        $paginatedEmployeeIds = $employees->pluck('id');

        $paginatedLeaveRequests = LeaveRequest::whereIn('employee_id', $paginatedEmployeeIds)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        $summaryData = [];
        foreach ($employees as $employee) {
            $logs = $employee->autologs;
            $employeeLeaves = $paginatedLeaveRequests[$employee->id] ?? collect();

            $presentDays = $logs->where('status', 'present')->count();
            $absentDays = $logs->where('status', 'absent')->count();
            $offDays = $logs->where('status', 'off')->count();
            $holidayDays = $logs->where('status', 'holiday')->count();

            $leaveDays = $employeeLeaves->whereIn('leave_type_id', $cutiTypeIds)->sum('days_requested');
            $permitDays = $employeeLeaves->whereIn('leave_type_id', $izinTypeIds)->sum('days_requested');
            $sickDays = $employeeLeaves->where('leave_type_id', $sakitTypeId)->sum('days_requested');

            $lateDays = $logs->where('late_duration', '>', 0)->count();
            $totalLateMinutes = $logs->sum('late_duration');
            $totalEarlyLeaveMinutes = $logs->sum('early_leave_duration');
            $totalOvertimeMinutes = $logs->sum('lembur');

            $overtimeHours = $totalOvertimeMinutes / 60;
            $calculatedOvertime = $logs->sum('lembur_calc') + $logs->sum('lm_calc');

            $summaryData[] = [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'employment_status' => $employee->employment_status,
                'position' => $employee->position?->name,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'permit_days' => $permitDays,
                'sick_days' => $sickDays,
                'off_days' => $offDays,
                'holiday_days' => $holidayDays,
                'late_days' => $lateDays,
                'total_late_minutes' => $totalLateMinutes,
                'total_early_leave_minutes' => $totalEarlyLeaveMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'overtime_hours' => round($overtimeHours, 2),
                'calculated_overtime' => round($calculatedOvertime, 2),
            ];
        }

        // Check existing snapshots by pay_period_id
        $existingSnapshots = collect();
        if ($selectedPeriodId) {
            $existingSnapshots = AttendanceSnapshot::where('pay_period_id', $selectedPeriodId)
                ->whereNull('segment')
                ->get()
                ->keyBy('employee_id');
        }

        foreach ($summaryData as &$data) {
            $data['has_snapshot'] = isset($existingSnapshots[$data['id']]);
            $data['snapshot_id'] = $existingSnapshots[$data['id']]->id ?? null;
        }

        return response()->json([
            'employees' => $summaryData,
            'stats' => $stats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $request->search,
            ],
            'payrollPeriods' => collect($payrollPeriods)->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'start_date' => Carbon::parse($p->start_date)->toDateString(),
                    'end_date' => Carbon::parse($p->end_date)->toDateString(),
                ];
            }),
            'selectedPeriodId' => $selectedPeriodId,
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
        ]);

        $startDate = Carbon::parse($request->period_start)->toDateString();
        $endDate = Carbon::parse($request->period_end)->toDateString();

        // Resolve pay_period_id
        $payPeriod = PayPeriod::where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->first();

        if (! $payPeriod) {
            return response()->json([
                'success' => false,
                'message' => 'Periode payroll tidak ditemukan untuk rentang tanggal tersebut.',
            ], 400);
        }

        $employee = Employee::where('is_active', 1)
            ->findOrFail($request->employee_id);

        $logs = AttendanceAutolog::where('employee_id', $request->employee_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('leave')
            ->get();

        $presentDays = $logs->where('status', 'present')->count();
        $absentDays = $logs->where('status', 'absent')->count();
        $dedDays = $logs->where('deduct_attendance', 1)->count();
        $notPaidDays = $logs->sum('deduct_day') + $dedDays;

        $leaveData = $this->getLeaveDataFromRequests($request->employee_id, $startDate, $endDate);
        $leaveDays = $leaveData['leave_days'];
        $permitDays = $leaveData['permit_days'];
        $sickDays = $leaveData['sick_days'];

        $workingDays = $presentDays + $absentDays + $leaveDays + $permitDays + $sickDays;
        $lateMinutes = $logs->sum('late_duration');
        $lm = $logs->sum('lm') / 60;
        $lmCount = (float) $logs->sum('lm_calc');
        $lembur = $logs->sum('lembur') / 60;
        $lemburCount = (float) $logs->sum('lembur_calc');

        $snapshot = AttendanceSnapshot::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'pay_period_id' => $payPeriod->id,
                'segment' => null,
            ],
            [
                'hari_kerja' => $workingDays,
                'cuti' => $leaveDays,
                'izin' => $permitDays,
                'sakit' => $sickDays,
                'absen' => $absentDays,
                'deduct_day' => $notPaidDays,
                'late_minutes' => $lateMinutes,
                'lm' => $lm,
                'lm_count' => $lmCount,
                'lembur' => $lembur,
                'lembur_count' => $lemburCount,
                'status' => 'draft',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Snapshot berhasil disimpan',
            'data' => $snapshot,
        ]);
    }

    public function storeBulk(Request $request)
    {
        try {
            $request->validate([
                'employee_ids' => 'required_without:save_all|array',
                'employee_ids.*' => 'exists:employees,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date',
            ]);

            $startDate = Carbon::parse($request->period_start)->toDateString();
            $endDate = Carbon::parse($request->period_end)->toDateString();

            // Resolve pay_period_id
            $payPeriod = PayPeriod::where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->first();

            if (! $payPeriod) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode payroll tidak ditemukan untuk rentang tanggal tersebut.',
                ], 400);
            }

            $created = 0;
            $updated = 0;

            if ($request->save_all) {
                // Hanya employee yang terdaftar di supervisor_employee_groups periode ini
                $employeeIds = \App\Modules\Supervisor\Models\SupervisorEmployeeGroup::where('period_start', $startDate)
                    ->where('period_end', $endDate)
                    ->pluck('employee_id')
                    ->unique()
                    ->values()
                    ->toArray();
            } else {
                $employeeIds = $request->employee_ids;
            }

            foreach ($employeeIds as $employeeId) {
                $employee = Employee::with(['department', 'position'])
                    ->find($employeeId);

                if (! $employee) {
                    continue;
                }

                $logs = AttendanceAutolog::where('employee_id', $employeeId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $presentDays = $logs->where('status', 'present')->count();
                $absentDays = $logs->where('status', 'absent')->count();
                $dedDays = $logs->where('deduct_attendance', 1)->count();
                $notPaidDays = $logs->sum('deduct_day') + $dedDays;

                $leaveData = $this->getLeaveDataFromRequests($employeeId, $startDate, $endDate);
                $leaveDays = $leaveData['leave_days'];
                $permitDays = $leaveData['permit_days'];
                $sickDays = $leaveData['sick_days'];

                $workingDays = $presentDays + $absentDays + $leaveDays + $permitDays + $sickDays;
                $lateMinutes = $logs->sum('late_duration');
                $lm = $logs->sum('lm') / 60;
                $lmCount = (float) $logs->sum('lm_calc');
                $lembur = $logs->sum('lembur') / 60;
                $lemburCount = (float) $logs->sum('lembur_calc');

                $existing = AttendanceSnapshot::where('employee_id', $employeeId)
                    ->where('pay_period_id', $payPeriod->id)
                    ->whereNull('segment')
                    ->first();

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }

                AttendanceSnapshot::updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'pay_period_id' => $payPeriod->id,
                        'segment' => null,
                    ],
                    [
                        'hari_kerja' => $workingDays,
                        'cuti' => $leaveDays,
                        'izin' => $permitDays,
                        'sakit' => $sickDays,
                        'absen' => $absentDays,
                        'deduct_day' => $notPaidDays,
                        'late_minutes' => $lateMinutes,
                        'lm' => $lm,
                        'lm_count' => $lmCount,
                        'lembur' => $lembur,
                        'lembur_count' => $lemburCount,
                        'status' => 'draft',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            // Hapus snapshot karyawan yang sudah tidak ada di group periode ini
            $deleted = AttendanceSnapshot::where('pay_period_id', $payPeriod->id)
                ->whereNull('segment')
                ->whereNotIn('employee_id', $employeeIds)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Snapshot berhasil disimpan: {$created} baru, {$updated} diperbarui" . ($deleted > 0 ? ", {$deleted} dihapus (tidak ada di group)" : ''),
            ]);
        } catch (\Exception $e) {
            \Log::error('storeBulk error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function print(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $leaveTypeIds = LeaveType::all();
        $cutiTypeIds = $leaveTypeIds->where('category', 'leave')->pluck('id');
        $izinTypeIds = $leaveTypeIds->where('category', 'permit')->pluck('id');
        $sakitTypeId = $leaveTypeIds->firstWhere('category', 'sick')?->id;

        // Ambil employee ID dari supervisor_employee_groups per periode
        $groupEmployeeIds = \App\Modules\Supervisor\Models\SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->pluck('employee_id')
            ->unique()
            ->values();

        $employees = Employee::whereIn('id', $groupEmployeeIds)
            ->with([
                'department',
                'position',
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->get();

        $employeeIds = $employees->pluck('id');

        $leaveRequests = LeaveRequest::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        $summaryData = [];
        $totalActualOvertime = 0;
        $totalCalculatedOvertime = 0;

        foreach ($employees as $employee) {
            $logs = $employee->autologs;
            $employeeLeaves = $leaveRequests[$employee->id] ?? collect();

            $presentDays = $logs->where('status', 'present')->count();
            $absentDays = $logs->where('status', 'absent')->count();
            $dedDays = $logs->where('deduct_attendance', 1)->count();
            $notPaidDays = $logs->sum('deduct_day') + $dedDays;
            $offDays = $logs->where('status', 'off')->count();
            $holidayDays = $logs->where('status', 'holiday')->count();

            $leaveDays = $employeeLeaves->whereIn('leave_type_id', $cutiTypeIds)->sum('days_requested');
            $permitDays = $employeeLeaves->whereIn('leave_type_id', $izinTypeIds)->sum('days_requested');
            $sickDays = $employeeLeaves->where('leave_type_id', $sakitTypeId)->sum('days_requested');

            $lateDays = $logs->where('late_duration', '>', 0)->count();
            $totalLateMinutes = $logs->sum('late_duration');
            $totalEarlyLeaveMinutes = $logs->sum('early_leave_duration');
            $totalOvertimeMinutes = $logs->sum('lembur');

            $overtimeHours = $totalOvertimeMinutes / 60;
            $calculatedOvertime = $logs->sum('lembur_calc') + $logs->sum('lm_calc');

            $totalActualOvertime += $overtimeHours;
            $totalCalculatedOvertime += $calculatedOvertime;

            $summaryData[] = [
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'employment_status' => $employee->employment_status,
                'position' => $employee->position?->name,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'permit_days' => $permitDays,
                'sick_days' => $sickDays,
                'off_days' => $offDays,
                'holiday_days' => $holidayDays,
                'late_days' => $lateDays,
                'total_late_minutes' => $totalLateMinutes,
                'total_early_leave_minutes' => $totalEarlyLeaveMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'overtime_hours' => round($overtimeHours, 2),
                'calculated_overtime' => round($calculatedOvertime, 2),
            ];
        }

        $stats = [
            'total_employees' => count($summaryData),
            'present' => collect($summaryData)->sum('present_days'),
            'absent' => collect($summaryData)->sum('absent_days'),
            'leave' => collect($summaryData)->sum('leave_days'),
            'permit' => collect($summaryData)->sum('permit_days'),
            'sick' => collect($summaryData)->sum('sick_days'),
            'late_days' => collect($summaryData)->sum('late_days'),
            'total_overtime_hours' => round(collect($summaryData)->sum('total_overtime_minutes') / 60, 1),
            'total_late_minutes' => collect($summaryData)->sum('total_late_minutes'),
            'total_actual_overtime' => round($totalActualOvertime, 2),
            'total_calculated_overtime' => round($totalCalculatedOvertime, 2),
        ];

        // Get company info
        $company = Company::first();

        // Generate PDF via dompdf
        $pdf = Pdf::loadView('supervisor.attendance.snapshot-print-pdf', [
            'company' => $company,
            'employees' => $summaryData,
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'filters' => [
                'search' => $request->search,
            ],
        ]);

        $pdf->setPaper('A4', 'portrait');

        $periodLabel = Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = 'Snapshot_Absensi_' . $periodLabel . '.pdf';

        return $pdf->download($filename);
    }

    private function calculateOvertime($hours)
    {
        if ($hours <= 0) {
            return 0;
        }
        if ($hours <= 1) {
            return $hours * 1.5;
        }

        return 1 * 1.5 + ($hours - 1) * 2;
    }

    private function getLeaveDataFromRequests($employeeId, $startDate, $endDate)
    {
        $leaveTypeIds = LeaveType::all();
        $cutiTypeIds = $leaveTypeIds->where('category', 'leave')->pluck('id');
        $izinTypeIds = $leaveTypeIds->where('category', 'permit')->pluck('id');
        $sakitTypeId = $leaveTypeIds->firstWhere('category', 'sick')?->id;

        $leaveRequests = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        return [
            'leave_days' => $leaveRequests->whereIn('leave_type_id', $cutiTypeIds)->sum('days_requested'),
            'permit_days' => $leaveRequests->whereIn('leave_type_id', $izinTypeIds)->sum('days_requested'),
            'sick_days' => $leaveRequests->where('leave_type_id', $sakitTypeId)->sum('days_requested'),
        ];
    }

    private function getPeriod($period)
    {
        $now = now();
        switch ($period) {
            case 'this_month':
                return [
                    'start' => $now->startOfMonth()->toDateString(),
                    'end' => $now->endOfMonth()->toDateString(),
                ];
            case 'last_month':
                return [
                    'start' => $now->subMonth()->startOfMonth()->toDateString(),
                    'end' => $now->subMonth()->endOfMonth()->toDateString(),
                ];
            default:
                return [
                    'start' => $now->startOfMonth()->toDateString(),
                    'end' => $now->endOfMonth()->toDateString(),
                ];
        }
    }
}
