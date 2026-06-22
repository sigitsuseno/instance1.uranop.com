<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Exports\RekapAbsensiExport;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Schedule\Models\WorkPattern;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class RekapAbsensiController extends Controller
{
    public function index(Request $request)
    {
        Log::info('RekapAbsensiController index hit!');
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;
        $userType = Auth::user()->user_type;

        $payrollPeriods = collect();

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod();
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        $selectedPeriodId = null;

        if ($userType === 'hr_branch') {
            $payrollPeriods = PayrollPeriod::where('company_id', $companyId)
                
                ->orderBy('start_date', 'desc')
                ->get();

            if (! $request->has('start_date') && ! $request->has('end_date') && $payrollPeriods->isNotEmpty()) {
                $latestPeriod = $payrollPeriods->first();
                $startDate = $latestPeriod->start_date->toDateString();
                $endDate = $latestPeriod->end_date->toDateString();
                $selectedPeriodId = $latestPeriod->id;
            }
        }

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
            
            
            ->whereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
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
            ->orderBy('employee_code')
            ->get();

        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }

        $data = [];
        foreach ($employees as $i => $employee) {
            $periodSnapshot = null;
            $logs = $employee->autologs->keyBy(fn ($l) => $l->date->toDateString());

            $row = [
                'no' => $i + 1,
                'employee_id' => $employee->id,
                'employee_code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name,
            ];

            foreach ($dates as $date) {
                $dateStr = $date->toDateString();
                $log = $logs->get($dateStr);
                $row['days'][$dateStr] = [
                    'status' => $this->getStatusText($log),
                    'lembur' => $log ? (float) ($log->overtime_converted_hours ?? 0) : 0,
                ];
            }

            $data[] = $row;
        }

        return response()->json([
            'employees' => $data,
            'dates' => collect($dates)->map(fn ($d) => [
                'date' => $d->toDateString(),
                'day_name' => $d->translatedFormat('l'),
                'day_short' => $d->translatedFormat('D'),
                'serial' => $d->format('Ymd'),
            ]),
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
            'companyName' => Auth::user()->company->name ?? 'ALL IN KARANGJATI',
        ]);
    }

    public function export(Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;
        $userType = Auth::user()->user_type;

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod();
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        $employees = Employee::where('is_active', 1)
            ->with([
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
                'employeePeriode',
            ])
            
            
            ->whereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->department_id, function ($query, $deptId) { $query->where('department_id', $deptId); })
            ->orderBy('employee_code')
            ->get();

        $companyName = Auth::user()->company->name ?? 'ALL IN KARANGJATI';
        $filename = 'rekap_absensi_'.Carbon::now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new RekapAbsensiExport($employees, $startDate, $endDate, $companyName),
            $filename
        );
    }

    public function print(Request $request)
    {
        $branchId = session('branch_id');
        $companyId = Auth::user()->company_id;

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod();
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        $month = Carbon::parse($endDate)->month;
        $year = Carbon::parse($endDate)->year;

        $employees = Employee::where('is_active', 1)
            ->with([
                'autologs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                },
                
            ])
            
            
            ->whereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->department_id, function ($query, $deptId) { $query->where('department_id', $deptId); })
            ->orderBy('employee_code')
            ->get();

        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }

        $data = [];
        foreach ($employees as $i => $employee) {
            $periodSnapshot = null;
            $logs = $employee->autologs->keyBy(fn ($l) => $l->date->toDateString());

            $row = [
                'no' => $i + 1,
                'employee_code' => $periodSnapshot->employee_code ?? $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $periodSnapshot?->department?->name ?? $employee->department?->name,
            ];

            foreach ($dates as $date) {
                $dateStr = $date->toDateString();
                $log = $logs->get($dateStr);
                $row['days'][$dateStr] = [
                    'status' => $this->getStatusText($log),
                    'lembur' => $log ? (float) ($log->overtime_converted_hours ?? 0) : 0,
                ];
            }

            $data[] = $row;
        }

        $departmentName = 'Semua Departemen';
        if ($request->department_id) {
            $dept = Department::find($request->department_id);
            $departmentName = $dept?->name ?? 'Semua Departemen';
        }

        return view('supervisor.attendance.rekap-absensi-print', [
            'employees' => $data,
            'dates' => $dates,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'departmentName' => $departmentName,
            'filters' => [
                'search' => $request->search,
                'department_id' => $request->department_id,
            ],
        ]);
    }

    protected function getStatusText($log): string
    {
        if (! $log) {
            return '';
        }

        if ($log->sakit_duration > 0) {
            return 'SAKIT';
        }

        if ($log->izin_duration > 0) {
            return 'I';
        }

        return match ($log->status) {
            'present' => 'H',
            'leave' => 'CUTI',
            'absent' => '-',
            'holiday' => 'LIBUR',
            'off' => 'OFF',
            default => '-',
        };
    }

    protected function getPeriod(): array
    {
        $date = Carbon::now();
        if ($date->day >= 25) {
            $start = $date->copy()->day(25);
            $end = $date->copy()->addMonth()->day(24);
        } else {
            $start = $date->copy()->subMonth()->day(25);
            $end = $date->copy()->day(24);
        }

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }
}
