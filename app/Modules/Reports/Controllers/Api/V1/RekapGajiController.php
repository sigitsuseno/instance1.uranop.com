<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Reports\Exports\RekapGajiExport;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Models\ExtraEmployee;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class RekapGajiController extends Controller
{
    /**
     * GET /api/v1/reports/rekap-gaji
     *
     * Mengambil data rekap gaji dari karyawan yang memiliki roster
     * di bulan/periode terpilih, difilter berdasarkan group "Imported Shift/Group".
     */
    public function index(Request $request)
    {
        $result = $this->buildRekapGajiData($request);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result; // error response
        }

        return response()->json($result);
    }

    /**
     * GET /api/v1/reports/rekap-gaji/export
     *
     * Export Excel — data sama dengan index + uang makan.
     */
    public function export(Request $request)
    {
        $result = $this->buildRekapGajiData($request);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result; // error response
        }

        // Merge uang makan dari rekab endpoint
        $result = $this->mergeUangMakan($result, $request);

        $rows    = $result['data'] instanceof \Illuminate\Support\Collection
            ? $result['data']->toArray()
            : (array) $result['data'];
        $periodName = $result['period_name'] ?? 'Rekap_Gaji';
        $dateStart  = $result['date_start'] ?? '';

        $safePeriod = preg_replace('/[^a-zA-Z0-9\s]/', '', $periodName);
        $safePeriod = str_replace(' ', '_', trim($safePeriod));
        $filename   = "Rekap_Gaji_{$safePeriod}.xlsx";

        return Excel::download(
            new RekapGajiExport($rows, $periodName, $dateStart),
            $filename
        );
    }

    /**
     * GET /api/v1/reports/rekap-gaji/groups
     *
     * Ambil daftar group dengan group_label = 'Imported Shift/Group'
     * untuk ditampilkan di checkboxes setting.
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

    // ─── Private helpers ──────────────────────────────────────────

    /**
     * Build rekap gaji data — shared between index() and export().
     */
    private function buildRekapGajiData(Request $request)
    {
        $periodId = $request->input('period_id');
        $groupCodes = $request->input('groups', '');

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

        // Dapatkan employee_id yang punya roster di range tanggal
        $rosteredEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct('employee_id')
            ->pluck('employee_id');

        if ($rosteredEmployeeIds->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'No roster data for this period']);
        }

        // Query employees
        $query = Employee::whereIn('id', $rosteredEmployeeIds)
            ->where('is_active', true)
            ->with(['position', 'groups', 'groups.master']);

        if (!empty($selectedGroups)) {
            $query->whereHas('groups', function ($q) use ($selectedGroups) {
                $q->whereIn('reference_code', $selectedGroups);
            });
        }

        $employees = $query->orderBy('name')->get();

        $periodMonth = $period->start_date->format('Y-m');

        // Preload BPJS untuk periode ini
        $bpjsData = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // Build response
        $data = $employees->map(function ($emp) use ($periodMonth, $bpjsData) {
            $salary = $emp->activeSalary($periodMonth);
            $bpjs   = $bpjsData->get($emp->id);

            $groupCodes = $emp->groups->pluck('reference_code')->toArray();

            // Status label
            $maritalStatus = $emp->marital_status ?? '';
            $children = $emp->families()
                ->where('is_dependent', true)
                ->count();
            $statusLabel = '-';
            if ($maritalStatus === 'single') {
                $statusLabel = "TK/{$children}";
            } elseif ($maritalStatus === 'married') {
                $statusLabel = "K/{$children}";
            }

            // BPJS TK = JHT + JKK + JKM (employee portion)
            $bpjsTk = 0;
            $bpjsKs = 0;
            if ($bpjs) {
                $bpjsTk = (float)($bpjs->employee_jht ?? 0) + (float)($bpjs->employee_jkk ?? 0) + (float)($bpjs->employee_jkm ?? 0);
                $bpjsKs = (float)($bpjs->employee_kesehatan ?? 0);
            }

            $baseSalary = $salary ? (float)$salary->base_salary : (float)($emp->base_salary ?? 0);
            $premi      = $salary ? (float)$salary->premi : (float)($emp->premi ?? 0);
            $tunjangan  = $salary ? (float)$salary->tunjangan : (float)($emp->tunjangan ?? 0);
            $totalGaji = $baseSalary + $tunjangan + $premi;

            return [
                'id'           => $emp->id,
                'name'         => $emp->name,
                'account_no'   => $emp->bank_account_number ?? '-',
                'status_label' => $statusLabel,
                'gender'       => $emp->gender === 'male' ? 'L' : ($emp->gender === 'female' ? 'P' : '-'),
                'gaji'         => $baseSalary,
                'total_gaji'   => $totalGaji,
                'bpjs_tk'      => $bpjsTk,
                'bpjs_ks'      => $bpjsKs,
                'uang_makan'   => 0,
                'groups'       => $groupCodes,
                'department'   => $emp->department?->name ?? '-',
                'position'     => $emp->position?->name ?? '-',
            ];
        });

        // ─── Extra Employees (karyawan titipan) ───
        $extraEmployees = ExtraEmployee::orderBy('nama')->get();

        $extraData = $extraEmployees->map(function ($extra) {
            $komponen = $extra->komponen_gaji ?? [];
            $gender   = $extra->gender ?: '-';
            $statusLabel = $extra->status_ptkp ?: '-';

            return [
                'id'           => 'extra_' . $extra->id,
                'name'         => $extra->nama,
                'account_no'   => $extra->account ?: '-',
                'status_label' => $statusLabel,
                'gender'       => in_array($gender, ['L', 'P']) ? $gender : '-',
                'gaji'         => (float) ($komponen['gaji_pokok'] ?? 0),
                'total_gaji'   => (float) ($komponen['total_gaji'] ?? 0),
                'bpjs_tk'      => (float) ($komponen['ttl_bpjs'] ?? 0),
                'bpjs_ks'      => 0,
                'uang_makan'   => 0,
                'groups'       => [],
                'department'   => '-',
                'position'     => '-',
                'source'       => 'extra',
            ];
        });

        $data = $data->merge($extraData);

        return [
            'data'        => $data->values(),
            'period_name' => $period->name,
            'date_start'  => $startDate,
            'date_end'    => $endDate,
        ];
    }

    /**
     * Merge uang_makan data from rekab endpoint into rekap gaji rows.
     */
    private function mergeUangMakan(array $result, Request $request): array
    {
        try {
            $periodId  = $request->input('period_id');
            $period    = PayPeriod::find($periodId);
            $startDate = $period ? $period->start_date : null;

            if (!$startDate) {
                return $result;
            }

            $month = $startDate->format('m');
            $year  = $startDate->format('Y');

            // Call UangMakanReportController's rekab internally
            $uangMakanCtrl = app(UangMakanReportController::class);
            $rekabRequest  = Request::create(
                "/api/v1/reports/uang-makan/rekab?month={$month}&year={$year}",
                'GET'
            );
            $rekabResponse = $uangMakanCtrl->rekab($rekabRequest);
            $rekabData     = json_decode($rekabResponse->getContent(), true);
            $umItems       = $rekabData['data'] ?? [];

            // Build map: employee_id => total uang_makan
            $umMap = [];
            foreach ($umItems as $item) {
                $nominals = $item['nominals'] ?? [];
                $total = (float)($nominals['uang_makan'] ?? 0)
                       + (float)($nominals['lembur_sabtu'] ?? 0)
                       + (float)($nominals['lembur_minggu'] ?? 0);
                $umMap[$item['id']] = $total;
            }

            // Merge into data
            $data = $result['data'];
            if ($data instanceof \Illuminate\Support\Collection) {
                $data = $data->map(function ($row) use ($umMap) {
                    if (isset($umMap[$row['id']])) {
                        $row['uang_makan'] = $umMap[$row['id']];
                    }
                    return $row;
                });
                $result['data'] = $data;
            }
        } catch (\Throwable $e) {
            // Uang makan not critical — skip silently
        }

        return $result;
    }
}
