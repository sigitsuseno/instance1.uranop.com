<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendanceSnapshot;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendanceSnapshot as AttendanceSnapshot;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Payroll\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\Payroll\Models\PayPeriod;

class AttendanceSnapshotController extends Controller
{
    public function index(Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;
        $userType = Auth::user()->user_type;

        $payrollPeriods = collect();

        if ($userType === 'hr_branch') {
            $payrollPeriods = PayPeriod::orderBy('start_date', 'desc')
                ->get();
        }

        $period = $this->getPeriod($request->input('period'));
        $startDate = $period['start'];
        $endDate = $period['end'];

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        }

        $selectedPeriodId = null;
        if ($userType === 'hr_branch' && $payrollPeriods->isNotEmpty() && ! $request->filled('start_date')) {
            $latestPeriod = $payrollPeriods->first();
            $startDate = $latestPeriod->start_date->toDateString();
            $endDate = $latestPeriod->end_date->toDateString();
            $selectedPeriodId = $latestPeriod->id;
        }

        $leaveTypeIds = LeaveType::where('company_id', $companyId)->get();
        $cutiTypeIds = $leaveTypeIds->filter(fn ($t) => str_starts_with($t->code, 'CT'))->pluck('id');
        $izinTypeIds = $leaveTypeIds->filter(fn ($t) => in_array($t->code, ['ITM', 'IMT', 'IPA']))->pluck('id');
        $sakitTypeId = $leaveTypeIds->firstWhere('code', 'SKT')?->id;

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        // Build base query: employee yang punya roster di periode ini
        $employeesQuery = Employee::whereHas('shiftRosters', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })
            ->with([
                'department',
                'position',
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate])->with('leave.leaveType');
                },
            ])
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
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
            $periodSnapshot = null;
            $logs = $employee->autologs;
            $employeeLeaves = $allLeaveRequests[$employee->id] ?? collect();
            $presentDays = $logs->where('status', 'present')->count();
            $absentDays = $logs->where('status', 'absent')->count();
            $offDays = $logs->where('status', 'off')->count();
            $holidayDays = $logs->where('status', 'holiday')->count();

            $leaveDays = $employeeLeaves->whereIn('leave_type_id', $cutiTypeIds)->sum('duration_days');
            $permitDays = $employeeLeaves->whereIn('leave_type_id', $izinTypeIds)->sum('duration_days');
            $sickDays = $employeeLeaves->where('leave_type_id', $sakitTypeId)->sum('duration_days');

            $lateDays = $logs->where('late_duration', '>', 0)->count();
            $totalLateMinutes = $logs->sum('late_duration');
            $totalEarlyLeaveMinutes = $logs->sum('early_leave_duration');
            $totalOvertimeMinutes = $logs->sum('lembur');

            $overtimeHours = $totalOvertimeMinutes / 60;
            $calculatedOvertime = $logs->sum('lembur_calc');

            $totalActualOvertime += $overtimeHours;
            $totalCalculatedOvertime += $calculatedOvertime;

            $allSummaryData[] = [
                'id' => $employee->id,
                'employee_code' => $periodSnapshot?->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'employment_status' => $employee->employment_status,
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name,
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
            $periodSnapshot = null;
            $logs = $employee->autologs;
            $employeeLeaves = $paginatedLeaveRequests[$employee->id] ?? collect();

            $presentDays = $logs->where('status', 'present')->count();
            $absentDays = $logs->where('status', 'absent')->count();
            $offDays = $logs->where('status', 'off')->count();
            $holidayDays = $logs->where('status', 'holiday')->count();

            $leaveDays = $employeeLeaves->whereIn('leave_type_id', $cutiTypeIds)->sum('duration_days');
            $permitDays = $employeeLeaves->whereIn('leave_type_id', $izinTypeIds)->sum('duration_days');
            $sickDays = $employeeLeaves->where('leave_type_id', $sakitTypeId)->sum('duration_days');

            $lateDays = $logs->where('late_duration', '>', 0)->count();
            $totalLateMinutes = $logs->sum('late_duration');
            $totalEarlyLeaveMinutes = $logs->sum('early_leave_duration');
            $totalOvertimeMinutes = $logs->sum('lembur');

            $overtimeHours = $totalOvertimeMinutes / 60;
            $calculatedOvertime = $logs->sum('lembur_calc');

            $summaryData[] = [
                'id' => $employee->id,
                'employee_code' => $periodSnapshot?->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'employment_status' => $employee->employment_status,
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name,
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

        $existingSnapshots = AttendanceSnapshot::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->whereBetween('period_start', [$startDate, $endDate])
            ->get()
            ->keyBy('employee_id');

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

        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        $startDate = Carbon::parse($request->period_start)->toDateString();
        $endDate = Carbon::parse($request->period_end)->toDateString();
        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

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
        $offDays = $logs->where('status', 'off')->count();
        $holidayDays = $logs->where('status', 'holiday')->count();
        $holiday_overtime = $logs->sum('holiday_overtime');

        $leaveData = $this->getLeaveDataFromRequests($request->employee_id, $startDate, $endDate, $companyId);
        $leaveDays = $leaveData['leave_days'];
        $permitDays = $leaveData['permit_days'];
        $sickDays = $leaveData['sick_days'];

        $workingDays = $presentDays + $absentDays + $leaveDays + $permitDays + $sickDays;

        $periodCode = $startDate.'_'.$endDate;

        $snapshot = AttendanceSnapshot::updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'employee_id' => $request->employee_id,
                'period_code' => $periodCode,
            ],
            [
                'period_start' => $startDate,
                'period_end' => $endDate,
                'total_working_days' => $workingDays,
                'total_present_days' => $presentDays,
                'total_absent_days' => $absentDays,
                'total_unpaid_days' => $notPaidDays,
                'total_late_days' => $logs->where('late_duration', '>', 0)->count(),
                'total_late_minutes' => $logs->sum('late_duration'),
                'total_early_leave_minutes' => $logs->sum('early_leave_duration'),
                'total_overtime_minutes' => $logs->sum('lembur_calc') * 60,
                'total_holiday_overtime' => $logs->sum('holiday_overtime'),
                'total_leave_days' => $leaveDays,
                'total_sick_days' => $sickDays,
                'total_permit_days' => $permitDays,
                'snapshot' => [
                    'off_days' => $offDays,
                    'holiday_days' => $holidayDays,
                    'calculated_overtime' => $logs->sum('lembur_calc'),
                    'created_at' => now()->toIso8601String(),
                    'created_by' => Auth::id(),
                ],
                'status' => 'draft',
                'is_locked' => false,
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

            $branchId = session('branch_id');
            $companyId = Auth::user()->company_id;

            $startDate = Carbon::parse($request->period_start)->toDateString();
            $endDate = Carbon::parse($request->period_end)->toDateString();
            $periodCode = $startDate.'_'.$endDate;

            $created = 0;
            $updated = 0;

            $month = Carbon::parse($endDate)->month;
            $year = Carbon::parse($endDate)->year;

            if ($request->save_all) {
                $employeeIds = Employee::where('is_active', 1)
                    
                    
                    ->pluck('id')
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
                $offDays = $logs->where('status', 'off')->count();
                $holidayDays = $logs->where('status', 'holiday')->count();

                $leaveData = $this->getLeaveDataFromRequests($employeeId, $startDate, $endDate, $companyId);
                $leaveDays = $leaveData['leave_days'];
                $permitDays = $leaveData['permit_days'];
                $sickDays = $leaveData['sick_days'];

                $workingDays = $presentDays + $absentDays + $leaveDays + $permitDays + $sickDays;

                $existing = AttendanceSnapshot::where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->where('employee_id', $employeeId)
                    ->where('period_code', $periodCode)
                    ->first();

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }

                AttendanceSnapshot::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'employee_id' => $employeeId,
                        'period_code' => $periodCode,
                    ],
                    [
                        'period_start' => $startDate,
                        'period_end' => $endDate,
                        'total_working_days' => $workingDays,
                        'total_present_days' => $presentDays,
                        'total_absent_days' => $absentDays,
                        'total_late_days' => $logs->where('late_duration', '>', 0)->count(),
                        'total_late_minutes' => $logs->sum('late_duration'),
                        'total_early_leave_minutes' => $logs->sum('early_leave_duration'),
                        'total_overtime_minutes' => $logs->sum('lembur_calc') * 60,
                        'total_holiday_overtime' => $logs->sum('holiday_overtime'),
                        'total_leave_days' => $leaveDays,
                        'total_unpaid_days' => $notPaidDays,
                        'total_sick_days' => $sickDays,
                        'total_permit_days' => $permitDays,
                        'snapshot' => [
                            'off_days' => $offDays,
                            'holiday_days' => $holidayDays,
                            'calculated_overtime' => $logs->sum('lembur_calc'),
                            'created_at' => now()->toIso8601String(),
                            'created_by' => Auth::id(),
                        ],
                        'status' => 'draft',
                        'is_locked' => false,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => "Snapshot berhasil disimpan: {$created} baru, {$updated} diperbarui",
            ]);
        } catch (\Exception $e) {
            \Log::error('storeBulk error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function print(Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $leaveTypeIds = LeaveType::where('company_id', $companyId)->get();
        $cutiTypeIds = $leaveTypeIds->filter(fn ($t) => str_starts_with($t->code, 'CT'))->pluck('id');
        $izinTypeIds = $leaveTypeIds->filter(fn ($t) => in_array($t->code, ['ITM', 'IMT', 'IPA']))->pluck('id');
        $sakitTypeId = $leaveTypeIds->firstWhere('code', 'SKT')?->id;

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        $employees = Employee::where('is_active', 1)
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
            $periodSnapshot = null;
            $logs = $employee->autologs;
            $employeeLeaves = $leaveRequests[$employee->id] ?? collect();

            $presentDays = $logs->where('status', 'present')->count();
            $dedDays = $logs->where('deduct_attendance', 1)->count();
            $notPaidDays = $logs->sum('deduct_day') + $dedDays;
            $offDays = $logs->where('status', 'off')->count();
            $holidayDays = $logs->where('status', 'holiday')->count();

            $leaveDays = $employeeLeaves->whereIn('leave_type_id', $cutiTypeIds)->sum('duration_days');
            $permitDays = $employeeLeaves->whereIn('leave_type_id', $izinTypeIds)->sum('duration_days');
            $sickDays = $employeeLeaves->where('leave_type_id', $sakitTypeId)->sum('duration_days');

            $lateDays = $logs->where('late_duration', '>', 0)->count();
            $totalLateMinutes = $logs->sum('late_duration');
            $totalEarlyLeaveMinutes = $logs->sum('early_leave_duration');
            $totalOvertimeMinutes = $logs->sum('lembur');

            $overtimeHours = $totalOvertimeMinutes / 60;
            $calculatedOvertime = $logs->sum('lembur_calc');

            $totalActualOvertime += $overtimeHours;
            $totalCalculatedOvertime += $calculatedOvertime;

            $summaryData[] = [
                'employee_code' => $periodSnapshot?->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'employment_status' => $employee->employment_status,
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name,
                'present_days' => $presentDays,
                'absent_days' => $absentDays ?? 0,
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

        return view('supervisor.attendance.snapshot-print', [
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

    private function getLeaveDataFromRequests($employeeId, $startDate, $endDate, $companyId)
    {
        $leaveTypeIds = LeaveType::where('company_id', $companyId)->get();
        $cutiTypeIds = $leaveTypeIds->filter(fn ($t) => str_starts_with($t->code, 'CT'))->pluck('id');
        $izinTypeIds = $leaveTypeIds->filter(fn ($t) => in_array($t->code, ['ITM', 'IMT', 'IPA']))->pluck('id');
        $sakitTypeId = $leaveTypeIds->firstWhere('code', 'SKT')?->id;

        $leaveRequests = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->get();

        return [
            'leave_days' => $leaveRequests->whereIn('leave_type_id', $cutiTypeIds)->sum('duration_days'),
            'permit_days' => $leaveRequests->whereIn('leave_type_id', $izinTypeIds)->sum('duration_days'),
            'sick_days' => $leaveRequests->where('leave_type_id', $sakitTypeId)->sum('duration_days'),
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
