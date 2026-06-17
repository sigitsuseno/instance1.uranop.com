<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\EmployeePph;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Reports\Exports\PphReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PphReportController extends Controller
{
    /**
     * GET /api/v1/reports/pph
     *
     * Matrix laporan PPh 21: karyawan × 12 bulan.
     * Klik karyawan → detail breakdown per bulan + PPh FINAL Desember.
     *
     * Query param `export=1` → download Excel.
     */
    public function index(Request $request): JsonResponse
    {
        $year = (int) ($request->input('year') ?? date('Y'));
        $search = $request->input('search');
        $employeeId = $request->input('employee_id');
        $groups = $request->input('groups')
            ? array_filter(explode(',', $request->input('groups')))
            : [];

        // Month names
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
            9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        // Export mode
        if ($request->input('export')) {
            return $this->exportExcel($year, $search, $groups, $monthNames);
        }

        // Years available
        $availableYears = PayPeriod::select('period_year')
            ->distinct()
            ->orderBy('period_year', 'desc')
            ->pluck('period_year');

        // Cek apakah ada data employee_pph
        $hasEpph = EmployeePph::whereHas('payPeriod', fn($q) => $q->where('period_year', $year))->exists();

        if ($hasEpph) {
            return $this->fromEmployeePph($year, $search, $employeeId, $groups, $monthNames, $availableYears);
        }

        // Fallback ke pay_records
        return $this->fromPayRecords($year, $search, $employeeId, $groups, $monthNames, $availableYears);
    }

    /**
     * Data dari tabel employee_pph (lengkap dengan breakdown).
     */
    private function fromEmployeePph(int $year, ?string $search, ?string $employeeId, array $groups, array $monthNames, $availableYears): JsonResponse
    {
        $rowsQuery = EmployeePph::with(['employee', 'employee.groups', 'payPeriod'])
            ->whereHas('payPeriod', fn($q) => $q->where('period_year', $year));

        if (!empty($groups)) {
            $rowsQuery->whereHas('employee.groups', fn($q) => $q->whereIn('reference_code', $groups));
        }

        if ($search) {
            $rowsQuery->whereHas('employee', fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
            );
        }

        $allRecords = $rowsQuery->get();
        $grouped = $allRecords->groupBy('employee_id');

        $rows = $grouped->map(function ($records, $empId) {
            $employee = $records->first()->employee;
            $monthly = [];

            foreach (range(1, 12) as $m) {
                $rec = $records->first(fn($r) => $r->payPeriod?->period_month === $m);
                $monthly[$m] = $rec ? [
                    'has_data'   => true,
                    'report'     => (float) $rec->pph_deducted,
                    'is_dtp'     => (bool) $rec->is_dtp,
                    'pph_amount' => (float) $rec->pph_amount,
                ] : ['has_data' => false, 'report' => 0, 'is_dtp' => false, 'pph_amount' => 0];
            }

            return [
                'employee_id'      => $empId,
                'employee_name'    => $employee->name ?? '-',
                'employee_code'    => $employee->employee_code ?? '-',
                'ptkp_status'      => $records->first()->ptkp_status ?? '-',
                'has_npwp'         => (bool) ($records->first()->has_npwp ?? false),
                'nik'              => $records->first()->npwp ?? null,
                'monthly_pph'      => $monthly,
                'total_pph_report' => collect($monthly)->sum('report'),
            ];
        })->values();

        $stats = [
            'total_karyawan'    => $rows->count(),
            'total_pph_payroll' => $rows->sum('total_pph_report'),
            'total_pph_report'  => $allRecords->sum('pph_amount'),
        ];

        return response()->json([
            'rows'           => $rows,
            'stats'          => $stats,
            'monthNames'     => array_values($monthNames),
            'availableYears' => $availableYears,
            'filters'        => ['year' => $year, 'search' => $search, 'employee_id' => $employeeId ? (int) $employeeId : null],
        ]);
    }

    /**
     * Fallback dari pay_records.pph (data minimal — cuma nominal PPh).
     */
    private function fromPayRecords(int $year, ?string $search, ?string $employeeId, array $groups, array $monthNames, $availableYears): JsonResponse
    {
        $query = PayRecord::with(['employee', 'employee.groups', 'payPeriod'])
            ->whereHas('payPeriod', fn($q) => $q->where('period_year', $year));

        if (!empty($groups)) {
            $query->whereHas('employee.groups', fn($q) => $q->whereIn('reference_code', $groups));
        }

        if ($search) {
            $query->whereHas('employee', fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
            );
        }

        $allRecords = $query->get();
        $grouped = $allRecords->groupBy('employee_id');

        $rows = $grouped->map(function ($records, $empId) use ($year) {
            $employee = $records->first()->employee;
            $monthly = [];

            foreach (range(1, 12) as $m) {
                $rec = $records->first(fn($r) => $r->payPeriod?->period_month === $m);
                $hasData = $rec !== null;
                $pphVal = $hasData ? (float) $rec->pph : 0;
                $monthly[$m] = [
                    'has_data'   => $hasData,
                    'report'     => $pphVal,
                    'is_dtp'     => false,
                    'pph_amount' => $pphVal,
                ];
            }

            return [
                'employee_id'      => $empId,
                'employee_name'    => $employee->name ?? '-',
                'employee_code'    => $employee->employee_code ?? '-',
                'ptkp_status'      => '-',
                'has_npwp'         => false,
                'nik'              => null,
                'monthly_pph'      => $monthly,
                'total_pph_report' => collect($monthly)->sum('report'),
            ];
        })->values();

        $stats = [
            'total_karyawan'    => $rows->count(),
            'total_pph_payroll' => $rows->sum('total_pph_report'),
            'total_pph_report'  => $rows->sum('total_pph_report'),
        ];

        $detailEmployee = null;
        $monthlyDetail = [];
        $decemberBreakdown = null;

        if ($employeeId && $grouped->has($employeeId)) {
            $empRecords = $grouped->get($employeeId);
            $emp = $empRecords->first();

            $detailEmployee = [
                'employee_id'   => (int) $employeeId,
                'employee_name' => $emp->employee->name ?? '-',
                'employee_code' => $emp->employee->employee_code ?? '-',
                'ptkp_status'   => '-',
                'has_npwp'      => false,
                'nik'           => null,
            ];

            foreach (range(1, 12) as $m) {
                $rec = $empRecords->first(fn($r) => $r->payPeriod?->period_month === $m);
                $hasData = $rec !== null;
                $monthlyDetail[$m] = $hasData ? [
                    'has_data'            => true,
                    'is_dtp'              => false,
                    'gaji_pokok'          => (float) $rec->gaji_pokok,
                    'tunjangan'           => (float) $rec->tunjangan,
                    'lembur_bonus_thr'    => 0,
                    'jkk'                 => 0,
                    'jkm'                 => 0,
                    'bpjs_kes_perusahaan' => 0,
                    'gross_income'        => (float) $rec->gaji_kotor,
                    'pph_rate'            => 0,
                    'pph_report'          => (float) $rec->pph,
                    'jht_karyawan'        => (float) $rec->bpjs_tk,
                    'jp_karyawan'         => (float) $rec->bpjs_pen,
                    'bpjs_kes_karyawan'   => (float) $rec->bpjs_ks,
                    'biaya_jabatan'       => 0,
                    'net_salary'          => (float) $rec->gaji_bersih,
                    'total_pengurang'     => (float) ($rec->bpjs_tk + $rec->bpjs_pen + $rec->bpjs_ks),
                    'pkp'                 => 0,
                ] : ['has_data' => false];
            }

            $monthsWithData = $empRecords->filter(fn($r) => $r !== null)->count();
            $decemberBreakdown = [
                'is_complete'        => $monthsWithData >= 11,
                'months_with_data'   => $monthsWithData,
                'gaji_pokok_setahun' => $empRecords->sum('gaji_pokok'),
                'tunjangan_setahun'  => $empRecords->sum('tunjangan'),
                'lembur_bonus_thr'   => 0,
                'bruto_setahun'      => $empRecords->sum('gaji_kotor'),
                'biaya_jabatan'      => 0,
                'jht_tahunan'        => $empRecords->sum('bpjs_tk'),
                'jp_tahunan'         => $empRecords->sum('bpjs_pen'),
                'netto_setahun'      => $empRecords->sum('gaji_bersih'),
                'ptkp_value'         => 54000000,
                'pkp'                => max(0, $empRecords->sum('gaji_bersih') - 54000000),
                'pph_setahun'        => $empRecords->sum('pph'),
                'pph_jan_nov'        => $empRecords->filter(fn($r) => in_array($r->payPeriod?->period_month, range(1, 11)))->sum('pph'),
                'pph_desember'       => $empRecords->filter(fn($r) => $r->payPeriod?->period_month === 12)->sum('pph'),
            ];
        }

        return response()->json([
            'rows'               => $rows,
            'stats'              => $stats,
            'monthNames'         => array_values($monthNames),
            'availableYears'     => $availableYears,
            'filters'            => ['year' => $year, 'search' => $search, 'employee_id' => $employeeId ? (int) $employeeId : null],
            'detailEmployee'     => $detailEmployee,
            'monthlyDetail'      => $monthlyDetail,
            'december_breakdown' => $decemberBreakdown,
        ]);
    }

    /**
     * Export Excel: matrix PPh per karyawan × 12 bulan.
     */
    private function exportExcel(int $year, ?string $search, array $groups, array $monthNames)
    {
        $hasEpph = EmployeePph::whereHas('payPeriod', fn($q) => $q->where('period_year', $year))->exists();

        if ($hasEpph) {
            $query = EmployeePph::with(['employee', 'payPeriod'])
                ->whereHas('payPeriod', fn($q) => $q->where('period_year', $year));
        } else {
            $query = PayRecord::with(['employee', 'payPeriod'])
                ->whereHas('payPeriod', fn($q) => $q->where('period_year', $year));
        }

        if (!empty($groups)) {
            $query->whereHas('employee.groups', fn($q) => $q->whereIn('reference_code', $groups));
        }
        if ($search) {
            $query->whereHas('employee', fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
            );
        }

        $allRecords = $query->get();
        $grouped = $allRecords->groupBy('employee_id');

        $rows = $grouped->map(function ($records) use ($hasEpph) {
            $employee = $records->first()->employee;
            $monthly = [];
            foreach (range(1, 12) as $m) {
                $rec = $records->first(fn($r) => $r->payPeriod?->period_month === $m);
                $hasData = $rec !== null;
                $val = $hasData
                    ? ($hasEpph ? (float) $rec->pph_deducted : (float) $rec->pph)
                    : 0;
                $isDtp = $hasEpph && $rec ? (bool) $rec->is_dtp : false;
                $monthly[$m] = [
                    'has_data' => $hasData,
                    'report'   => $val,
                    'is_dtp'   => $isDtp,
                ];
            }
            return [
                'employee_name'    => $employee->name ?? '-',
                'employee_code'    => $employee->employee_code ?? '-',
                'ptkp_status'      => $hasEpph ? ($records->first()->ptkp_status ?? '-') : '-',
                'monthly_pph'      => $monthly,
                'total_pph_report' => collect($monthly)->sum('report'),
            ];
        })->values()->toArray();

        $filename = "Laporan_PPh21_{$year}.xlsx";

        return Excel::download(
            new PphReportExport($rows, array_values($monthNames), $year),
            $filename
        );
    }
}
