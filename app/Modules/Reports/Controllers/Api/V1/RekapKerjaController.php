<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Models\ExtraEmployee;
use App\Modules\Reports\Exports\RekapKerjaExport;
use App\Modules\Reports\Exports\RekapKerjaCombinedExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RekapKerjaController extends Controller
{
    /**
     * GET /api/v1/reports/rekap-kerja
     *
     * 3 section: A. ALL IN  |  B. BULANAN PRINT  |  C. UANG MAKAN
     * Data di-grouping berdasarkan POSITION / JABATAN (BAGIAN).
     */
    public function index(Request $request)
    {
        $periodId = $request->input('period_id');
        $groupCodes = $request->input('groups', '');
        $printGroupCodes = $request->input('print_groups', '');

        if (!$periodId) {
            return response()->json(['data' => [], 'message' => 'Period ID required'], 422);
        }

        $period = PayPeriod::find($periodId);
        if (!$period) {
            return response()->json(['data' => [], 'message' => 'Period not found'], 404);
        }

        $startDate = $period->start_date->format('Y-m-d');
        $endDate   = $period->end_date->format('Y-m-d');

        $selectedGroups = [];
        if ($groupCodes) {
            $selectedGroups = array_filter(array_map('trim', explode(',', $groupCodes)));
        }

        // Section A: ALL IN — semua group yang dipilih + extra employees
        $extraIds = $this->getExtraEmployeeIds();
        $allInData = $this->buildSection($startDate, $endDate, $periodId, $selectedGroups, $extraIds);

        // Section B: BULANAN PRINT — tanpa extra employees
        $printGroups = [];
        if ($printGroupCodes) {
            $printGroups = array_filter(array_map('trim', explode(',', $printGroupCodes)));
        } else {
            $printGroups = $this->getPrintGroups($selectedGroups);
        }
        $printData = $this->buildSection($startDate, $endDate, $periodId, $printGroups, []);

        // Section C: UANG MAKAN — dikelompokkan per BAGIAN (position)
        $uangMakanGroups = [];
        $uangMakanParam = $request->input('uang_makan_groups', '');
        if ($uangMakanParam) {
            $uangMakanGroups = array_filter(array_map('trim', explode(',', $uangMakanParam)));
        }
        // Fallback: kalau belum diset, pakai selectedGroups (ALL IN)
        if (empty($uangMakanGroups)) {
            $uangMakanGroups = $selectedGroups;
        }
        $uangMakanData = $this->buildUangMakanSection($period, $uangMakanGroups);

        return response()->json([
            'sections' => [
                'all_in'        => $allInData,
                'bulanan_print' => $printData,
                'uang_makan'    => $uangMakanData,
            ],
            'period_name' => $period->name,
            'date_start'  => $startDate,
            'date_end'    => $endDate,
        ]);
    }

    /**
     * GET /api/v1/reports/rekap-kerja/export
     *
     * Export Excel — 3 sheet: ALL IN, BULANAN PRINT, UANG MAKAN.
     */
    public function export(Request $request)
    {
        $periodId = $request->input('period_id');
        $groupCodes = $request->input('groups', '');
        $printGroupCodes = $request->input('print_groups', '');

        if (!$periodId) {
            return response()->json(['message' => 'Period ID required'], 422);
        }

        $period = PayPeriod::find($periodId);
        if (!$period) {
            return response()->json(['message' => 'Period not found'], 404);
        }

        $startDate = $period->start_date->format('Y-m-d');
        $endDate   = $period->end_date->format('Y-m-d');

        $selectedGroups = [];
        if ($groupCodes) {
            $selectedGroups = array_filter(array_map('trim', explode(',', $groupCodes)));
        }

        // Build all 3 sections — reuse the same private methods
        $extraIds    = $this->getExtraEmployeeIds();
        $allInData   = $this->buildSection($startDate, $endDate, $periodId, $selectedGroups, $extraIds);

        $printGroups = [];
        if ($printGroupCodes) {
            $printGroups = array_filter(array_map('trim', explode(',', $printGroupCodes)));
        } else {
            $printGroups = $this->getPrintGroups($selectedGroups);
        }
        $printData    = $this->buildSection($startDate, $endDate, $periodId, $printGroups, []);

        $uangMakanGroupsExport = [];
        $uangMakanParamExport = $request->input('uang_makan_groups', '');
        if ($uangMakanParamExport) {
            $uangMakanGroupsExport = array_filter(array_map('trim', explode(',', $uangMakanParamExport)));
        }
        if (empty($uangMakanGroupsExport)) {
            $uangMakanGroupsExport = $selectedGroups;
        }
        $uangMakanData = $this->buildUangMakanSection($period, $uangMakanGroupsExport);

        $safePeriod = preg_replace('/[^a-zA-Z0-9\s]/', '', $period->name);
        $safePeriod = str_replace(' ', '_', trim($safePeriod));
        $filename   = "Rekap_Kerja_{$safePeriod}.xlsx";

        return Excel::download(
            new RekapKerjaExport($allInData, $printData, $uangMakanData, $period->name, $startDate, $endDate),
            $filename
        );
    }

    /**
     * GET /api/v1/reports/rekap-kerja/export-combined
     *
     * Export 1 sheet gabungan: Section A → B → C (vertikal).
     */
    public function exportCombined(Request $request)
    {
        $periodId = $request->input('period_id');
        $groupCodes = $request->input('groups', '');
        $printGroupCodes = $request->input('print_groups', '');
        $uangMakanParam = $request->input('uang_makan_groups', '');

        if (!$periodId) {
            return response()->json(['message' => 'Period ID required'], 422);
        }

        $period = PayPeriod::find($periodId);
        if (!$period) {
            return response()->json(['message' => 'Period not found'], 404);
        }

        $startDate = $period->start_date->format('Y-m-d');
        $endDate   = $period->end_date->format('Y-m-d');

        $selectedGroups = [];
        if ($groupCodes) {
            $selectedGroups = array_filter(array_map('trim', explode(',', $groupCodes)));
        }

        // Build all 3 sections
        $extraIds    = $this->getExtraEmployeeIds();
        $allInData   = $this->buildSection($startDate, $endDate, $periodId, $selectedGroups, $extraIds);

        $printGroups = [];
        if ($printGroupCodes) {
            $printGroups = array_filter(array_map('trim', explode(',', $printGroupCodes)));
        } else {
            $printGroups = $this->getPrintGroups($selectedGroups);
        }
        $printData    = $this->buildSection($startDate, $endDate, $periodId, $printGroups, []);

        $uangMakanGroups = [];
        if ($uangMakanParam) {
            $uangMakanGroups = array_filter(array_map('trim', explode(',', $uangMakanParam)));
        }
        if (empty($uangMakanGroups)) {
            $uangMakanGroups = $selectedGroups;
        }
        $uangMakanData = $this->buildUangMakanSection($period, $uangMakanGroups);

        $safePeriod = preg_replace('/[^a-zA-Z0-9\s]/', '', $period->name);
        $safePeriod = str_replace(' ', '_', trim($safePeriod));
        $filename   = "Rekap_Kerja_Gabungan_{$safePeriod}.xlsx";

        return Excel::download(
            new RekapKerjaCombinedExport($allInData, $printData, $uangMakanData, $period->name, $startDate, $endDate),
            $filename
        );
    }

    /**
     * GET /api/v1/reports/rekap-kerja/groups
     */
    public function groups()
    {
        $groups = EmployeeGroupMaster::where('group_label', 'Imported Shift/Group')
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn($g) => [
                'code' => $g->code,
                'name' => $g->name,
            ]);

        return response()->json(['data' => $groups]);
    }

    // ─── Private: Section A & B ──────────────────────────

    /**
     * Build section data — grouped by POSITION (BAGIAN).
     * @param array $extraIds  ID extra employees yang dipilih (hanya untuk all_in).
     */
    private function buildSection(string $startDate, string $endDate, int $periodId, array $groupCodes, array $extraIds = []): array
    {
        $rosteredEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct('employee_id')
            ->pluck('employee_id');

        if ($rosteredEmployeeIds->isEmpty()) {
            return [];
        }

        $query = Employee::whereIn('id', $rosteredEmployeeIds)
            ->where('is_active', true)
            ->with(['position', 'groups', 'groups.master']);

        if (!empty($groupCodes)) {
            $query->whereHas('groups', function ($q) use ($groupCodes) {
                $q->whereIn('reference_code', $groupCodes);
            });
        }

        $employees = $query->orderBy('name')->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $payRecords = PayRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        $bpjsData = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // ─── Group by POSITION (BAGIAN) ───
        $grouped = [];

        foreach ($employees as $emp) {
            $bagian = $emp->position?->name ?: 'TANPA BAGIAN';

            if (!isset($grouped[$bagian])) {
                $grouped[$bagian] = [];
            }
            $grouped[$bagian][] = $emp;
        }

        $result = [];

        foreach ($grouped as $bagian => $emps) {
            $jml = count($emps);
            $gaji = 0;
            $lembur = 0;
            $gajiKotorTotal = 0;
            $bpjsTk = 0;
            $bpjsKs = 0;

            foreach ($emps as $emp) {
                $pr = $payRecords->get($emp->id);
                $gajiKotor   = $pr ? (float) $pr->gaji_kotor : 0;
                $overtimePay = $pr ? (float) ($pr->upah_lembur ?? 0) : 0;

                $bpjs = $bpjsData->get($emp->id);
                if ($bpjs) {
                    // 3 komponen potongan karyawan: JHT + JP (TK) + Kesehatan (KS)
                    $bpjsTk += (float)($bpjs->employee_jht ?? 0) + (float)($bpjs->employee_jp ?? 0);
                    $bpjsKs += (float)($bpjs->employee_kesehatan ?? 0);
                }

                // GAJI = gaji_kotor - lembur
                $gaji += ($gajiKotor - $overtimePay);
                $lembur += $overtimePay;
                $gajiKotorTotal += $gajiKotor;
            }

            // TOTAL = gaji_kotor (tanpa ditambah lembur)
            $total = $gajiKotorTotal;
            $bpjsTotal = $bpjsTk + $bpjsKs;

            $result[] = [
                'bagian'   => $bagian,
                'jml'      => $jml,
                'gaji'     => round($gaji, 2),
                'lembur'   => round($lembur, 2),
                'total'    => round($total, 2),
                'bpjs'     => round($bpjsTotal, 2),
                'bpjs_tk'  => round($bpjsTk, 2),
                'bpjs_ks'  => round($bpjsKs, 2),
            ];
        }

        usort($result, fn($a, $b) => strcasecmp($a['bagian'], $b['bagian']));

        // ─── Extra Employees (hanya jika ada extraIds yang dipilih) ───
        if (!empty($extraIds)) {
            $extraEmployees = ExtraEmployee::whereIn('id', $extraIds)->orderBy('nama')->get();
            if ($extraEmployees->isNotEmpty()) {
                $jml = $extraEmployees->count();
                $gajiExtra = 0;
                $lemburExtra = 0;
                $bpjsExtra = 0;

                foreach ($extraEmployees as $extra) {
                    $komponen = $extra->komponen_gaji ?? [];
                    $gajiExtra += (float)($komponen['gaji_pokok'] ?? 0);
                    $lemburExtra += (float)($komponen['upah_lembur'] ?? 0);
                    $bpjsExtra += (float)($komponen['ttl_bpjs'] ?? 0);
                }

                $result[] = [
                    'bagian'   => 'EXTRA',
                    'jml'      => $jml,
                    'gaji'     => round($gajiExtra, 2),
                    'lembur'   => round($lemburExtra, 2),
                    'total'    => round($gajiExtra + $lemburExtra, 2),
                    'bpjs'     => round($bpjsExtra, 2),
                    'bpjs_tk'  => round($bpjsExtra, 2),
                    'bpjs_ks'  => 0,
                ];
            }
        }

        return $result;
    }

    // ─── Private: Section C (Uang Makan) ─────────────────

    /**
     * Build Uang Makan section — grouped by POSITION (BAGIAN).
     * Memanggil internal UangMakanReportController::rekab(),
     * lalu map employee_id → position untuk grouping.
     */
    private function buildUangMakanSection(PayPeriod $period, array $uangMakanGroups): array
    {
        try {
            $startDate = $period->start_date->format('Y-m-d');
            $endDate   = $period->end_date->format('Y-m-d');

            // ─── Preload employee_id → position map ───
            $rosteredIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
                ->distinct('employee_id')
                ->pluck('employee_id');

            $empQuery = Employee::whereIn('id', $rosteredIds)
                ->where('is_active', true)
                ->with(['position']);

            if (!empty($uangMakanGroups)) {
                $empQuery->whereHas('groups', function ($q) use ($uangMakanGroups) {
                    $q->whereIn('reference_code', $uangMakanGroups);
                });
            }

            $positionMap = $empQuery->get()
                ->pluck('position.name', 'id')
                ->map(fn($name) => $name ?: 'TANPA BAGIAN')
                ->toArray();

            // ─── Panggil internal rekab ───
            // Teruskan uang_makan_groups agar employee_overtime difilter di sumbernya.
            $uangMakanCtrl = app(UangMakanReportController::class);
            $rekabParams = ['period_id' => $period->id];
            if (!empty($uangMakanGroups)) {
                $rekabParams['groups'] = $uangMakanGroups;
            }
            $rekabRequest  = Request::create(
                '/api/v1/reports/uang-makan/rekab',
                'GET',
                $rekabParams
            );
            $rekabResponse = $uangMakanCtrl->rekab($rekabRequest);
            $rekabData     = json_decode($rekabResponse->getContent(), true);
            $umItems       = $rekabData['data'] ?? [];

            // ─── Group by POSITION ───
            $grouped = [];

            foreach ($umItems as $item) {
                $employeeId = $item['id'] ?? null;
                $bagian = $positionMap[$employeeId] ?? ($item['group_name'] ?? 'TANPA BAGIAN');

                if (!isset($grouped[$bagian])) {
                    $grouped[$bagian] = [
                        'jml'          => 0,
                        'uang_makan'   => 0,
                        'lembur_sabtu' => 0,
                        'lembur_minggu'=> 0,
                        'insentif'     => 0,
                        'total'        => 0,
                    ];
                }

                $nominals = $item['nominals'] ?? [];
                $grouped[$bagian]['jml']++;
                $grouped[$bagian]['uang_makan']   += (float)($nominals['uang_makan'] ?? 0);
                $grouped[$bagian]['lembur_sabtu'] += (float)($nominals['lembur_sabtu'] ?? 0);
                $grouped[$bagian]['lembur_minggu']+= (float)($nominals['lembur_minggu'] ?? 0);
                $grouped[$bagian]['insentif']     += (float)($nominals['insentif'] ?? 0);
                $grouped[$bagian]['total']        += (float)($item['total'] ?? 0);
            }

            $result = [];
            foreach ($grouped as $bagian => $data) {
                $result[] = array_merge(['bagian' => $bagian], $data);
            }

            usort($result, fn($a, $b) => strcasecmp($a['bagian'], $b['bagian']));

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─── Helpers ──────────────────────────────────────────

    /**
     * Ambil ID extra employees yang dipilih dari config.
     */
    private function getExtraEmployeeIds(): array
    {
        try {
            $configService = app(\App\Modules\Settings\Services\ReportConfigService::class);
            $config = $configService->getConfig('rekap-kerja');
            return $config['extra_employee_ids'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * GET /api/v1/reports/rekap-kerja/extra-employees
     * List semua extra employees untuk checklist di settings modal.
     */
    public function extraEmployees()
    {
        $extras = ExtraEmployee::orderBy('nama')->get()
            ->map(fn($e) => [
                'id'   => $e->id,
                'nama' => $e->nama,
            ]);

        return response()->json(['data' => $extras]);
    }

    /**
     * Get print-related groups from selected groups.
     * Default: dari config.print_groups, atau auto-detect "PRINT".
     */
    private function getPrintGroups(array $selectedGroups): array
    {
        if (empty($selectedGroups)) {
            return [];
        }

        try {
            $configService = app(\App\Modules\Settings\Services\ReportConfigService::class);
            $config = $configService->getConfig('rekap-kerja');
            if (!empty($config['print_groups'])) {
                return array_intersect($selectedGroups, $config['print_groups']);
            }
        } catch (\Throwable $e) {
            // fallback
        }

        $printGroups = EmployeeGroupMaster::where('group_label', 'Imported Shift/Group')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('name', 'like', '%PRINT%')
                  ->orWhere('code', 'like', '%PRINT%');
            })
            ->pluck('code')
            ->toArray();

        return array_intersect($selectedGroups, $printGroups);
    }
}
