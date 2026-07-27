<?php

namespace App\Modules\Supervisor\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Supervisor\Attendance\Models\SupervisorEmployee as Employee;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use App\Modules\Supervisor\Reports\Exports\AttendanceReportExport;
use App\Modules\Organization\Models\Company;
use App\Modules\Payroll\Models\PayPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportController extends Controller
{
    /**
     * Laporan Kehadiran Supervisor — 2 Section: Allin & Bulanan
     */
    public function index(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $startDate = $period['start'];
        $endDate   = $period['end'];

        $payrollPeriods = PayPeriod::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date', 'end_date']);

        // Build dates array
        $dates = $this->buildDates($startDate, $endDate);

        // Fetch & group data
        $data = $this->fetchGroupedData($startDate, $endDate, $dates);

        $company = Company::first();

        return response()->json([
            'success' => true,
            'data' => [
                'periods' => collect($payrollPeriods)->map(fn($p) => [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'start_date' => $p->start_date->toDateString(),
                    'end_date'   => $p->end_date->toDateString(),
                ]),
                'dates' => $dates,
                'sections' => [
                    [
                        'label' => 'A. Karyawan Allin, Gudang, Sopir',
                        'code'  => 'A',
                        'data'  => $data['sectionA'],
                    ],
                    [
                        'label' => 'B. Karyawan Bulanan',
                        'code'  => 'B',
                        'data'  => $data['sectionB'],
                    ],
                ],
                'company' => $company?->name ?? 'ALL IN KARANGJATI',
                'period' => [
                    'start' => $startDate,
                    'end'   => $endDate,
                ],
            ],
        ]);
    }

    /**
     * Export ke Excel — 2 sheet: ALL IN & BULANAN
     */
    public function export(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $startDate = $period['start'];
        $endDate   = $period['end'];

        $dates = $this->buildDates($startDate, $endDate);
        $data = $this->fetchGroupedData($startDate, $endDate, $dates);
        $company = Company::first();

        $filename = 'Laporan_Absensi_Allin_Bulanan_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new AttendanceReportExport(
                $data['sectionA'],
                $data['sectionB'],
                $startDate,
                $endDate,
                $company?->name ?? 'ALL IN KARANGJATI'
            ),
            $filename
        );
    }

    // ── Private ─────────────────────────────────

    private function resolvePeriod(Request $request): array
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                'start' => Carbon::parse($request->start_date)->toDateString(),
                'end'   => Carbon::parse($request->end_date)->toDateString(),
            ];
        }
        return $this->getDefaultPeriod();
    }

    private function buildDates(string $startDate, string $endDate): array
    {
        $dates = [];
        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = [
                'date'       => $current->toDateString(),
                'day'        => (int) $current->format('d'),
                'day_name'   => $current->translatedFormat('l'),
                'is_weekend' => $current->isSunday(),
            ];
            $current->addDay();
        }
        return $dates;
    }

    private function fetchGroupedData(string $startDate, string $endDate, array $dates): array
    {
        // 1. Ambil karyawan yang di-assign supervisor di periode ini
        $groupedIds = SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->pluck('employee_id')
            ->unique()
            ->toArray();

        if (empty($groupedIds)) {
            return ['sectionA' => [], 'sectionB' => []];
        }

        // 2. Ambil data karyawan + groups + autologs
        $employees = Employee::whereIn('id', $groupedIds)
            ->where('is_active', 1)
            ->with([
                'department:id,name',
                'groups:employee_id,reference_code',
                'autologs' => fn($q) => $q->whereBetween('date', [$startDate, $endDate]),
            ])
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'name', 'department_id']);

        $sectionA = []; // GRP-ALLIN, GRP-GD, GRP-SPR
        $sectionB = []; // GRP-PS1, GRP-SS

        foreach ($employees as $emp) {
            $groupCodes = $emp->groups->pluck('reference_code')->toArray();

            // 3. Filter: buang GRP-JKT
            if (in_array('GRP-JKT', $groupCodes)) {
                continue;
            }

            $logs = $emp->autologs->keyBy(fn($l) => $l->date->toDateString());

            $row = [
                'id'            => $emp->id,
                'employee_code' => $emp->employee_code,
                'name'          => $emp->name,
                'department'    => $emp->department?->name,
                'days'          => [],
            ];

            foreach ($dates as $d) {
                $dateStr = $d['date'];
                $log = $logs->get($dateStr);
                $row['days'][$dateStr] = $this->buildDayData($log, $d);
            }

            // 4. Grouping
            $isSectionA = !empty(array_intersect($groupCodes, ['GRP-ALLIN', 'GRP-GD', 'GRP-SPR']));
            $isSectionB = !empty(array_intersect($groupCodes, ['GRP-PS1', 'GRP-SS']));

            if ($isSectionA) {
                $sectionA[] = $row;
            } elseif ($isSectionB) {
                $sectionB[] = $row;
            }
            // Abaikan jika tidak masuk keduanya (GRP-SPC dll)
        }

        return ['sectionA' => $sectionA, 'sectionB' => $sectionB];
    }

    protected function buildDayData($log, array $d): array
    {
        if (! $log) {
            return [
                'status'       => '-',
                'check_in'     => null,
                'check_out'    => null,
                'lembur'       => 0,
                'lm'           => 0,
                'is_holiday'   => $d['is_weekend'],
                'is_leave'     => false,
            ];
        }

        $isHoliday = $log->is_holiday || $d['is_weekend'];

        return [
            'status'       => $this->getStatusText($log),
            'check_in'     => $log->check_in?->format('H:i'),
            'check_out'    => $log->check_out?->format('H:i'),
            'lembur'       => (int) ($log->lembur ?? 0),
            'lm'           => (int) ($log->lm ?? 0),
            'is_holiday'   => $isHoliday,
            'is_leave'     => (bool) $log->is_leave,
            'holiday_name' => $log->metadata['holiday_name'] ?? null,
        ];
    }

    protected function getStatusText($log): string
    {
        if (! $log) return '-';

        if ((int) $log->sakit_duration > 0) return 'S';
        if ((int) $log->izin_duration > 0) return 'I';

        return match ($log->status) {
            'present' => 'H',
            'leave'   => 'C',
            'absent'  => 'A',
            'holiday' => 'L',
            'off'     => 'Off',
            default   => '-',
        };
    }

    protected function getDefaultPeriod(): array
    {
        $now = Carbon::now();
        if ($now->day >= 25) {
            $start = $now->copy()->day(25);
            $end   = $now->copy()->addMonth()->day(24);
        } else {
            $start = $now->copy()->subMonth()->day(25);
            $end   = $now->copy()->day(24);
        }
        return [
            'start' => $start->toDateString(),
            'end'   => $end->toDateString(),
        ];
    }
}
