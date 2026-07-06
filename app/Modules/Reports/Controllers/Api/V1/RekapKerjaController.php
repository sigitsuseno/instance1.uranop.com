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
use Illuminate\Http\Request;
use Carbon\Carbon;

class RekapKerjaController extends Controller
{
    /**
     * GET /api/v1/reports/rekap-kerja
     *
     * Mengambil data rekap kerja dalam 3 section:
     * - A. ALL IN
     * - B. BULANAN PRINT
     * - C. UANG MAKAN
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

        // Parse selected groups
        $selectedGroups = [];
        if ($groupCodes) {
            $selectedGroups = array_filter(array_map('trim', explode(',', $groupCodes)));
        }

        // ─── Build sections ────────────────────────────────

        // Section A: ALL IN — semua group yang dipilih
        $allInData = $this->buildSection($startDate, $endDate, $periodId, $selectedGroups, 'all_in');

        // Section B: BULANAN PRINT — dari query param print_groups, atau dari config
        $printGroups = [];
        if ($printGroupCodes) {
            $printGroups = array_filter(array_map('trim', explode(',', $printGroupCodes)));
        } else {
            $printGroups = $this->getPrintGroups($selectedGroups);
        }
        $printData = $this->buildSection($startDate, $endDate, $periodId, $printGroups, 'print');

        // Section C: UANG MAKAN — dari rekab uang makan
        $uangMakanData = $this->buildUangMakanSection($period, $selectedGroups);

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

    // ─── Private helpers ──────────────────────────────────

    /**
     * Build section data grouped by BAGIAN (group name).
     */
    private function buildSection(string $startDate, string $endDate, int $periodId, array $groupCodes, string $section): array
    {
        // Dapatkan employee_id yang punya roster di range tanggal
        $rosteredEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct('employee_id')
            ->pluck('employee_id');

        if ($rosteredEmployeeIds->isEmpty()) {
            return [];
        }

        // Query employees
        $query = Employee::whereIn('id', $rosteredEmployeeIds)
            ->where('is_active', true)
            ->with(['position', 'groups', 'groups.master', 'department']);

        if (!empty($groupCodes)) {
            $query->whereHas('groups', function ($q) use ($groupCodes) {
                $q->whereIn('reference_code', $groupCodes);
            });
        }

        $employees = $query->orderBy('name')->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $periodMonth = Carbon::parse($startDate)->format('Y-m');

        // Preload PayRecords untuk overtime
        $payRecords = PayRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // Preload BPJS
        $bpjsData = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // Group employees by their first group's name (BAGIAN)
        $grouped = [];

        foreach ($employees as $emp) {
            $firstGroup = $emp->groups->first();
            $groupName = $firstGroup && $firstGroup->master
                ? $firstGroup->master->name
                : ($emp->department?->name ?? 'TANPA BAGIAN');

            if (!isset($grouped[$groupName])) {
                $grouped[$groupName] = [];
            }
            $grouped[$groupName][] = $emp;
        }

        // Build per-group aggregates
        $result = [];

        foreach ($grouped as $bagian => $emps) {
            $jml = count($emps);
            $gaji = 0;
            $lembur = 0;
            $bpjsTk = 0;
            $bpjsKs = 0;

            foreach ($emps as $emp) {
                $salary = $emp->activeSalary($periodMonth);
                $baseSalary = $salary ? (float)$salary->base_salary : (float)($emp->base_salary ?? 0);

                // Overtime from PayRecord
                $pr = $payRecords->get($emp->id);
                $overtimePay = $pr ? (float)($pr->upah_lembur ?? 0) : 0;

                // BPJS
                $bpjs = $bpjsData->get($emp->id);
                if ($bpjs) {
                    $bpjsTk += (float)($bpjs->employee_jht ?? 0) + (float)($bpjs->employee_jkk ?? 0) + (float)($bpjs->employee_jkm ?? 0);
                    $bpjsKs += (float)($bpjs->employee_kesehatan ?? 0);
                }

                $gaji += $baseSalary;
                $lembur += $overtimePay;
            }

            $total = $gaji + $lembur;
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

        // Sort by bagian name
        usort($result, fn($a, $b) => strcasecmp($a['bagian'], $b['bagian']));

        // ─── Extra Employees ───
        // Extra employees tidak punya group — gabung ke "EXTRA" atau skip jika
        // section-specific filtering aktif
        if ($section === 'all_in') {
            $extraEmployees = ExtraEmployee::orderBy('nama')->get();
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

                $totalExtra = $gajiExtra + $lemburExtra;
                $result[] = [
                    'bagian'   => 'EXTRA',
                    'jml'      => $jml,
                    'gaji'     => round($gajiExtra, 2),
                    'lembur'   => round($lemburExtra, 2),
                    'total'    => round($totalExtra, 2),
                    'bpjs'     => round($bpjsExtra, 2),
                    'bpjs_tk'  => round($bpjsExtra, 2),
                    'bpjs_ks'  => 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Build Uang Makan section by calling rekab internally and grouping by bagian.
     */
    private function buildUangMakanSection(PayPeriod $period, array $selectedGroups): array
    {
        try {
            $uangMakanCtrl = app(UangMakanReportController::class);
            $rekabRequest  = Request::create(
                "/api/v1/reports/uang-makan/rekab?period_id={$period->id}",
                'GET'
            );
            $rekabResponse = $uangMakanCtrl->rekab($rekabRequest);
            $rekabData     = json_decode($rekabResponse->getContent(), true);
            $umItems       = $rekabData['data'] ?? [];

            // Group by group_name (BAGIAN)
            $grouped = [];

            foreach ($umItems as $item) {
                $groupName = $item['group_name'] ?? 'TANPA BAGIAN';

                if (!isset($grouped[$groupName])) {
                    $grouped[$groupName] = [
                        'jml'          => 0,
                        'uang_makan'   => 0,
                        'lembur_sabtu' => 0,
                        'lembur_minggu'=> 0,
                        'insentif'     => 0,
                        'total'        => 0,
                    ];
                }

                $nominals = $item['nominals'] ?? [];
                $grouped[$groupName]['jml']++;
                $grouped[$groupName]['uang_makan']   += (float)($nominals['uang_makan'] ?? 0);
                $grouped[$groupName]['lembur_sabtu'] += (float)($nominals['lembur_sabtu'] ?? 0);
                $grouped[$groupName]['lembur_minggu']+= (float)($nominals['lembur_minggu'] ?? 0);
                $grouped[$groupName]['insentif']     += (float)($nominals['insentif'] ?? 0);
                $grouped[$groupName]['total']        += (float)($item['total'] ?? 0);
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

    /**
     * Get print-related groups from selected groups.
     * Default: groups whose code contains 'PRINT' or name contains 'PRINT'.
     * Can be overridden by report config.
     */
    private function getPrintGroups(array $selectedGroups): array
    {
        if (empty($selectedGroups)) {
            return [];
        }

        // Try to get from report config first
        try {
            $configService = app(\App\Modules\Settings\Services\ReportConfigService::class);
            $config = $configService->getConfig('rekap-kerja');
            if (!empty($config['print_groups'])) {
                return array_intersect($selectedGroups, $config['print_groups']);
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        // Default: filter groups containing "PRINT" in the name
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
