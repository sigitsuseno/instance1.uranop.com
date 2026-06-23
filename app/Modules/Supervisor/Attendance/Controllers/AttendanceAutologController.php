<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // Build base query: hanya employee yang punya roster di periode ini
        $employeesQuery = Employee::whereHas('shiftRosters', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })
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
            $logs = $employee->autologs;
            $summaryData[] = [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
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
                'code' => $employee->employee_code,
                'name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
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
    }

    /**
     * Adjustment: update leave data and overtime converted hours
     */
    public function adjustment(Request $request)
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
                            $converted = $remainingHours * 2;
                        }
                    } else {
                        $converted = 0;

                        if ($overtimeHours <= 0.5) {
                            $converted = $overtimeHours * 1;
                        } else {
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

    public function export(Request $request)
    {
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
