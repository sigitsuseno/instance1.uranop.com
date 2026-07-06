<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Employee\Models\EmployeeSalaryComponent;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Models\ExtraEmployee;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RekapPphKompensasiController extends Controller
{
    /**
     * GET /api/v1/reports/rekap-pph-kompensasi
     *
     * Section A: PPH (No, NAMA, NIK, NIK TKU, L/P, STATUS, TOTAL GAJI, BPJS TK, BPJS KESEHATAN, PPH)
     * Section B: Kompensasi (No, NAMA, NIK, NIK TKU, STATUS, TOTAL KOMPENSASI)
     */
    public function index(Request $request)
    {
        $result = $this->buildData($request);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json($result);
    }

    /**
     * GET /api/v1/reports/rekap-pph-kompensasi/groups
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

    private function buildData(Request $request)
    {
        $periodId = $request->input('period_id');
        $groupCodes = $request->input('groups', '');

        if (!$periodId) {
            return response()->json(['pph' => [], 'kompensasi' => [], 'message' => 'Period ID required'], 422);
        }

        $period = PayPeriod::find($periodId);
        if (!$period) {
            return response()->json(['pph' => [], 'kompensasi' => [], 'message' => 'Period not found'], 404);
        }

        $startDate = $period->start_date->format('Y-m-d');
        $endDate   = $period->end_date->format('Y-m-d');

        $selectedGroups = [];
        if ($groupCodes) {
            $selectedGroups = array_filter(array_map('trim', explode(',', $groupCodes)));
        }

        // Karyawan yang punya roster di range tanggal periode
        $rosteredEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct('employee_id')
            ->pluck('employee_id');

        if ($rosteredEmployeeIds->isEmpty()) {
            return response()->json([
                'pph'          => [],
                'kompensasi'   => [],
                'period_name'  => $period->name,
                'date_start'   => $startDate,
                'date_end'     => $endDate,
                'message'      => 'No roster data for this period',
            ]);
        }

        $query = Employee::whereIn('id', $rosteredEmployeeIds)
            ->where('is_active', true)
            ->with(['position', 'groups', 'families']);

        if (!empty($selectedGroups)) {
            $query->whereHas('groups', function ($q) use ($selectedGroups) {
                $q->whereIn('reference_code', $selectedGroups);
            });
        }

        $employees = $query->orderBy('name')->get();

        $periodMonth = $period->start_date->format('Y-m');

        // Preload BPJS
        $bpjsData = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // Preload salary components
        $salaryComponents = EmployeeSalaryComponent::whereIn('employee_id', $employees->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('employee_id');

        // ─── Section A: PPH ───
        $pphData = $employees->map(function ($emp) use ($periodMonth, $bpjsData, $salaryComponents) {
            $salary = $emp->activeSalary($periodMonth);
            $bpjs   = $bpjsData->get($emp->id);
            $sc     = $salaryComponents->get($emp->id);

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

            $nik = $emp->nik ?? '-';
            $nikTku = $nik !== '-' ? $nik . '000000' : '-';

            $bpjsTk = 0;
            $bpjsKs = 0;
            if ($bpjs) {
                $bpjsTk = (float)($bpjs->employee_jkk ?? 0) + (float)($bpjs->employee_jkm ?? 0);
                $bpjsKs = (float)($bpjs->employee_kesehatan ?? 0);
            }

            $baseSalary = $salary ? (float)$salary->base_salary : (float)($emp->base_salary ?? 0);
            $premi      = $salary ? (float)$salary->premi : (float)($emp->premi ?? 0);
            $tunjangan  = $salary ? (float)$salary->tunjangan : (float)($emp->tunjangan ?? 0);
            $totalGaji  = $baseSalary + $tunjangan + $premi;

            $pph = $sc ? (float)($sc->pph ?? 0) : 0;

            return [
                'id'           => $emp->id,
                'name'         => $emp->name,
                'nik'          => $nik,
                'nik_tku'      => $nikTku,
                'gender'       => $emp->gender === 'male' ? 'L' : ($emp->gender === 'female' ? 'P' : '-'),
                'status_label' => $statusLabel,
                'total_gaji'   => $totalGaji,
                'bpjs_tk'      => $bpjsTk,
                'bpjs_ks'      => $bpjsKs,
                'pph'          => $pph,
            ];
        });

        // ─── Section B: Kompensasi ───
        $kompensasiData = $employees->map(function ($emp) use ($salaryComponents) {
            $sc = $salaryComponents->get($emp->id);

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

            $nik = $emp->nik ?? '-';
            $nikTku = $nik !== '-' ? $nik . '000000' : '-';

            $kompensasi = $sc ? (float)($sc->kompensasi_pph ?? 0) : 0;

            return [
                'id'               => $emp->id,
                'name'             => $emp->name,
                'nik'              => $nik,
                'nik_tku'          => $nikTku,
                'gender'           => $emp->gender === 'male' ? 'L' : ($emp->gender === 'female' ? 'P' : '-'),
                'status_label'     => $statusLabel,
                'total_kompensasi' => $kompensasi,
            ];
        });

        // ─── Extra Employees (karyawan titipan) untuk Section A ───
        $extraEmployees = ExtraEmployee::orderBy('nama')->get();

        $extraPphData = $extraEmployees->map(function ($extra) {
            $komponen = $extra->komponen_gaji ?? [];
            $nik     = $extra->nik ?? '-';
            $nikTku  = $extra->nik_tku ?? ($nik !== '-' ? $nik . '000000' : '-');
            $gender  = $extra->gender ?: '-';
            $statusLabel = $extra->status_ptkp ?: '-';

            return [
                'id'           => 'extra_' . $extra->id,
                'name'         => $extra->nama,
                'nik'          => $nik,
                'nik_tku'      => $nikTku,
                'gender'       => in_array($gender, ['L', 'P']) ? $gender : '-',
                'status_label' => $statusLabel,
                'total_gaji'   => (float) ($komponen['total_gaji'] ?? 0),
                'bpjs_tk'      => (float) ($komponen['ttl_bpjs'] ?? 0),
                'bpjs_ks'      => 0,
                'pph'          => (float) ($komponen['ttl_pph'] ?? 0),
                'source'       => 'extra',
            ];
        });

        return [
            'pph'         => $pphData->merge($extraPphData)->values(),
            'kompensasi'  => $kompensasiData->values(),
            'period_name' => $period->name,
            'date_start'  => $startDate,
            'date_end'    => $endDate,
        ];
    }
}
