<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\WorkPattern;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

class StaffOvertimeController extends Controller
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

        // Fetch payroll periods
        $payrollPeriods = PayPeriod::orderBy('start_date', 'desc')->get();

        $selectedPeriodId = null;
        if (! $request->has('start_date') && ! $request->has('end_date') && $payrollPeriods->isNotEmpty()) {
            $latestPeriod = $payrollPeriods->first();
            $startDate = $latestPeriod->start_date->toDateString();
            $endDate = $latestPeriod->end_date->toDateString();
            $selectedPeriodId = $latestPeriod->id;
        }

        $tab = $request->input('tab', 'jakarta');

        // Build employee query based on tab
        $employeesQuery = Employee::with(['department', 'position'])
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
            });

        if ($tab === 'jakarta') {
            $employeesQuery->whereHas('groups', function ($q) {
                $q->where('reference_code', 'GRP-JKT');
            });
            $employeesQuery->with(['attendancePrepares' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }]);
        } else {
            $employeesQuery->where('is_active', 1);
            $employeesQuery->whereHas('groups', function ($q) {
                $q->whereIn('reference_code', ['GRP-ALLIN', 'GRP-PS1', 'GRP-GD', 'GRP-SS', 'GRP-SPR']);
            });
            $employeesQuery->with(['autologs' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }]);
        }

        $employeesQuery->orderBy('employee_code');

        // All employees for stats
        $allEmployees = (clone $employeesQuery)->get();

        $allSummaryData = [];
        foreach ($allEmployees as $employee) {
            if ($tab === 'jakarta') {
                $logs = $employee->attendancePrepares;
                $allSummaryData[] = [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->name,
                    'hadir' => $logs->whereIn('status', ['present', 'late'])->count(),
                    'lembur' => round($logs->sum('lembur') / 60, 1),
                    'cuti' => $logs->where('status', 'leave')->count(),
                    'izin' => $logs->where('status', 'permit')->count(),
                    'sakit' => $logs->where('is_permit_flag', 1)->where('deduct_attendance', 0)->count(),
                    'absen' => $logs->where('status', 'absent')->count(),
                ];
            } else {
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
                ];
            }
        }

        $stats = [
            'total_employees' => count($allSummaryData),
            'present' => collect($allSummaryData)->sum('hadir'),
            'total_overtime' => collect($allSummaryData)->sum('lembur'),
            'leave' => collect($allSummaryData)->sum('cuti'),
            'permit' => collect($allSummaryData)->sum('izin'),
            'sakit' => collect($allSummaryData)->sum('sakit'),
            'absent' => collect($allSummaryData)->sum('absen'),
        ];

        // Paginated
        $employees = $employeesQuery->paginate(20)->withQueryString();
        $summaryData = [];
        foreach ($employees as $employee) {
            if ($tab === 'jakarta') {
                $logs = $employee->attendancePrepares;
                $summaryData[] = [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->name,
                    'hadir' => $logs->whereIn('status', ['present', 'late'])->count(),
                    'lembur' => round($logs->sum('lembur') / 60, 1),
                    'cuti' => $logs->where('status', 'leave')->count(),
                    'izin' => $logs->where('status', 'permit')->count(),
                    'sakit' => $logs->where('is_permit_flag', 1)->where('deduct_attendance', 0)->count(),
                    'absen' => $logs->where('status', 'absent')->count(),
                ];
            } else {
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
                ];
            }
        }

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
                'tab' => $tab,
            ],
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'workPatterns' => WorkPattern::all(['id', 'name']),
            'payrollPeriods' => collect($payrollPeriods)->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'start_date' => Carbon::parse($p->start_date)->toDateString(),
                    'end_date' => Carbon::parse($p->end_date)->toDateString(),
                ];
            }),
            'pagination' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ],
        ]);
    }

    protected function getPeriod($period = null)
    {
        $date = $period ? Carbon::parse($period . '-01') : Carbon::now();
        if ($date->day >= 25) {
            $start = $date->copy()->day(25);
            $end = $date->copy()->addMonth()->day(24);
        } else {
            $start = $date->copy()->subMonth()->day(25);
            $end = $date->copy()->day(24);
        }

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }

    public function print(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $departmentId = $request->input('department_id');
        $workPatternId = $request->input('work_pattern_id');
        $search = $request->input('search');
        $tab = $request->input('tab', 'jakarta');

        $employeesQuery = Employee::with(['department', 'position'])
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
            });

        if ($tab === 'jakarta') {
            $employeesQuery->whereHas('groups', function ($q) {
                $q->where('reference_code', 'GRP-JKT');
            });
            $employeesQuery->with(['attendancePrepares' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }]);
        } else {
            $employeesQuery->where('is_active', 1);
            $employeesQuery->whereHas('groups', function ($q) {
                $q->whereIn('reference_code', ['GRP-ALLIN', 'GRP-PS1', 'GRP-GD', 'GRP-SS', 'GRP-SPR']);
            });
            $employeesQuery->with(['autologs' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }]);
        }

        $employeesQuery->orderBy('employee_code');
        $employees = $employeesQuery->get();

        $summaryData = [];
        foreach ($employees as $employee) {
            if ($tab === 'jakarta') {
                $logs = $employee->attendancePrepares;
                $summaryData[] = [
                    'employee_code' => $employee->employee_code,
                    'employee_name' => $employee->name,
                    'department' => $employee->department?->name ?? '-',
                    'position' => $employee->position?->name ?? '-',
                    'hadir' => $logs->whereIn('status', ['present', 'late'])->count(),
                    'lembur' => round($logs->sum('lembur') / 60, 1),
                    'cuti' => $logs->where('status', 'leave')->count(),
                    'izin' => $logs->where('status', 'permit')->count(),
                    'sakit' => $logs->where('is_permit_flag', 1)->where('deduct_attendance', 0)->count(),
                    'absen' => $logs->where('status', 'absent')->count(),
                ];
            } else {
                $logs = $employee->autologs;
                $summaryData[] = [
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
        }

        $stats = [
            'total_employees' => count($summaryData),
            'present' => collect($summaryData)->sum('hadir'),
            'total_overtime_hours' => collect($summaryData)->sum('lembur'),
            'leave' => collect($summaryData)->sum('cuti'),
            'permit' => collect($summaryData)->sum('izin'),
            'sakit' => collect($summaryData)->sum('sakit'),
            'absent' => collect($summaryData)->sum('absen'),
        ];

        $departmentName = 'Semua Departemen';
        if ($departmentId) {
            $dept = Department::find($departmentId);
            $departmentName = $dept?->name ?? 'Semua Departemen';
        }

        $company = Company::first();
        $tabLabel = $tab === 'jakarta' ? 'Jakarta' : 'Ungaran';

        $pdf = Pdf::loadView('supervisor.attendance.lembur-print-pdf', [
            'company' => $company,
            'employees' => $summaryData,
            'stats' => $stats,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'departmentName' => $departmentName,
            'tabLabel' => $tabLabel,
            'filters' => [
                'search' => $search,
                'department_id' => $departmentId,
                'work_pattern_id' => $workPatternId,
            ],
        ]);

        $pdf->setPaper('A4', 'portrait');

        $periodLabel = Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = 'Lembur_Staf_' . $tabLabel . '_' . $periodLabel . '.pdf';

        return $pdf->download($filename);
    }

    public function export(Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();

        $departmentId = $request->input('department_id');
        $workPatternId = $request->input('work_pattern_id');
        $search = $request->input('search');
        $tab = $request->input('tab', 'jakarta');

        $employeesQuery = Employee::with(['department', 'position'])
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
            });

        if ($tab === 'jakarta') {
            $employeesQuery->whereHas('groups', function ($q) {
                $q->where('reference_code', 'GRP-JKT');
            });
            $employeesQuery->with(['attendancePrepares' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }]);
        } else {
            $employeesQuery->where('is_active', 1);
            $employeesQuery->whereHas('groups', function ($q) {
                $q->whereIn('reference_code', ['GRP-ALLIN', 'GRP-PS1', 'GRP-GD', 'GRP-SS', 'GRP-SPR']);
            });
            $employeesQuery->with(['autologs' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }]);
        }

        $employeesQuery->orderBy('employee_code');
        $employees = $employeesQuery->get();

        $data = $employees->map(function ($employee) use ($tab) {
            if ($tab === 'jakarta') {
                $logs = $employee->attendancePrepares;
                $lembur = round($logs->sum('lembur') / 60, 1);
                $hadir = $logs->whereIn('status', ['present', 'late'])->count();
                $cuti = $logs->where('status', 'leave')->count();
                $izin = $logs->where('status', 'permit')->count();
                $sakit = $logs->where('is_permit_flag', 1)->where('deduct_attendance', 0)->count();
                $absen = $logs->where('status', 'absent')->count();
            } else {
                $logs = $employee->autologs;
                $lembur = round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1);
                $hadir = $logs->where('status', 'present')->count();
                $cuti = $logs->where('status', 'leave')->count();
                $izin = $logs->where('izin_duration', 1)->count();
                $sakit = $logs->where('sakit_duration', 1)->count();
                $absen = $logs->where('status', 'absent')->count();
            }

            return [
                'no' => $employee->employee_code,
                'nama' => $employee->name,
                'departemen' => $employee->department?->name ?? '-',
                'hadir' => $hadir,
                'lembur' => $lembur,
                'cuti' => $cuti,
                'izin' => $izin,
                'sakit' => $sakit,
                'absen' => $absen,
            ];
        });

        $filename = 'lembur_staf_' . $tab . '_' . Carbon::now()->format('Ymd_His') . '.xlsx';

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
                return ['No', 'Nama', 'Departemen', 'Hadir', 'Lembur', 'Cuti', 'Izin', 'Sakit', 'Absen'];
            }
        }, $filename);
    }

    public function show($employeeId, Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();
        $tab = $request->input('tab', 'jakarta');

        $employeeQuery = Employee::with(['department', 'position'])
            ->where('id', $employeeId);

        if ($tab !== 'jakarta') {
            $employeeQuery->where('is_active', 1);
        }

        $employee = $employeeQuery->firstOrFail();

        if ($tab === 'jakarta') {
            $logs = AttendancePrepare::with('employeeShiftRoster.workPattern')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get();
        } else {
            $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get();
        }

        $isFixed = $logs->contains(fn ($log) => $log->employeeShiftRoster?->workPattern?->employee_type === 'SHIFT');

        $dailyData = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            if ($tab === 'jakarta') {
                $overtimeConvertedHours = round(($log?->lembur ?? 0) / 60, 1);
                $isSat = $currentDate->isSaturday();
                $isHoliday = $log?->is_holiday_flag ?? false;
            } else {
                $overtimeConvertedHours = $log?->lembur_calc ?? 0;
                $isSat = $log?->is_sat ?? false;
                $isHoliday = $log?->is_holiday ?? false;
            }

            $checkIn = $log?->check_in ? $log->check_in->format('H:i') : null;
            $checkOut = $log?->check_out ? $log->check_out->format('H:i') : null;

            $dailyData[] = [
                'date' => $dateStr,
                'day' => $currentDate->translatedFormat('D'),
                'date_display' => $currentDate->format('d M Y'),
                'is_weekend' => $currentDate->isSunday(),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'lembur' => $log?->lembur ?? 0,
                'lembur_display' => $this->formatOvertime($log?->lembur ?? 0),
                'status' => $log?->status ?? 'pending',
                'status_label' => $this->getStatusLabel($log?->status ?? 'pending'),
                'status_badge' => $this->getStatusBadge($log?->status ?? 'pending'),
                'is_locked' => $log?->is_locked ?? false,
                'notes' => $log?->notes,
                'lembur_calc' => $overtimeConvertedHours,
                'shift_start' => $log?->employeeShiftRoster?->shift?->work_hour_start
                    ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_start)->format('H:i')
                    : null,
                'shift_end' => $log?->employeeShiftRoster?->shift?->work_hour_end
                    ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_end)->format('H:i')
                    : null,
                'is_sat' => $isSat,
                'is_holiday' => $isHoliday,
                'is_fixed' => $isFixed,
            ];

            $currentDate->addDay();
        }

        if ($tab === 'jakarta') {
            $summary = [
                'hadir' => $logs->whereIn('status', ['present', 'late'])->count(),
                'lembur_minutes' => $logs->sum('lembur'),
                'lembur' => round($logs->sum('lembur') / 60, 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('status', 'permit')->count(),
                'sakit' => $logs->where('is_permit_flag', 1)->where('deduct_attendance', 0)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
            ];
        } else {
            $summary = [
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur_minutes' => $logs->sum('lembur'),
                'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('izin_duration', 1)->count(),
                'sakit' => $logs->where('sakit_duration', 1)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
            ];
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
            'summary' => $summary,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }

    public function printDetail($employeeId, Request $request)
    {
        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()))->toDateString();
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()))->toDateString();
        $tab = $request->input('tab', 'jakarta');

        $employeeQuery = Employee::with(['department', 'position'])
            ->where('id', $employeeId);

        if ($tab !== 'jakarta') {
            $employeeQuery->where('is_active', 1);
        }

        $employee = $employeeQuery->firstOrFail();

        if ($tab === 'jakarta') {
            $logs = AttendancePrepare::with('employeeShiftRoster.workPattern')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get();
        } else {
            $logs = AttendanceAutolog::with('employeeShiftRoster.workPattern')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->get();
        }

        $isFixed = $logs->contains(fn ($log) => $log->employeeShiftRoster?->workPattern?->employee_type === 'SHIFT');

        $dailyData = [];
        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->toDateString();
            $log = $logs->first(fn ($l) => $l->date->toDateString() === $dateStr);

            if ($tab === 'jakarta') {
                $overtimeConvertedHours = round(($log?->lembur ?? 0) / 60, 1);
                $isSat = $currentDate->isSaturday();
                $isHoliday = $log?->is_holiday_flag ?? false;
            } else {
                $overtimeConvertedHours = $log?->lembur_calc ?? 0;
                $isSat = $log?->is_sat ?? false;
                $isHoliday = $log?->is_holiday ?? false;
            }

            $checkIn = $log?->check_in ? $log->check_in->format('H:i') : null;
            $checkOut = $log?->check_out ? $log->check_out->format('H:i') : null;

            $dailyData[] = [
                'date' => $dateStr,
                'day' => $currentDate->translatedFormat('D'),
                'date_display' => $currentDate->format('d M Y'),
                'is_weekend' => $currentDate->isSunday(),
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'lembur' => $log?->lembur ?? 0,
                'lembur_display' => $this->formatOvertime($log?->lembur ?? 0),
                'lembur_calc' => $overtimeConvertedHours,
                'status' => $log?->status ?? 'pending',
                'status_label' => $this->getStatusLabel($log?->status ?? 'pending'),
                'shift_start' => $log?->employeeShiftRoster?->shift?->work_hour_start
                    ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_start)->format('H:i')
                    : null,
                'shift_end' => $log?->employeeShiftRoster?->shift?->work_hour_end
                    ? Carbon::parse($log->employeeShiftRoster->shift->work_hour_end)->format('H:i')
                    : null,
                'is_sat' => $isSat,
                'is_holiday' => $isHoliday,
                'is_fixed' => $isFixed,
            ];

            $currentDate->addDay();
        }

        if ($tab === 'jakarta') {
            $summary = [
                'hadir' => $logs->whereIn('status', ['present', 'late'])->count(),
                'lembur_minutes' => $logs->sum('lembur'),
                'lembur' => round($logs->sum('lembur') / 60, 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('status', 'permit')->count(),
                'sakit' => $logs->where('is_permit_flag', 1)->where('deduct_attendance', 0)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
            ];
        } else {
            $summary = [
                'hadir' => $logs->where('status', 'present')->count(),
                'lembur_minutes' => $logs->sum('lembur'),
                'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
                'cuti' => $logs->where('status', 'leave')->count(),
                'izin' => $logs->where('izin_duration', 1)->count(),
                'sakit' => $logs->where('sakit_duration', 1)->count(),
                'absen' => $logs->where('status', 'absent')->count(),
            ];
        }

        $company = Company::first();

        $pdf = Pdf::loadView('supervisor.attendance.lembur-detail-print-pdf', [
            'company' => $company,
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
            'tab' => $tab,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $employee->name);
        $safeName = preg_replace('/_+/', '_', $safeName);
        $safeName = trim($safeName, '_');
        $periodLabel = Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = 'Lembur_' . $safeName . '_' . $periodLabel . '.pdf';

        return $pdf->download($filename);
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

        return round($minutes / 60, 1) . ' jam';
    }

    protected function getStatusLabel($status)
    {
        $labels = [
            'present' => 'Hadir', 'late' => 'Terlambat', 'absent' => 'Absen',
            'leave' => 'Cuti', 'permit' => 'Izin', 'holiday' => 'Libur',
            'off' => 'Off', 'pending' => 'Menunggu',
        ];

        return $labels[$status] ?? $status;
    }

    protected function getStatusBadge($status)
    {
        $badges = [
            'present' => 'bg-green-100 text-green-800', 'late' => 'bg-yellow-100 text-yellow-800',
            'absent' => 'bg-red-100 text-red-800', 'leave' => 'bg-blue-100 text-blue-800',
            'permit' => 'bg-purple-100 text-purple-800', 'holiday' => 'bg-gray-100 text-gray-800',
            'off' => 'bg-orange-100 text-orange-800', 'pending' => 'bg-yellow-100 text-yellow-800',
        ];

        return $badges[$status] ?? 'bg-gray-100 text-gray-800';
    }
}
