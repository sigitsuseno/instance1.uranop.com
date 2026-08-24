<?php

namespace App\Modules\Supervisor\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorDashboardController extends Controller
{
    /**
     * Get supervisor dashboard data with audit logs filtered to hrbranch.
     */
    public function index(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        // ── Stats ─────────────────────────────────────────────────────
        $totalKaryawan = Employee::where('is_active', 1)->count();

        $hadirHariIni = AttendancePrepare::where('date', $today)
            ->whereNotNull('check_in')
            ->count();

        $menungguCuti = LeaveRequest::where('status', 'pending')->count();

        // Total payroll bulan ini: sum gaji_kotor dari pay_records bulan aktif
        $totalPayroll = 0;
        $activePeriod = PayPeriod::where('status', 'active')->first();
        if ($activePeriod) {
            $totalPayroll = (int) PayRecord::where('pay_period_id', $activePeriod->id)
                ->sum('gaji_kotor');
        }

        $stats = [
            'totalKaryawan'  => $totalKaryawan,
            'hadirHariIni'   => $hadirHariIni,
            'menungguCuti'   => $menungguCuti,
            'totalPayroll'   => $this->formatRupiah($totalPayroll),
        ];

        // ── Pending Leave Requests ────────────────────────────────────
        $pendingLeaves = LeaveRequest::with(['employee:id,name,department_id', 'employee.department:id,name', 'leaveType:id,name'])
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($lr) => [
                'id'            => $lr->id,
                'employee_name' => $lr->employee?->name ?? '-',
                'department'    => $lr->employee?->department?->name ?? '-',
                'leave_type'    => $lr->leaveType?->name ?? '-',
                'start_date'    => $lr->start_date?->format('d M Y'),
                'end_date'      => $lr->end_date?->format('d M Y'),
                'days'          => $lr->days_requested,
                'status'        => $lr->status,
                'created_at'    => $lr->created_at?->format('d M Y H:i'),
            ]);

        // ── Contracts Expiring Soon (30 hari) ─────────────────────────
        $contractsExpiring = EmployeeContract::with('employee:id,name,department_id', 'employee.department:id,name')
            ->active()
            ->latestOnly()
            ->expiringSoon(30)
            ->latest('end_date')
            ->limit(10)
            ->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'employee_name' => $c->employee?->name ?? '-',
                'department'    => $c->employee?->department?->name ?? '-',
                'contract_type' => $c->contract_type_label,
                'end_date'      => $c->end_date?->format('d M Y'),
                'days_left'     => $c->days_left,
            ]);

        // ── Recent Audit Logs (Filtered for hrbranch role) ────────────
        $recentAuditLogs = AuditLog::with('user:id,name')
            ->whereHas('user', function ($q) {
                $q->where('user_type', 'hrbranch');
            })
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'user_name'  => $log->user?->name ?? 'System',
                'action'     => $log->action,
                'module'     => $log->module,
                'model_type' => $log->model_type ? class_basename($log->model_type) : null,
                'created_at' => $log->created_at?->format('d M Y H:i'),
            ]);

        // ── Birthdays This Month ──────────────────────────────────────
        $currentMonth = now()->month;
        $birthdays = Employee::where('is_active', 1)
            ->whereMonth('date_of_birth', $currentMonth)
            ->select('id', 'name', 'date_of_birth', 'department_id')
            ->with('department:id,name')
            ->orderByRaw('DAY(date_of_birth)')
            ->get()
            ->map(fn ($e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'department' => $e->department?->name ?? '-',
                'dob'        => $e->date_of_birth?->format('d M'),
            ]);

        return response()->json([
            'stats'             => $stats,
            'pendingLeaves'     => $pendingLeaves,
            'contractsExpiring' => $contractsExpiring,
            'recentAuditLogs'   => $recentAuditLogs,
            'birthdays'         => $birthdays,
        ]);
    }

    /**
     * Statistik ringkas dashboard supervisor (kehadiran + komposisi gaji per jabatan).
     * GET /api/v1/supervisor/dashboard/statistik?kehadiran_month=YYYY-MM&payroll_month=YYYY-MM
     */
    public function statistik(Request $request): JsonResponse
    {
        // ── KEHADIRAN (attendance_autologs) ─────────────────────────
        $kehadiranMonths = SupervisorAttendance::query()
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as ym, COUNT(*) as jml')
            ->groupBy('ym')
            ->orderByDesc('ym')
            ->get()
            ->map(fn ($r) => $this->monthOption($r->ym, (int) $r->jml));

        $khMonth = $request->query('kehadiran_month', $kehadiranMonths->first()['ym'] ?? now()->format('Y-m'));
        [$khYear, $khMonthNum] = array_pad(explode('-', $khMonth), 2, null);
        $khYear = (int) $khYear;
        $khMonthNum = max(1, min(12, (int) $khMonthNum));

        $khStart = Carbon::create($khYear, $khMonthNum, 1)->toDateString();
        $khEnd = Carbon::create($khYear, $khMonthNum, 1)->endOfMonth()->toDateString();
        $khBase = SupervisorAttendance::whereBetween('date', [$khStart, $khEnd]);

        $khCounts = (clone $khBase)
            ->selectRaw('status, COUNT(*) as jml')
            ->groupBy('status')
            ->pluck('jml', 'status')
            ->map(fn ($v) => (int) $v);

        $statusMeta = [
            'present'  => ['label' => 'Hadir',  'color' => '#10b981'],
            'absent'   => ['label' => 'Absen',  'color' => '#ef4444'],
            'leave'    => ['label' => 'Cuti',   'color' => '#3b82f6'],
            'sakit'    => ['label' => 'Sakit',  'color' => '#eab308'],
            'izin'     => ['label' => 'Izin',   'color' => '#f97316'],
            'off'      => ['label' => 'Off',    'color' => '#64748b'],
            'holiday'  => ['label' => 'Libur',  'color' => '#9ca3af'],
            'pending'  => ['label' => 'Pending','color' => '#a855f7'],
        ];

        $summary = ['total' => $khCounts->sum()];
        $chart = [];
        foreach ($statusMeta as $key => $meta) {
            $val = $khCounts->get($key, 0);
            $summary[$key] = $val;
            if ($val > 0) {
                $chart[] = ['label' => $meta['label'], 'value' => $val, 'color' => $meta['color']];
            }
        }

        // trend harian: hadir vs total (bukan hadir) per tanggal
        $khDaily = (clone $khBase)
            ->selectRaw('date, COUNT(*) as total, SUM(CASE WHEN status="present" THEN 1 ELSE 0 END) as present')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dailyTrend = [];
        $daysInMonth = Carbon::create($khYear, $khMonthNum, 1)->daysInMonth;
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dt = Carbon::create($khYear, $khMonthNum, $d)->toDateString();
            $row = $khDaily->get($dt);
            $dailyTrend[] = [
                'date'    => $dt,
                'label'   => sprintf('%02d', $d),
                'total'   => $row ? (int) $row->total : 0,
                'present' => $row ? (int) $row->present : 0,
            ];
        }

        // ── PAYROLL (supervisor_breakdowns) ─────────────────────────
        $payrollMonths = SupervisorBreakdown::query()
            ->join('pay_periods', 'pay_periods.id', '=', 'supervisor_breakdowns.pay_period_id')
            ->whereNotNull('pay_periods.period_year')
            ->whereNotNull('pay_periods.period_month')
            ->selectRaw('pay_periods.period_year as y, pay_periods.period_month as m, COUNT(*) as jml')
            ->groupBy('pay_periods.period_year', 'pay_periods.period_month')
            ->orderByRaw('pay_periods.period_year DESC, pay_periods.period_month DESC')
            ->get()
            ->map(fn ($r) => $this->monthOption(sprintf('%04d-%02d', $r->y, $r->m), (int) $r->jml));

        $payMonth = $request->query('payroll_month', $payrollMonths->first()['ym'] ?? now()->format('Y-m'));
        [$payYear, $payMonthNum] = array_pad(explode('-', $payMonth), 2, null);
        $payYear = (int) $payYear;
        $payMonthNum = max(1, min(12, (int) $payMonthNum));

        $periodIds = PayPeriod::where('period_year', $payYear)
            ->where('period_month', $payMonthNum)
            ->pluck('id');

        $payData = [];
        $payTotal = 0;
        if (! $periodIds->isEmpty()) {
            $payRows = SupervisorBreakdown::whereIn('pay_period_id', $periodIds)
                ->selectRaw('COALESCE(position_name, "Tanpa Jabatan") as position, COUNT(*) as jml, SUM(gaji_kotor) as gaji')
                ->groupBy('position')
                ->orderByDesc('gaji')
                ->get();
            $payTotal = (float) $payRows->sum('gaji');
            $payData = $payRows
                ->map(fn ($r) => [
                    'position' => $r->position,
                    'gaji' => (float) $r->gaji,
                    'count' => (int) $r->jml,
                ])
                ->values();
        }

        return response()->json([
            'kehadiran' => [
                'month'       => $khMonth,
                'month_label' => Carbon::create($khYear, $khMonthNum, 1)->locale('id')->translatedFormat('F Y'),
                'months'      => $kehadiranMonths,
                'summary'     => $summary,
                'chart'       => $chart,
                'daily_trend' => $dailyTrend,
            ],
            'payroll' => [
                'month'       => $payMonth,
                'month_label' => Carbon::create($payYear, $payMonthNum, 1)->locale('id')->translatedFormat('F Y'),
                'months'      => $payrollMonths,
                'total'       => $payTotal,
                'data'        => $payData,
            ],
        ]);
    }

    private function monthOption(string $ym, int $count): array
    {
        [$y, $m] = array_pad(explode('-', $ym), 2, null);
        return [
            'ym'    => $ym,
            'label' => Carbon::create((int) $y, (int) $m, 1)->locale('id')->translatedFormat('F Y'),
            'count' => $count,
        ];
    }

    private function formatRupiah(int $nominal): string
    {
        if ($nominal >= 1_000_000_000) {
            return 'Rp ' . number_format($nominal / 1_000_000_000, 1, ',', '.') . ' M';
        }
        if ($nominal >= 1_000_000) {
            return 'Rp ' . number_format($nominal / 1_000_000, 0, ',', '.') . ' Jt';
        }
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }
}
