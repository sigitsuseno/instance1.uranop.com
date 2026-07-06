<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

        // 1️⃣ Dapatkan employee_id yang punya roster di range tanggal
        $rosteredEmployeeIds = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->distinct('employee_id')
            ->pluck('employee_id');

        if ($rosteredEmployeeIds->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'No roster data for this period']);
        }

        // 2️⃣ Query employees
        $query = Employee::whereIn('id', $rosteredEmployeeIds)
            ->where('is_active', true)
            ->with(['position', 'groups', 'groups.master']);

        // Filter by selected groups (via employee_groups.reference_code)
        if (!empty($selectedGroups)) {
            $query->whereHas('groups', function ($q) use ($selectedGroups) {
                $q->whereIn('reference_code', $selectedGroups);
            });
        }

        $employees = $query->orderBy('name')->get();

        // 3️⃣ Ambil data gaji aktif per periode
        $periodMonth = $period->start_date->format('Y-m');

        // Preload BPJS untuk periode ini
        $bpjsData = EmployeeBpjs::whereIn('employee_id', $employees->pluck('id'))
            ->where('pay_period_id', $periodId)
            ->get()
            ->keyBy('employee_id');

        // 4️⃣ Build response
        $data = $employees->map(function ($emp) use ($periodMonth, $bpjsData) {
            $salary = $emp->activeSalary($periodMonth);
            $bpjs   = $bpjsData->get($emp->id);

            // Determine groups untuk section mapping
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

            // Gaji dari activeSalary
            $baseSalary = $salary ? (float)$salary->base_salary : (float)($emp->base_salary ?? 0);
            $premi      = $salary ? (float)$salary->premi : (float)($emp->premi ?? 0);
            $tunjangan  = $salary ? (float)$salary->tunjangan : (float)($emp->tunjangan ?? 0);

            // Total gaji = gaji pokok + tunjangan + premi
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
                'uang_makan'   => 0, // diisi dari endpoint terpisah (opsi 1)
                'groups'       => $groupCodes,
                'department'   => $emp->department?->name ?? '-',
                'position'     => $emp->position?->name ?? '-',
            ];
        });

        return response()->json([
            'data'        => $data->values(),
            'period_name' => $period->name,
            'date_start'  => $startDate,
            'date_end'    => $endDate,
        ]);
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
}
