<?php

namespace App\Modules\Supervisor\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Exports\RekapAbsensiExport;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Department;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\WorkPattern;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RekapAbsensiController extends Controller
{
    public function index(Request $request)
    {
        // Get period
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod();
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

        $employees = Employee::where('is_active', 1)
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
            ->whereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
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
            ->orderBy('employee_code')
            ->get();

        // Build date range
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }

        $data = [];
        foreach ($employees as $i => $employee) {
            $logs = $employee->autologs->keyBy(fn ($l) => $l->date->toDateString());

            $row = [
                'no' => $i + 1,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
            ];

            foreach ($dates as $date) {
                $dateStr = $date->toDateString();
                $log = $logs->get($dateStr);
                $row['days'][$dateStr] = [
                    'status' => $this->getStatusText($log),
                    'lembur' => $log ? round((($log->lembur ?? 0) + ($log->lm ?? 0)) / 60, 1) : 0,
                ];
            }

            $data[] = $row;
        }

        // Get company name
        $company = Company::first();

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
            'companyName' => $company?->name ?? 'ALL IN KARANGJATI',
        ]);
    }

    public function export(Request $request)
    {
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod();
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        $employees = Employee::where('is_active', 1)
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
            ->whereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->department_id, function ($query, $deptId) {
                $query->where('department_id', $deptId);
            })
            ->orderBy('employee_code')
            ->get();

        $company = Company::first();
        $companyName = $company?->name ?? 'ALL IN KARANGJATI';
        $filename = 'rekap_absensi_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new RekapAbsensiExport($employees, $startDate, $endDate, $companyName),
            $filename
        );
    }

    public function print(Request $request)
    {
        // Boost limits for large PDF generation
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate = Carbon::parse($request->end_date)->toDateString();
        } else {
            $period = $this->getPeriod();
            $startDate = $period['start'];
            $endDate = $period['end'];
        }

        $employees = Employee::where('is_active', 1)
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
            ->whereHas('autologs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($request->department_id, function ($query, $deptId) {
                $query->where('department_id', $deptId);
            })
            ->orderBy('employee_code')
            ->get();

        // Build date range
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = $current->copy();
            $current->addDay();
        }

        $data = [];
        foreach ($employees as $i => $employee) {
            $logs = $employee->autologs->keyBy(fn ($l) => $l->date->toDateString());

            $row = [
                'no' => $i + 1,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
            ];

            foreach ($dates as $date) {
                $dateStr = $date->toDateString();
                $log = $logs->get($dateStr);
                $row['days'][$dateStr] = [
                    'status' => $this->getStatusText($log),
                    'lembur' => $log ? round((($log->lembur ?? 0) + ($log->lm ?? 0)) / 60, 1) : 0,
                ];
            }

            $data[] = $row;
        }

        $departmentName = 'Semua Departemen';
        if ($request->department_id) {
            $dept = Department::find($request->department_id);
            $departmentName = $dept?->name ?? 'Semua Departemen';
        }

        $company = Company::first();

        $pdf = Pdf::loadView('supervisor.attendance.rekap-absensi-print-pdf', [
            'company' => $company,
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

        $pdf->setPaper('A4', 'landscape');

        $periodLabel = Carbon::parse($endDate)->translatedFormat('F_Y');
        $filename = 'Rekap_Absensi_' . $periodLabel . '.pdf';

        return $pdf->download($filename);
    }

    protected function getStatusText($log): string
    {
        if (! $log) {
            return '';
        }

        if ($log->sakit_duration > 0) {
            return 'S';
        }

        if ($log->izin_duration > 0) {
            return 'I';
        }

        return match ($log->status) {
            'present' => 'H',
            'leave' => 'C',
            'absent' => '-',
            'holiday' => 'L',
            'off' => 'O',
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
