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
     *
     * Ketika `employee_id` diberikan, bangun juga detail rincian bulanan
     * dan perhitungan PPh FINAL (Desember) untuk karyawan tersebut.
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
                'total_pph_report' => collect($monthly)->sum(fn($m) => $m['is_dtp'] ? 0 : $m['report']),
            ];
        })->values();

        $stats = [
            'total_karyawan'    => $rows->count(),
            'total_pph_payroll' => $rows->sum('total_pph_report'),
            'total_pph_report'  => $allRecords->sum('pph_amount'),
        ];

        $detailEmployee = null;
        $monthlyDetail = [];
        $decemberBreakdown = null;

        if ($employeeId && $grouped->has($employeeId)) {
            [$detailEmployee, $monthlyDetail, $decemberBreakdown] = $this->buildEmployeePphDetail($grouped->get($employeeId));
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
     * Bangun detail rincian bulanan + perhitungan PPh FINAL (Desember)
     * dari record employee_pph seorang karyawan dalam satu tahun.
     *
     * @param \Illuminate\Support\Collection $records
     */
    private function buildEmployeePphDetail($records): array
    {
        $first = $records->first();
        $employee = $first->employee;

        $detailEmployee = [
            'employee_id'   => (int) $employee->id,
            'employee_name' => $employee->name ?? '-',
            'employee_code' => $employee->employee_code ?? '-',
            'ptkp_status'   => $first->ptkp_status ?? '-',
            'has_npwp'      => (bool) ($first->has_npwp ?? false),
            'nik'           => $first->npwp ?? null,
        ];

        $monthlyDetail = [];
        foreach (range(1, 12) as $m) {
            $rec = $records->first(fn($r) => $r->payPeriod?->period_month === $m);
            if (!$rec) {
                $monthlyDetail[$m] = ['has_data' => false];
                continue;
            }

            $jht = (float) $rec->bpjs_jht_karyawan;
            $jp = (float) $rec->bpjs_jp_karyawan;
            $biayaJabatan = (float) $rec->biaya_jabatan;
            $totalPengurang = (float) $rec->total_pengurang;

            $monthlyDetail[$m] = [
                'has_data'            => true,
                'is_dtp'              => (bool) $rec->is_dtp,
                'gaji_pokok'          => (float) $rec->gaji_pokok,
                'tunjangan'           => (float) $rec->tunjangan,
                'lembur_bonus_thr'    => (float) $rec->lembur_bonus_thr,
                'jkk'                 => (float) $rec->bpjs_jkk_perusahaan,
                'jkm'                 => (float) $rec->bpjs_jkm_perusahaan,
                'bpjs_kes_perusahaan' => (float) $rec->bpjs_kes_perusahaan,
                'gross_income'        => (float) $rec->gross_income,
                'pph_rate'            => (float) $rec->pph_rate,
                'pph_report'          => (float) $rec->pph_deducted,
                'jht_karyawan'        => $jht,
                'jp_karyawan'         => $jp,
                'bpjs_kes_karyawan'   => max(0, $totalPengurang - $biayaJabatan - $jht - $jp),
                'biaya_jabatan'       => $biayaJabatan,
                'net_salary'          => (float) $rec->netto_income,
                'total_pengurang'     => $totalPengurang,
                'pkp'                 => (float) $rec->pkp,
            ];
        }

        $monthsWithData = $records->count();
        $ptkpStatus = $first->ptkp_status;
        $hasNpwp = (bool) ($first->has_npwp ?? false);

        // Komponen tahunan — dipakai langsung dari kolom employee_pph.
        $brutoSetahun = $records->sum('gross_income');
        $biayaJabatanSetahun = min(6000000, $records->sum('biaya_jabatan'));
        $jhtTahunan = $records->sum('bpjs_jht_karyawan');
        $jpTahunan = $records->sum('bpjs_jp_karyawan');
        $nettoSetahun = max(0, $brutoSetahun - $biayaJabatanSetahun - $jhtTahunan - $jpTahunan);

        // PTKP sesuai status karyawan (kini sudah terisi).
        $ptkpValue = $this->getPtkpValue($ptkpStatus);
        $pkp = max(0, $nettoSetahun - $ptkpValue);
        $pphSetahun = $this->calculateProgressivePph($pkp);

        // Non-NPWP: dikenakan 120% (PPh 20) — konsisten dengan PphCalculationService.
        if (!$hasNpwp) {
            $pphSetahun = round($pphSetahun * 1.2, 2);
        }

        $pphJanNov = $records->filter(fn($r) => in_array($r->payPeriod?->period_month, range(1, 11)))->sum('pph_deducted');

        $decemberBreakdown = [
            'is_complete'        => $monthsWithData >= 11,
            'months_with_data'   => $monthsWithData,
            'gaji_pokok_setahun' => $records->sum('gaji_pokok'),
            'tunjangan_setahun'  => $records->sum('tunjangan'),
            'lembur_bonus_thr'   => $records->sum('lembur_bonus_thr'),
            'bruto_setahun'      => $brutoSetahun,
            'biaya_jabatan'      => $biayaJabatanSetahun,
            'jht_tahunan'        => $jhtTahunan,
            'jp_tahunan'         => $jpTahunan,
            'netto_setahun'      => $nettoSetahun,
            'ptkp_value'         => $ptkpValue,
            'pkp'                => $pkp,
            'pph_setahun'        => $pphSetahun,
            'pph_jan_nov'        => $pphJanNov,
            'pph_desember'       => max(0, round($pphSetahun - $pphJanNov, 2)),
        ];

        return [$detailEmployee, $monthlyDetail, $decemberBreakdown];
    }

    /**
     * Ambil nilai PTKP (Rp) dari tabel ptkp_rates berdasarkan status_code.
     * Fallback TK/0 (54 jt) bila status tidak dikenal / belum diisi.
     */
    private function getPtkpValue(?string $status): float
    {
        $value = \App\Modules\Settings\Models\PtkpRate::where('status_code', $status)
            ->where('is_active', true)
            ->value('value');

        return $value !== null ? (float) $value : 54000000;
    }

    /**
     * Hitung PPh 21 setahun dengan tarif progresif (Pasal 17 UU HPP)
     * dari tabel progressive_rates. Fallback ke tarif standar bila kosong.
     */
    private function calculateProgressivePph(float $pkp): float
    {
        if ($pkp <= 0) {
            return 0;
        }

        $rates = \App\Modules\Settings\Models\ProgressiveRate::orderBy('min_income')->get();

        if ($rates->isEmpty()) {
            $rates = collect([
                (object) ['min_income' => 0,          'max_income' => 60000000,   'rate' => 5],
                (object) ['min_income' => 60000001,   'max_income' => 250000000,  'rate' => 15],
                (object) ['min_income' => 250000001,  'max_income' => 500000000,  'rate' => 25],
                (object) ['min_income' => 500000001,  'max_income' => 5000000000, 'rate' => 30],
                (object) ['min_income' => 5000000001, 'max_income' => null,       'rate' => 35],
            ]);
        }

        $tax = 0.0;
        foreach ($rates as $r) {
            $min = (float) $r->min_income;
            $max = $r->max_income !== null ? (float) $r->max_income : INF;
            if ($pkp <= $min) {
                break;
            }
            $taxable = min($pkp, $max) - $min;
            if ($taxable > 0) {
                $tax += $taxable * ((float) $r->rate / 100);
            }
            if ($pkp <= $max) {
                break;
            }
        }

        return round($tax, 2);
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
            $empModel = $emp->employee;
            $ptkpStatus = $empModel->ptkp ?? 'TK/0';
            $hasNpwp = (bool) ($empModel->has_npwp ?? false);

            $detailEmployee = [
                'employee_id'   => (int) $employeeId,
                'employee_name' => $empModel->name ?? '-',
                'employee_code' => $empModel->employee_code ?? '-',
                'ptkp_status'   => $ptkpStatus,
                'has_npwp'      => $hasNpwp,
                'nik'           => $empModel->npwp ?? null,
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

            $monthsWithData = $empRecords->count();

            $brutoSetahun = $empRecords->sum('gaji_kotor');
            $jhtTahunan = $empRecords->sum('bpjs_tk');
            $jpTahunan = $empRecords->sum('bpjs_pen');
            $nettoSetahun = max(0, $empRecords->sum('gaji_bersih'));

            // PTKP diambil dari status karyawan (bukan hardcode).
            $ptkpValue = $this->getPtkpValue($ptkpStatus);
            $pkp = max(0, $nettoSetahun - $ptkpValue);
            $pphSetahun = $this->calculateProgressivePph($pkp);

            if (!$hasNpwp) {
                $pphSetahun = round($pphSetahun * 1.2, 2);
            }

            $pphJanNov = $empRecords->filter(fn($r) => in_array($r->payPeriod?->period_month, range(1, 11)))->sum('pph');

            $decemberBreakdown = [
                'is_complete'        => $monthsWithData >= 11,
                'months_with_data'   => $monthsWithData,
                'gaji_pokok_setahun' => $empRecords->sum('gaji_pokok'),
                'tunjangan_setahun'  => $empRecords->sum('tunjangan'),
                'lembur_bonus_thr'   => 0,
                'bruto_setahun'      => $brutoSetahun,
                'biaya_jabatan'      => 0,
                'jht_tahunan'        => $jhtTahunan,
                'jp_tahunan'         => $jpTahunan,
                'netto_setahun'      => $nettoSetahun,
                'ptkp_value'         => $ptkpValue,
                'pkp'                => $pkp,
                'pph_setahun'        => $pphSetahun,
                'pph_jan_nov'        => $pphJanNov,
                'pph_desember'       => max(0, round($pphSetahun - $pphJanNov, 2)),
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
                'total_pph_report' => collect($monthly)->sum(fn($m) => $m['is_dtp'] ? 0 : $m['report']),
            ];
        })->values()->toArray();

        $filename = "Laporan_PPh21_{$year}.xlsx";

        return Excel::download(
            new PphReportExport($rows, array_values($monthNames), $year),
            $filename
        );
    }
}
