<?php

namespace App\Modules\Dashboard\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardApiController extends Controller
{
    /**
     * Get all dashboard data in a single call.
     */
    public function index(Request $request): JsonResponse
    {
        // ── Stats ─────────────────────────────────────────────────────
        $totalKaryawan = Employee::where('is_active', 1)->count();

        // Cuti Tahunan (CT) yang berakhir pada bulan ini
        $cutiPeriodeIni = LeaveRequest::whereHas('leaveType', fn ($q) => $q->where('code', 'CT'))
            ->whereYear('end_date', now()->year)
            ->whereMonth('end_date', now()->month)
            ->count();

        // Izin (IZN) periode ini: query sama dengan cuti periode ini
        $izinPeriodeIni = LeaveRequest::whereHas('leaveType', fn ($q) => $q->where('code', 'IZN'))
            ->whereYear('end_date', now()->year)
            ->whereMonth('end_date', now()->month)
            ->count();

        // Total payroll bulan ini: sum gaji_kotor dari pay_records bulan aktif
        $totalPayroll = 0;
        $activePeriod = PayPeriod::where('status', 'active')->first();
        if ($activePeriod) {
            $totalPayroll = (int) PayRecord::where('pay_period_id', $activePeriod->id)
                ->sum('gaji_kotor');
        }

        $stats = [
            'totalKaryawan'   => $totalKaryawan,
            'cutiPeriodeIni'  => $cutiPeriodeIni,
            'izinPeriodeIni'  => $izinPeriodeIni,
            'totalPayroll'    => $this->formatRupiah($totalPayroll),
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

        // ── Recent Audit Logs ─────────────────────────────────────────
        $recentAuditLogs = AuditLog::with('user:id,name')
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
     * Get supervisor dashboard data with audit logs filtered to hrbranch.
     */
    public function supervisorIndex(Request $request): JsonResponse
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

        // ── Recent Audit Logs (Filtered for hrbranch user) ────────────
        $recentAuditLogs = AuditLog::with('user:id,name')
            ->whereHas('user', function ($q) {
                $q->where('email', 'hrbranch@uranop.com');
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

    // ── Helpers ───────────────────────────────────────────────────

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
