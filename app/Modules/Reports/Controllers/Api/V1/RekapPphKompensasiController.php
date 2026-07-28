<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Employee\Models\EmployeeSalaryComponent;
use App\Modules\Attendance\Models\EmployeeOvertime;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Models\ExtraEmployee;
use App\Modules\Reports\Exports\RekapPphKompensasiPphExport;
use App\Modules\Reports\Exports\RekapPphKompensasiKompensasiExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
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

    /**
     * GET /api/v1/reports/rekap-pph-kompensasi/export-pph
     *
     * Export PPH ke Excel.
     */
    public function exportPph(Request $request)
    {
        $result = $this->buildData($request);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        $rows       = $result['pph'] instanceof \Illuminate\Support\Collection
            ? $result['pph']->toArray()
            : (array) $result['pph'];
        $periodName = $result['period_name'] ?? 'PPH';
        $dateStart  = $result['date_start'] ?? '';
        $dateEnd    = $result['date_end'] ?? '';

        $safe     = str_replace(' ', '_', trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $periodName)));
        $filename = "Rekap_PPH_{$safe}.xlsx";

        return Excel::download(
            new RekapPphKompensasiPphExport($rows, $periodName, $dateStart, $dateEnd),
            $filename
        );
    }

    /**
     * GET /api/v1/reports/rekap-pph-kompensasi/export-kompensasi
     *
     * Export Kompensasi ke Excel.
     */
    public function exportKompensasi(Request $request)
    {
        $result = $this->buildData($request);
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        $rows       = $result['kompensasi'] instanceof \Illuminate\Support\Collection
            ? $result['kompensasi']->toArray()
            : (array) $result['kompensasi'];
        $periodName = $result['period_name'] ?? 'Kompensasi';
        $dateStart  = $result['date_start'] ?? '';
        $dateEnd    = $result['date_end'] ?? '';

        $safe     = str_replace(' ', '_', trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $periodName)));
        $filename = "Rekap_Kompensasi_{$safe}.xlsx";

        return Excel::download(
            new RekapPphKompensasiKompensasiExport($rows, $periodName, $dateStart, $dateEnd),
            $filename
        );
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

        // Preload salary components
        $salaryComponents = EmployeeSalaryComponent::whereIn('employee_id', $employees->pluck('id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('employee_id');

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

        // ─── Section A: PPH ───
        $pphData = $employees->map(function ($emp) use ($payRecords, $overtimeSums, $bpjsByPeriod, $bpjsFallback, $salaryComponents) {
            $payRecord = $payRecords->get($emp->id);
            $bpjs      = $bpjsByPeriod->get($emp->id) ?? $bpjsFallback->get($emp->id);
            $sc        = $salaryComponents->get($emp->id);

            $nik = $emp->nik ?? '-';
            $nikTku = $nik !== '-' ? $nik . '000000' : '-';

            $bpjsTk = 0;
            $bpjsKs = 0;
            if ($bpjs) {
                $bpjsTk = (float)($bpjs->employer_jkk ?? 0) + (float)($bpjs->employer_jkm ?? 0);
                $bpjsKs = (float)($bpjs->employer_kesehatan ?? 0);
            }

            $gajiKotor = $payRecord ? (float) $payRecord->gaji_kotor : 0;

            $groupCodes = $emp->groups->pluck('reference_code')->toArray();
            $um = (float) (in_array('GRP-PS1', $groupCodes) || in_array('GRP-SS', $groupCodes) ? 0 : ($overtimeSums->get($emp->id) ?? 0));

            $pph = $sc ? (float)($sc->pph ?? 0) : 0;

            return [
                'id'           => $emp->id,
                'name'         => $emp->name,
                'nik'          => $nik,
                'nik_tku'      => $nikTku,
                'gender'       => $emp->gender,
                'status_label' => $emp->ptkp ?? '-',
                'total_gaji'   => $gajiKotor,
                'um'           => $um,
                'bpjs_tk'      => $bpjsTk,
                'bpjs_ks'      => $bpjsKs,
                'pph'          => $pph,
            ];
        });

        // ─── Section B: Kompensasi ───
        $kompensasiData = $employees->map(function ($emp) use ($salaryComponents) {
            $sc = $salaryComponents->get($emp->id);

            // Perhitungan kompensasi kontrak: (gaji_pokok + tj_masa_kerja) / 12
            $monthlyKompensasi = 0;
            if ($sc) {
                $gajiPokok  = (float) $sc->gaji_pokok;
                $tjMasaKerja = (float) $sc->tunjangan_masa_kerja;
                $monthlyKompensasi = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
            }

            $nik = $emp->nik ?? '-';
            $nikTku = $nik !== '-' ? $nik . '000000' : '-';

            return [
                'id'               => $emp->id,
                'name'             => $emp->name,
                'nik'              => $nik,
                'nik_tku'          => $nikTku,
                'gender'           => $emp->gender === 'L' ? 'L' : ($emp->gender === 'P' ? 'P' : '-'),
                'status_label'     => $emp->ptkp ?? '-',
                'total_kompensasi' => $monthlyKompensasi,
            ];
        });

        // ─── Extra Employees (karyawan titipan) untuk Section A ───
        $extraEmployees = collect();
        if ($request->exists('extra_ids')) {
            $extraIds = $request->input('extra_ids', '');
            if ($extraIds !== '') {
                $extraIdArray = array_filter(array_map('trim', explode(',', $extraIds)));
                if (!empty($extraIdArray)) {
                    $extraEmployees = ExtraEmployee::whereIn('id', $extraIdArray)->orderBy('nama')->get();
                }
            }
            // Kalau extra_ids ada tapi kosong → tetap empty (tidak ada yang dipilih)
        } else {
            // Backward compat: key extra_ids tidak ada → tampilkan semua
            $extraEmployees = ExtraEmployee::orderBy('nama')->get();
        }

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
                'um'           => 0,
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
