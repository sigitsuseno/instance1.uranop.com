<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Attendance\Models\EmployeeOvertime;
use App\Modules\Reports\Exports\RekapGajiExport;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Models\ExtraEmployee;
use Illuminate\Http\Request;
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

        // Preload BPJS — cari per period dulu, fallback ke yg terbaru
        $bpjsByPeriod = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // Cari BPJS terbaru buat karyawan yg belum punya data period ini
        $employeeIdsWithoutBpjs = $employees->pluck('id')->diff($bpjsByPeriod->keys());
        $bpjsFallback = collect();
        if ($employeeIdsWithoutBpjs->isNotEmpty()) {
            $bpjsFallback = EmployeeBpjs::whereIn('employee_id', $employeeIdsWithoutBpjs)
                ->orderBy('pay_period_id', 'desc')
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('employee_id')
                ->map->first();
        }

        // Preload PayRecord per period
        $payRecords = PayRecord::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // Preload EmployeeOvertime sum nominal per employee in period
        $overtimeSums = EmployeeOvertime::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_periode_id', $periodId)
            ->groupBy('employee_id')
            ->selectRaw('employee_id, SUM(nominal) as total_nominal')
            ->pluck('total_nominal', 'employee_id');

        // Build response
        $data = $employees->map(function ($emp) use ($bpjsByPeriod, $bpjsFallback, $payRecords, $overtimeSums) {
            $bpjs   = $bpjsByPeriod->get($emp->id) ?? $bpjsFallback->get($emp->id);

            $groupCodes = $emp->groups->pluck('reference_code')->toArray();

            // BPJS TK = employer JHT + JKK + JKM
            $bpjsTk = 0;
            $bpjsKs = 0;
            if ($bpjs) {
                $bpjsTk = (float)($bpjs->employer_jht ?? 0) + (float)($bpjs->employer_jkk ?? 0) + (float)($bpjs->employer_jkm ?? 0);
                $bpjsKs = (float)($bpjs->employee_kesehatan ?? 0);
            }

            $payRecord = $payRecords->get($emp->id);
            $gajiKotor = $payRecord ? (float) $payRecord->gaji_kotor : 0;
            $um        = (float) (in_array('GRP-PS1', $groupCodes) || in_array('GRP-SS', $groupCodes) ? 0 : ($overtimeSums->get($emp->id) ?? 0));
            $totalGaji = $gajiKotor + $um;

            return [
                'id'           => $emp->id,
                'name'         => $emp->name,
                'account_no'   => $emp->bank_account_number ?? '-',
                'status_label' => $emp->ptkp ?? '-',
                'gender'       => in_array($emp->gender, ['L', 'P']) ? $emp->gender : '-',
                'gaji'         => $gajiKotor,
                'total_gaji'   => $totalGaji,
                'bpjs_tk'      => $bpjsTk,
                'bpjs_ks'      => $bpjsKs,
                'uang_makan'   => $um,
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

}
