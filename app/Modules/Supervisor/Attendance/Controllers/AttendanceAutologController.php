<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Services\AttendanceCalculatorService;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Supervisor\Attendance\Services\SupervisorAttPrepareSync;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\WorkPattern;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceAutologController extends Controller
{
    /**
     * Display auditor logs summary per employee
     */
    public function index(Request $request)
    {

        $userType = Auth::user()->user_type;

        $payrollPeriods = collect();

        // Get period
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod($request->input('period'));
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        $selectedPeriodId = null;

        // Fetch payroll periods for period selector
        $payrollPeriods = PayPeriod::orderBy('start_date', 'desc')
            ->get();

        // Default to latest payroll period if no dates specified
        if (! $request->has('start_date') && ! $request->has('end_date') && $payrollPeriods->isNotEmpty()) {
            $latestPeriod = $payrollPeriods->first();
            $startDate = $latestPeriod->start_date->toDateString();
            $endDate = $latestPeriod->end_date->toDateString();
            $selectedPeriodId = $latestPeriod->id;
        }

        // Ambil employee ID dari supervisor_employee_groups per periode
        $groupEmployeeIds = SupervisorEmployeeGroup::where('period_start', $startDate)
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
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->department_id, function ($query, $deptId) {
                $query->where('department_id', $deptId);
            })
            ->when($request->work_pattern_id, function ($query, $wpId) {
                $query->where('work_pattern_id', $wpId);
            })
            ->orderBy('employee_code');

        // Get all employees for stats (no pagination)
        $allEmployees = (clone $employeesQuery)->get();

        // Transform all employees for stats calculation
        $allSummaryData = [];
        foreach ($allEmployees as $employee) {
            $logs = $employee->autologs;
            $allSummaryData[] = [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('izin_duration', 1)->count(),
                'sakit' => $logs->where('sakit_duration', 1)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
                'libur' => $logs->where('status', 'holiday')->count(),
                'off' => $logs->where('status', 'off')->count(),
                'is_locked' => $logs->count() > 0 && $logs->first()?->is_locked,
            ];
        }

        // Stats from ALL employees (not affected by pagination)
        $stats = [
            'total_employees' => count($allSummaryData),
            'present' => collect($allSummaryData)->sum('hadir'),
            'total_overtime' => collect($allSummaryData)->sum('lembur'),
            'leave' => collect($allSummaryData)->sum('cuti'),
            'permit' => collect($allSummaryData)->sum('izin'),
            'sakit' => collect($allSummaryData)->sum('sakit'),
            'absent' => collect($allSummaryData)->sum('absen'),
        ];

        // Paginated employees for table display
        $employees = $employeesQuery->paginate(20)->withQueryString();

        // Transform only paginated employees for table
        $summaryData = [];
        foreach ($employees as $employee) {
            $logs = $employee->autologs;
            $summaryData[] = [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('izin_duration', 1)->count(),
                'sakit' => $logs->where('sakit_duration', 1)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
                'libur' => $logs->where('status', 'holiday')->count(),
                'off' => $logs->where('status', 'off')->count(),
                'is_locked' => $logs->count() > 0 && $logs->first()?->is_locked,
            ];
        }

        // Check if data exists
        $allLogsQuery = AttendanceAutolog::whereBetween('date', [$startDate, $endDate]);

        $isDataExists = (clone $allLogsQuery)->exists();

        return response()->json([
            'employees' => $summaryData,
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'period_id' => $selectedPeriodId,
            ],
            'filters' => [
                'search' => $request->search,
                'department_id' => $request->department_id,
                'work_pattern_id' => $request->work_pattern_id,
            ],
            'departments' => Department::get(['id', 'name']),
            'workPatterns' => WorkPattern::get(['id', 'name']),
            'payrollPeriods' => collect($payrollPeriods)->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'start_date' => Carbon::parse($p->start_date)->toDateString(),
                    'end_date' => Carbon::parse($p->end_date)->toDateString(),
                ];
            }),
            'pagination' => [
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
                'links' => [
                    'prev' => $employees->previousPageUrl(),
                    'next' => $employees->nextPageUrl(),
                ],
            ],
            'isDataExists' => $isDataExists,
        ]);
    }

    /**
     * Print summary report using blade view with iframe
     */
    public function print(Request $request)
    {
        // Get period from request
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        // Get filter params
        $departmentId = $request->input('department_id');
        $workPatternId = $request->input('work_pattern_id');
        $search = $request->input('search');

        // Get all employees with filters (no pagination for print)
        $employees = Employee::whereHas('shiftRosters', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })
            ->with([
                'department',
                'position',
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($departmentId, function ($query, $deptId) {
                $query->where('department_id', $deptId);
            })
            ->when($workPatternId, function ($query, $wpId) {
                $query->where('work_pattern_id', $wpId);
            })
            ->orderBy('employee_code')
            ->get();

        // Transform data for summary
        $summaryData = [];
        foreach ($employees as $employee) {
            $logs = $employee->autologs;
            $summaryData[] = [
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name ?? '-',
                'position' => $employee->position?->name ?? '-',
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('status', 'izin')->where('deduct_attendance', 1)->count(),
                'sakit' => $logs->where('status', 'sakit')->where('deduct_attendance', 0)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
                'libur' => $logs->where('status', 'holiday')->count(),
                'off' => $logs->where('status', 'off')->count(),
            ];
        }

        // Calculate stats
        $stats = [
            'total_employees' => count($summaryData),
            'present' => collect($summaryData)->sum('hadir'),
            'total_overtime_hours' => collect($summaryData)->sum('lembur'),
            'leave' => collect($summaryData)->sum('cuti'),
            'permit' => collect($summaryData)->sum('izin'),
            'sakit' => collect($summaryData)->sum('sakit'),
            'absent' => collect($summaryData)->sum('absen'),
        ];

        // Get department name if filtered
        $departmentName = 'Semua Departemen';
        if ($departmentId) {
            $dept = Department::find($departmentId);
            $departmentName = $dept?->name ?? 'Semua Departemen';
        }

        return view('supervisor.attendance.autolog-print', [
            'employees' => $summaryData,
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'departmentName' => $departmentName,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'work_pattern_id' => $workPatternId,
            ],
        ]);
    }

    /**
     * Show detail auditor log for an employee
     */
    public function show($employeeId, Request $request)
    {
        // Get period
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $employee = Employee::with([
            'department',
            'position',
        ])
            ->where('id', $employeeId)
            ->firstOrFail();

        $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Fallback: load roster langsung untuk hari2 yang autolog-nya tidak punya employee_shift_roster_id
        // (terjadi pada data import XLSX manual, misal periode 1-4 tahun 2026)
        $datesWithoutRoster = $logs
            ->filter(fn ($l) => ! $l->employee_shift_roster_id)
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $rosterFallback = [];
        if (! empty($datesWithoutRoster)) {
            $rosters = \App\Modules\Schedule\Models\EmployeeShiftRoster::with('shift')
                ->where('employee_id', $employeeId)
                ->whereIn('date', $datesWithoutRoster)
                ->get()
                ->keyBy(fn ($r) => $r->date->toDateString());

            foreach ($rosters as $dateStr => $roster) {
                $rosterFallback[$dateStr] = [
                    'shift_start' => $roster->shift?->work_hour_start
                        ? Carbon::parse($roster->shift->work_hour_start)->format('H:i')
                        : null,
                    'shift_end' => $roster->shift?->work_hour_end
                        ? Carbon::parse($roster->shift->work_hour_end)->format('H:i')
                        : null,
                ];
            }
        }

        $isFixed = $logs->contains(fn ($log) => $log->employeeShiftRoster?->workPattern?->employee_type === 'SHIFT');

        $dailyData = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            // Priority: 1) dari relasi roster di autolog, 2) dari fallback roster lookup
            $shiftStart = $log?->employeeShiftRoster?->shift?->work_hour_start
                ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_start)->format('H:i')
                : ($rosterFallback[$dateStr]['shift_start'] ?? null);

            $shiftEnd = $log?->employeeShiftRoster?->shift?->work_hour_end
                ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_end)->format('H:i')
                : ($rosterFallback[$dateStr]['shift_end'] ?? null);

            $dailyData[] = [
                'date' => $dateStr,
                'day' => $currentDate->translatedFormat('D'),
                'date_display' => $currentDate->format('d M Y'),
                'is_weekend' => $currentDate->isSunday(),
                'check_in' => $log?->check_in ? $log->check_in->format('H:i') : null,
                'check_out' => $log?->check_out ? $log->check_out->format('H:i') : null,
                'lembur' => $log?->lembur ?? 0,
                'lembur_display' => $this->formatOvertime($log?->lembur ?? 0),
                'status' => $log?->status ?? 'pending',
                'status_label' => $this->getStatusLabel($log?->status ?? 'pending'),
                'status_badge' => $this->getStatusBadge($log?->status ?? 'pending'),
                'is_locked' => $log?->is_locked ?? false,
                'notes' => $log?->notes,
                'lembur_calc' => $log?->lembur_calc ?? 0,
                'lm' => $log?->lm ?? 0,
                'lm_calc' => $log?->lm_calc ?? 0,
                'lembur_total_calc' => round(($log?->lembur_calc ?? 0) + ($log?->lm_calc ?? 0), 1),
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'is_sat' => $log?->is_sat ?? false,
                'is_holiday' => $log?->is_holiday ?? false,
                'is_fixed' => $isFixed,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'code' => $employee->employee_code,
                'name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
            ],
            'dailyData' => $dailyData,
            'summary' => [
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => $logs->sum('lembur'),
                'lembur_calc' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('izin_duration', 1)->count(),
                'sakit' => $logs->where('sakit_duration', 1)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
            ],
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }

    protected function getPeriod($period = null)
    {
        $date = $period ? Carbon::parse($period.'-01') : Carbon::now();
        if ($date->day >= 25) {
            $start = $date->copy()->day(25);
            $end = $date->copy()->addMonth()->day(24);
        } else {
            $start = $date->copy()->subMonth()->day(25);
            $end = $date->copy()->day(24);
        }

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }

    protected function formatOvertime($minutes)
    {
        if (! $minutes || $minutes === 0) {
            return '-';
        }
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        if ($mins === 0) {
            return "{$hours} jam";
        }

        return round($minutes / 60, 1).' jam';
    }

    protected function getStatusLabel($status)
    {
        $labels = ['present' => 'Hadir', 'absent' => 'Absen', 'leave' => 'Cuti', 'permit' => 'Izin', 'holiday' => 'Libur', 'off' => 'Off', 'pending' => 'Menunggu'];

        return $labels[$status] ?? $status;
    }

    protected function getStatusBadge($status)
    {
        $badges = ['present' => 'bg-green-100 text-green-800', 'absent' => 'bg-red-100 text-red-800', 'leave' => 'bg-blue-100 text-blue-800', 'permit' => 'bg-purple-100 text-purple-800', 'holiday' => 'bg-gray-100 text-gray-800', 'off' => 'bg-orange-100 text-orange-800', 'pending' => 'bg-yellow-100 text-yellow-800'];

        return $badges[$status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * Generate PDF detail report for individual employee.
     * Output: downloadable PDF file (via dompdf).
     */
    public function printDetail($employeeId, Request $request)
    {
        // Get period
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $employee = Employee::with([
            'department',
            'position',
        ])
            ->where('id', $employeeId)
            ->firstOrFail();

        $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Fallback: load roster langsung untuk hari2 yang autolog-nya tidak punya employee_shift_roster_id
        $datesWithoutRoster = $logs
            ->filter(fn ($l) => ! $l->employee_shift_roster_id)
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $rosterFallback = [];
        if (! empty($datesWithoutRoster)) {
            $rosters = \App\Modules\Schedule\Models\EmployeeShiftRoster::with('shift')
                ->where('employee_id', $employeeId)
                ->whereIn('date', $datesWithoutRoster)
                ->get()
                ->keyBy(fn ($r) => $r->date->toDateString());

            foreach ($rosters as $dateStr => $roster) {
                $rosterFallback[$dateStr] = [
                    'shift_start' => $roster->shift?->work_hour_start
                        ? Carbon::parse($roster->shift->work_hour_start)->format('H:i')
                        : null,
                    'shift_end' => $roster->shift?->work_hour_end
                        ? Carbon::parse($roster->shift->work_hour_end)->format('H:i')
                        : null,
                ];
            }
        }

        $isFixed = $logs->contains(fn ($log) => $log->employeeShiftRoster?->workPattern?->employee_type === 'SHIFT');

        $dailyData = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            // Priority: 1) dari relasi roster di autolog, 2) dari fallback roster lookup
            $shiftStart = $log?->employeeShiftRoster?->shift?->work_hour_start
                ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_start)->format('H:i')
                : ($rosterFallback[$dateStr]['shift_start'] ?? null);

            $shiftEnd = $log?->employeeShiftRoster?->shift?->work_hour_end
                ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_end)->format('H:i')
                : ($rosterFallback[$dateStr]['shift_end'] ?? null);

            $dailyData[] = [
                'date' => $dateStr,
                'day' => $currentDate->translatedFormat('D'),
                'date_display' => $currentDate->format('d M Y'),
                'is_weekend' => $currentDate->isSunday(),
                'check_in' => $log?->check_in ? $log->check_in->format('H:i') : null,
                'check_out' => $log?->check_out ? $log->check_out->format('H:i') : null,
                'lembur' => $log?->lembur ?? 0,
                'lembur_display' => $this->formatOvertime($log?->lembur ?? 0),
                'lembur_calc' => $log?->lembur_calc ?? 0,
                'lm' => $log?->lm ?? 0,
                'lm_calc' => $log?->lm_calc ?? 0,
                'lembur_total_calc' => round(($log?->lembur_calc ?? 0) + ($log?->lm_calc ?? 0), 1),
                'status' => $log?->status ?? 'pending',
                'status_label' => $this->getStatusLabel($log?->status ?? 'pending'),
                'shift_start' => $shiftStart,
                'shift_end' => $shiftEnd,
                'is_sat' => $log?->is_sat ?? false,
                'is_holiday' => $log?->is_holiday ?? false,
                'is_fixed' => $isFixed,
            ];

            $currentDate->addDay();
        }

        $summary = [
            'hadir' => $logs->where('status', 'present')->count(),
            'lembur' => $logs->sum('lembur') / 60,
            'lembur_calc' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
            'cuti' => $logs->where('status', 'leave')->count(),
            'izin' => $logs->where('status', 'permit')->where('deduct_attendance', 1)->count(),
            'sakit' => $logs->where('status', 'permit')->where('deduct_attendance', 0)->count(),
            'absen' => $logs->where('status', 'absent')->count(),
        ];

        // Generate PDF via dompdf
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('supervisor.attendance.autolog-detail-pdf', [
            'employee' => [
                'id' => $employee->id,
                'code' => $employee->employee_code,
                'name' => $employee->name,
                'department' => $employee->department?->name ?? '-',
                'position' => $employee->position?->name ?? '-',
            ],
            'dailyData' => $dailyData,
            'summary' => $summary,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);

        $pdf->setPaper('A4', 'portrait');

        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $employee->name);
        $safeName = preg_replace('/_+/', '_', $safeName);
        $safeName = trim($safeName, '_');
        $periodLabel = Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = 'Absensi_' . $safeName . '_' . $periodLabel . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Adjustment: update leave data and overtime converted hours
     */
    public function adjustment(Request $request, AttendanceCalculatorService $calculator)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $startDate = Carbon::parse($request->input('start_date'))->toDateString();
        $endDate = Carbon::parse($request->input('end_date'))->toDateString();

        $autologs = AttendanceAutolog::with(['employeeShiftRoster.workPattern'])
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $employeeIds = Employee::withoutGlobalScopes()
            ->pluck('id');

        $leaveRequests = LeaveRequest::whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->with('leaveType')
            ->get();

        $updatedCount = 0;

        try {
            DB::beginTransaction();

            foreach ($autologs as $autolog) {
                $updateData = [];
                $dateStr = Carbon::parse($autolog->date)->toDateString();

                // --- CUTI ---
                $leaveForDate = $leaveRequests->where('employee_id', $autolog->employee_id)
                    ->filter(function ($lr) use ($dateStr) {
                        return $lr->start_date->toDateString() <= $dateStr
                            && $lr->end_date->toDateString() >= $dateStr;
                    })->first();

                if ($leaveForDate) {
                    $leaveTypeCode = $leaveForDate->leaveType?->code ?? '';
                    $isPaid = $leaveForDate->leaveType?->is_paid ?? false;

                    $updateData['is_leave'] = true;
                    $updateData['leave_id'] = $leaveForDate->id;
                    $updateData['deduct_day'] = $isPaid ? 0 : 1;
                    $updateData['sakit_duration'] = ($leaveTypeCode === 'SKT') ? 1 : 0;
                    $updateData['izin_duration'] = ($leaveTypeCode === 'ITM') ? 1 : 0;
                } else {
                    $updateData['is_leave'] = false;
                    $updateData['leave_id'] = null;
                    $updateData['deduct_day'] = ($autolog->status === 'absent') ? 1 : null;
                    $updateData['sakit_duration'] = 0;
                    $updateData['izin_duration'] = 0;
                }

                // --- LEMBUR ---
                // Hitung lembur_calc & lm pake AttendanceCalculatorService
                // (multiplier dari OvertimeRule per work_pattern_id, fallback ke hardcoded)
                if ($autolog->lembur > 0) {
                    $roster = $autolog->employeeShiftRoster;
                    $workPatternId = $roster?->work_pattern_id;
                    $workPatternType = $roster?->workPattern?->employee_type;

                    $calc = $calculator->calculateManual(
                        manualOvertimeMinutes: $autolog->lembur,
                        workPatternId: $workPatternId,
                        isHoliday: (bool) $autolog->is_holiday,
                        isSunday: (bool) $autolog->is_sun,
                        isSaturday: (bool) $autolog->is_sat,
                        workPatternType: $workPatternType,
                    );

                    // overtime_count = menit terkonversi (pake multiplier)
                    // lembur_calc disimpan dalam format jam (float)
                    $updateData['lembur_calc'] = round($calc['overtime_count'] / 60, 2);
                    $updateData['lm']          = $calc['lm'];
                    $updateData['lm_calc']     = round($calc['lm_count'] / 60, 2);
                } else {
                    $updateData['lembur_calc'] = null;
                    $updateData['lm']          = 0;
                    $updateData['lm_calc']     = null;
                }

                $autolog->update($updateData);
                $updatedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Adjustment berhasil! {$updatedCount} records telah diperbarui.",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Adjustment Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync data dari att_prepares ke attendance_autologs.
     * Tombol Sync di halaman Data Absensi.
     */
    public function sync(Request $request, SupervisorAttPrepareSync $service)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $request->validate([
            'payroll_period_id' => ['required', 'integer', 'exists:pay_periods,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        $periodId = (int) $request->input('payroll_period_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        try {
            $result = $service->sync(
                periodId: $periodId,
                startDate: $startDate,
                endDate: $endDate,
            );

            return response()->json([
                'success' => true,
                'message' => "Sync selesai! {$result['inserted']} inserted, {$result['updated']} updated.",
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('AttPrepareSync failed', [
                'error' => $e->getMessage(),
                'period_id' => $periodId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync gagal: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Roster cross-table view untuk autolog data.
     * GET /api/v1/supervisor/attendance/roster
     */
    public function roster(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate   = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();
        $groupCodes = $request->input('group_codes', []);

        // --- Available groups (untuk checkbox) ---
        $availableGroups = \App\Modules\Settings\Models\EmployeeGroupMaster::where('group_label', 'Imported Shift/Group')
            ->where('is_active', true)
            ->get()
            ->map(fn($g) => ['code' => $g->code, 'name' => $g->name])
            ->values();

        // Default: semua groups kalo ga dipilih
        if (empty($groupCodes)) {
            $groupCodes = $availableGroups->pluck('code')->toArray();
        }

        // --- Employees: sama kayak index() — yang punya autolog ATAU roster di periode ini ---
        $employeesQuery = Employee::where(function ($q) use ($startDate, $endDate) {
            $q->whereHas('shiftRosters', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })->orWhereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });
        })
            // Kalau ada group codes terpilih, filter by group
            ->when(!empty($groupCodes), function ($query) use ($groupCodes) {
                $query->whereHas('groups', function ($q) use ($groupCodes) {
                    $q->whereIn('reference_code', $groupCodes);
                });
            })
            ->with(['department', 'position'])
            ->orderBy('employee_code');

        $search = $request->input('search');
        if ($search) {
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $employees = $employeesQuery->get();

        // --- Autolog data ---
        $autologs = AttendanceAutolog::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // --- Roster (shift) data untuk header shift_start/shift_end ---
        $rosters = \App\Modules\Schedule\Models\EmployeeShiftRoster::with('shift')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // --- Generate semua tanggal dalam range ---
        $dates = [];
        $current = Carbon::parse($startDate);
        $last = Carbon::parse($endDate);
        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        while ($current <= $last) {
            $d = $current->copy();
            $dates[] = [
                'date'      => $d->toDateString(),
                'day'       => $d->day,
                'dayName'   => $dayNames[$d->dayOfWeek],
                'isWeekend' => $d->isSunday(),
            ];
            $current->addDay();
        }

        // --- Build employee list ---
        $employeeList = $employees->map(function ($emp) {
            return [
                'id'         => $emp->id,
                'name'       => $emp->name,
                'nip'        => $emp->employee_code,
                'department' => $emp->department?->name ?? '-',
                'position'   => $emp->position?->name ?? '-',
            ];
        })->values();

        // --- Build autolog data map ---
        $autologData = [];
        foreach ($autologs as $empId => $logs) {
            $empRosters = $rosters->get($empId, collect());
            foreach ($logs as $log) {
                $dateStr = $log->date->toDateString();
                $roster = $empRosters->first(fn($r) => $r->date->toDateString() === $dateStr);
                $autologData[$empId][$dateStr] = [
                    'id'          => $log->id,
                    'check_in'    => $log->check_in ? $log->check_in->format('H:i') : null,
                    'check_out'   => $log->check_out ? $log->check_out->format('H:i') : null,
                    'lembur'      => (int) $log->lembur,
                    'lm'          => (int) $log->lm,
                    'status'      => $log->status,
                    'shift_start' => $roster?->shift?->work_hour_start
                        ? Carbon::parse($roster->shift->work_hour_start)->format('H:i')
                        : null,
                    'shift_end'   => $roster?->shift?->work_hour_end
                        ? Carbon::parse($roster->shift->work_hour_end)->format('H:i')
                        : null,
                    'is_locked'   => (bool) $log->is_locked,
                    'is_holiday'  => (bool) $log->is_holiday,
                    'is_sat'      => (bool) $log->is_sat,
                    'is_sun'      => (bool) $log->is_sun,
                ];
            }
        }

        return response()->json([
            'employees'   => $employeeList,
            'dates'       => $dates,
            'autologData' => $autologData,
            'groups'      => $availableGroups,
            'period'      => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
        ]);
    }

    /**
     * Update individual autolog cell (check_in, check_out, lembur, lm).
     * POST /api/v1/supervisor/attendance/roster/update
     */
    public function updateRoster(Request $request)
    {
        $validated = $request->validate([
            'id'        => ['required', 'integer', 'exists:attendance_autologs,id'],
            'check_in'  => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'lembur'    => ['nullable', 'integer', 'min:0'],
            'lm'        => ['nullable', 'integer', 'min:0'],
        ]);

        $autolog = AttendanceAutolog::findOrFail($validated['id']);

        if ($autolog->is_locked) {
            return response()->json([
                'success' => false,
                'message' => 'Record ini terkunci. Tidak dapat diedit.',
            ], 422);
        }

        $autolog->update([
            'check_in'        => $validated['check_in'] ?? $autolog->check_in,
            'check_out'       => $validated['check_out'] ?? $autolog->check_out,
            'lembur'          => $validated['lembur'] ?? $autolog->lembur,
            'lm'              => $validated['lm'] ?? $autolog->lm,
            'is_manual_edit'  => true,
            'last_edited_at'  => now(),
            'last_edited_by'  => Auth::id(),
        ]);

        // Refresh buat dapetin format datetime yang udah di-cast
        $autolog->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil disimpan.',
            'data'    => [
                'id'        => $autolog->id,
                'check_in'  => $autolog->check_in ? $autolog->check_in->format('H:i') : null,
                'check_out' => $autolog->check_out ? $autolog->check_out->format('H:i') : null,
                'lembur'    => (int) $autolog->lembur,
                'lm'        => (int) $autolog->lm,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $departmentId = $request->input('department_id');
        $workPatternId = $request->input('work_pattern_id');
        $search = $request->input('search');

        // Query sama persis dengan index() — all employees, no pagination
        $employees = Employee::where(function ($q) use ($startDate, $endDate) {
            $q->whereHas('shiftRosters', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })->orWhereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            });
        })
            ->whereDoesntHave('groups', function ($q) {
                $q->where('reference_code', 'GRP-JKT');
            })
            ->with([
                'department',
                'position',
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($departmentId, function ($query, $deptId) {
                $query->where('department_id', $deptId);
            })
            ->when($workPatternId, function ($query, $wpId) {
                $query->where('work_pattern_id', $wpId);
            })
            ->orderBy('employee_code')
            ->get();

        $rows = [];
        $no = 1;
        foreach ($employees as $employee) {
            $logs = $employee->autologs;
            $rows[] = [
                'no' => $no++,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name ?? '-',
                'position' => $employee->position?->name ?? '-',
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('izin_duration', 1)->count(),
                'sakit' => $logs->where('sakit_duration', 1)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
            ];
        }

        $periodLabel = \Carbon\Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = 'Absensi_' . $periodLabel . '.xlsx';

        $departmentName = 'Semua Departemen';
        if ($departmentId) {
            $dept = Department::find($departmentId);
            $departmentName = $dept?->name ?? 'Semua Departemen';
        }

        return Excel::download(
            new \App\Modules\Supervisor\Attendance\Exports\AttendanceSummaryExport(
                data: $rows,
                periodStart: \Carbon\Carbon::parse($startDate)->format('d M Y'),
                periodEnd: \Carbon\Carbon::parse($endDate)->format('d M Y'),
                departmentName: $departmentName,
            ),
            $filename
        );
    }

    /**
     * Export detail autolog harian per employee ke Excel.
     */
    public function exportDetail($employeeId, Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $employee = Employee::with(['department', 'position'])
            ->where('id', $employeeId)
            ->firstOrFail();

        $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $rows = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);
        $totalOvertimeRaw = 0;
        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            $lemburMin = $log?->lembur ?? 0;
            $lemburDisplay = $lemburMin > 0 ? round($lemburMin / 60, 1) . ' jam' : '-';
            $totalOvertimeRaw += $lemburMin;

            $rows[] = [
                $employee->employee_code,
                $employee->name,
                $currentDate->translatedFormat('D, d M Y'),
                $log?->check_in ? $log->check_in->format('H:i') : '--:--',
                $log?->check_out ? $log->check_out->format('H:i') : '--:--',
                $lemburDisplay,
            ];

            $currentDate->addDay();
        }

        $totalOvertimeHours = round($totalOvertimeRaw / 60, 1);

        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $employee->name);
        $safeName = preg_replace('/_+/', '_', $safeName);
        $safeName = trim($safeName, '_');
        $periodLabel = \Carbon\Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = $safeName . '_' . $periodLabel . '.xlsx';

        return Excel::download(
            new \App\Modules\Supervisor\Attendance\Exports\AttendanceDetailExport(
                data: $rows,
                employee: [
                    'name' => $employee->name,
                    'code' => $employee->employee_code,
                    'department' => $employee->department?->name ?? '-',
                    'position' => $employee->position?->name ?? '-',
                ],
                periodStart: \Carbon\Carbon::parse($startDate)->format('d F Y'),
                periodEnd: \Carbon\Carbon::parse($endDate)->format('d F Y'),
                totalOvertime: $totalOvertimeHours,
            ),
            $filename
        );
    }
}
