<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Schedule\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceAutologController extends Controller
{
    /**
     * Display auditor logs summary per employee
     */
    public function index(Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;
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

        // For hr_branch: fetch payroll periods and override default dates
        if ($userType === 'hr_branch') {
            $payrollPeriods = PayrollPeriod::where('company_id', $companyId)
                
                ->orderBy('start_date', 'desc')
                ->get();

            // Default to latest payroll period if no dates specified
            if (! $request->has('start_date') && ! $request->has('end_date') && $payrollPeriods->isNotEmpty()) {
                $latestPeriod = $payrollPeriods->first();
                $startDate = $latestPeriod->start_date->toDateString();
                $endDate = $latestPeriod->end_date->toDateString();
                $selectedPeriodId = $latestPeriod->id;
            }
        }

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        // Build base query for employees
        $employeesQuery = Employee::where('is_active', 1)
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
            ->when($request->department_id, function ($query, $deptId) { $query->where('department_id', $deptId); })
            ->when($request->work_pattern_id, function ($query, $wpId) {
                $query->where('work_pattern_id', $wpId);
            })
            ->orderBy('employee_code');
        // Get all employees for stats (no pagination)
        $allEmployees = (clone $employeesQuery)->get();

        // Transform all employees for stats calculation
        $allSummaryData = [];
        foreach ($allEmployees as $employee) {
            $periodSnapshot = null;
            $logs = $employee->autologs;
            $allSummaryData[] = [
                'id' => $employee->id,
                'employee_code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name,
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name,
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('overtime_converted_hours'), 1),
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
            $periodSnapshot = null;
            $logs = $employee->autologs;
            $summaryData[] = [
                'id' => $employee->id,
                'employee_code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name,
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name,
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('overtime_converted_hours'), 1),
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
        $allLogsQuery = AttendanceAutolog::where('company_id', $companyId)
            
            ->whereBetween('date', [$startDate, $endDate]);

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
            'departments' => Department::where('company_id', $companyId)->get(['id', 'name']),
            'workPatterns' => WorkPattern::where('company_id', $companyId)->get(['id', 'name']),
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
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        // Get period from request
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        // Get filter params
        $departmentId = $request->input('department_id');
        $workPatternId = $request->input('work_pattern_id');
        $search = $request->input('search');

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        // Get all employees with filters (no pagination for print)
        $employees = Employee::where('is_active', 1)
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
            ->when($departmentId, function ($query, $deptId) use ($month, $year) {
                $query->whereHas('employeePeriode', function ($q) use ($deptId, $month, $year) {
                    $q->where('month', $month)->where('year', $year)->where('department_id', $deptId);
                });
            })
            ->when($workPatternId, function ($query, $wpId) {
                $query->where('work_pattern_id', $wpId);
            })
            ->orderBy('employee_code')
            ->get();

        // Transform data for summary
        $summaryData = [];
        foreach ($employees as $employee) {
            $periodSnapshot = null;
            $logs = $employee->autologs;
            $summaryData[] = [
                'employee_code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name ?? '-',
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name ?? '-',
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur' => round($logs->sum('overtime_converted_hours'), 1),
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

        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;
        // Get period
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        $employee = Employee::with([
            'department',
            'position',
            
        ])
            
            
            ->where('id', $employeeId)
            ->firstOrFail();

        $periodSnapshot = null;

        $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern') // Eager load roster, shift, and work pattern
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $isFixed = $logs->contains(fn ($log) => $log->employeeShiftRoster?->workPattern?->employee_type === 'SHIFT');

        $dailyData = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            $dailyData[] = [
                'date' => $dateStr,
                'day' => $currentDate->translatedFormat('D'),
                'date_display' => $currentDate->format('d M Y'),
                'is_weekend' => $currentDate->isSunday(),
                'check_in' => $log?->check_in ? $log->check_in->format('H:i') : null,
                'check_out' => $log?->check_out ? $log->check_out->format('H:i') : null,
                'overtime_minutes' => $log?->overtime_duration ?? 0,
                'overtime_display' => $this->formatOvertime($log?->overtime_duration ?? 0),
                'status' => $log?->status ?? 'pending',
                'status_label' => $this->getStatusLabel($log?->status ?? 'pending'),
                'status_badge' => $this->getStatusBadge($log?->status ?? 'pending'),
                'is_locked' => $log?->is_locked ?? false,
                'notes' => $log?->notes,
                'overtime_converted_hours' => $log?->overtime_converted_hours ?? 0,
                'shift_start' => $log?->employeeShiftRoster?->shift?->work_hour_start ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_start)->format('H:i') : null,
                'shift_end' => $log?->employeeShiftRoster?->shift?->work_hour_end ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_end)->format('H:i') : null,
                'is_sat' => $log?->is_sat ?? false,
                'is_holiday' => $log?->is_holiday ?? false,
                'is_fixed' => $isFixed,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name,
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name,
            ],
            'dailyData' => $dailyData,
            'summary' => [
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur_minutes' => $logs->sum('overtime_duration'),
                'lembur' => round($logs->sum('overtime_converted_hours'), 1),
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
     * Print detail report for individual employee using blade view
     */
    public function printDetail($employeeId, Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        // Get period
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        $employee = Employee::with([
            'department',
            'position',
            
        ])
            
            
            ->where('id', $employeeId)
            ->firstOrFail();

        $periodSnapshot = null;

        $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $isFixed = $logs->contains(fn ($log) => $log->employeeShiftRoster?->workPattern?->employee_type === 'SHIFT');

        $dailyData = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            $dailyData[] = [
                'date' => $dateStr,
                'day' => $currentDate->translatedFormat('D'),
                'date_display' => $currentDate->format('d M Y'),
                'is_weekend' => $currentDate->isSunday(),
                'check_in' => $log?->check_in ? $log->check_in->format('H:i') : null,
                'check_out' => $log?->check_out ? $log->check_out->format('H:i') : null,
                'overtime_minutes' => $log?->overtime_duration ?? 0,
                'overtime_display' => $this->formatOvertime($log?->overtime_duration ?? 0),
                'overtime_converted_hours' => $log?->overtime_converted_hours ?? 0,
                'status' => $log?->status ?? 'pending',
                'status_label' => $this->getStatusLabel($log?->status ?? 'pending'),
                'shift_start' => $log?->employeeShiftRoster?->shift?->work_hour_start ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_start)->format('H:i') : null,
                'shift_end' => $log?->employeeShiftRoster?->shift?->work_hour_end ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_end)->format('H:i') : null,
                'is_sat' => $log?->is_sat ?? false,
                'is_holiday' => $log?->is_holiday ?? false,
                'is_fixed' => $isFixed,
            ];

            $currentDate->addDay();
        }

        $summary = [
            'hadir' => $logs->where('status', 'present')->count(),
            'lembur_minutes' => $logs->sum('overtime_duration') / 60,
            'lembur' => round($logs->sum('overtime_converted_hours'), 1),
            'cuti' => $logs->where('status', 'leave')->count(),
            'izin' => $logs->where('status', 'permit')->where('deduct_attendance', 1)->count(),
            'sakit' => $logs->where('status', 'permit')->where('deduct_attendance', 0)->count(),
            'absen' => $logs->where('status', 'absent')->count(),
        ];

        return view('supervisor.attendance.autolog-detail-print', [
            'employee' => [
                'id' => $employee->id,
                'code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name ?? '-',
                'position' => $periodSnapshot?->position?->name ?? $employee->position?->name ?? '-',
            ],
            'dailyData' => $dailyData,
            'summary' => $summary,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }

    /**
     * Adjustment: update leave data and overtime converted hours
     */
    public function adjustment(Request $request)
    {
        // 1. Inisialisasi & Validasi Awal
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        // Tambahkan timeout & memory limit untuk jaga-jaga jika data ribuan
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $startDate = Carbon::parse($request->input('start_date'))->toDateString();
        $endDate = Carbon::parse($request->input('end_date'))->toDateString();

        // 2. Ambil Data Utama (Gunakan Eager Loading untuk performa)
        $autologs = AttendanceAutolog::with(['employeeShiftRoster.workPattern'])
            
            
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $employeeIds = Employee::withoutGlobalScopes()
            
            
            ->pluck('id');

        // 3. Ambil Data Cuti (Saring berdasarkan range tanggal yang relevan)
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

        // 4. Proses Update dengan Transaction (Supaya tidak "ngejam")
        try {
            DB::beginTransaction();

            foreach ($autologs as $autolog) {
                $updateData = [];
                // Pastikan kolom date adalah instance Carbon
                $dateStr = Carbon::parse($autolog->date)->toDateString();

                // --- LOGIKA CUTI ---
                // Cari apakah karyawan ini punya cuti yang mencakup tanggal autolog ini
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

                    // Kita isi 1 (hari) saja, bukan totalDays agar tidak double-count di laporan
                    $updateData['sakit_duration'] = ($leaveTypeCode === 'SKT') ? 1 : 0;
                    $updateData['izin_duration'] = ($leaveTypeCode === 'ITM') ? 1 : 0;
                } else {
                    $updateData['is_leave'] = false;
                    $updateData['leave_id'] = null;
                    $updateData['deduct_day'] = ($autolog->status === 'absent') ? 1 : null;
                    $updateData['sakit_duration'] = 0;
                    $updateData['izin_duration'] = 0;
                }

                // --- LOGIKA LEMBUR (TIERED MULTIPLIER) ---
                if ($autolog->overtime_duration > 0) {
                    $overtimeHours = $autolog->overtime_duration / 60;
                    $employeeType = $autolog->employeeShiftRoster?->workPattern?->employee_type;
                    $isShift = $employeeType === 'SHIFT';
                    $isSat = $autolog->is_sat;
                    $isHoliday = $autolog->is_holiday;

                    if ($isShift && $isHoliday) {
                        $remainingHours = max(0, $overtimeHours - 1);
                        $converted = 0;

                        if ($isSat) {
                            // Sabtu + Holiday: (jam - 1) lalu jam 1-5 x2, jam 6 x3, jam 7+ x1
                            for ($i = 1; $i <= ceil($remainingHours); $i++) {
                                $seg = min(1, max(0, $remainingHours - ($i - 1)));
                                if ($i <= 5) {
                                    $converted += $seg * 2;
                                } elseif ($i === 6) {
                                    $converted += $seg * 3;
                                } else {
                                    $converted += $seg * 4;
                                }
                            }
                        } else {
                            // Holiday (non-Sabtu): (jumlah lembur - 1) x 2
                            $converted = $remainingHours * 2;
                        }
                    } else {
                        // Default + Sabtu biasa: jam ke-1 x1.5, jam ke-2+ x2
                        $converted = 0;

                        // JIKA LEMBUR 0.5 JAM ATAU KURANG
                        if ($overtimeHours <= 0.5) {
                            // Dikali 1 saja (tanpa pengali 1.5)
                            $converted = $overtimeHours * 1;

                            // Catatan: Jika maksudmu lembur <= 0.5 jam itu HANGUS (dianggap 0),
                            // ubah kodenya menjadi: $converted = 0;
                        }
                        // JIKA LEMBUR DI ATAS 0.5 JAM
                        else {
                            $firstHour = min($overtimeHours, 1);
                            $converted += $firstHour * 1.5;

                            if ($overtimeHours > 1) {
                                $overtimeRemaining = $overtimeHours - 1;
                                $converted += $overtimeRemaining * 2;
                            }
                        }
                    }

                    $updateData['overtime_converted_hours'] = round($converted, 2);
                } else {
                    $updateData['overtime_converted_hours'] = null;
                }

                // Eksekusi Update
                $autolog->update($updateData);
                $updatedCount++;
            }

            DB::commit();

            return back()->with('success', "Adjustment berhasil! {$updatedCount} records telah diperbarui.");

        } catch (\Exception $e) {
            DB::rollBack();
            // Log error untuk mempermudah debugging jika gagal
            Log::error('Adjustment Error: '.$e->getMessage());

            return back()->with('error', 'Terjadi kesalahan: '.$e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $departmentId = $request->input('department_id');
        $workPatternId = $request->input('work_pattern_id');
        $search = $request->input('search');

        $autologs = AttendanceAutolog::with('employee')
            
            
            ->whereBetween('date', [$startDate, $endDate])
            ->when($search, function ($query, $search) {
                $query->whereHas('employee', function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($departmentId, function ($query, $deptId) {
                $query->whereHas('employee', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            })
            ->when($workPatternId, function ($query, $wpId) {
                $query->whereHas('employee', function ($q) use ($wpId) {
                    $q->where('work_pattern_id', $wpId);
                });
            })
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        $data = $autologs->map(function ($log) {
            return [
                'no' => $log->employee?->employee_code ?? '-',
                'nama' => $log->employee?->name ?? '-',
                'in' => $log->check_in ? $log->check_in->format('H:i') : '-',
                'out' => $log->check_out ? $log->check_out->format('H:i') : '-',
                'overtime' => $log->overtime_duration ?? 0,
                'total_overtime' => $log->overtime_converted_hours ?? 0,
            ];
        });

        $filename = 'autolog_'.Carbon::now()->format('Ymd_His').'.xlsx';

        return Excel::download(new class($data) implements FromCollection, WithHeadings
        {
            private $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function collection()
            {
                return collect($this->data);
            }

            public function headings(): array
            {
                return ['No', 'Nama', 'IN', 'Out', 'Overtime', 'Total Overtime'];
            }
        }, $filename);
    }
}
