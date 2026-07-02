<?php

namespace App\Modules\Supervisor\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Supervisor\Payroll\Models\SupervisorPayRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SupervisorGajiKaryawanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment' => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = SupervisorPayRecord::with(['employee.department', 'employee.position', 'employee.groups'])
            ->where('pay_period_id', $period->id)
            ->join('employees', 'supervisor_payrolls.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('supervisor_payrolls.*');

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        $records = $query->get()->map(function ($record) {
            $emp = $record->employee;
            $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            return [
                'id' => $record->id,
                'employee_code' => $emp?->employee_code ?? $emp?->nip ?? '-',
                'name' => $emp?->name ?? '-',
                'department' => $emp?->department?->name ?? '-',
                'position' => $emp?->position?->name ?? '-',
                'gender' => $emp?->gender ?? '-',
                'join_year' => $joinDate ? $joinDate->format('d-M-Y') : '-',
                'groups' => $emp?->groups?->pluck('reference_code')->toArray() ?? [],
                'bank_name' => $emp?->bank_name ?? '-',
                'bank_account_number' => $emp?->bank_account_number ?? '-',
                'bank_account_name' => $emp?->bank_account_name ?? '-',
                // Data masukan
                'gaji_pokok' => (float) $record->gaji_pokok,
                'premi' => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan' => (float) $record->tunjangan,
                'hari_kerja' => (int) $record->hari_kerja,
                'lm' => (int) $record->lm,
                'lembur_count' => (int) $record->lembur_count,
                // Hasil hitungan
                'gaji' => (float) $record->gaji,
                'upah_lembur' => (float) $record->upah_lembur,
                'revisi' => (float) $record->revisi,
                'premi_hadir' => (float) $record->premi_hadir,
                'pblt' => (float) $record->pblt,
                'total' => (float) $record->gaji_kotor,
                // Potongan
                'bpjs_tk' => (float) $record->bpjs_tk,
                'bpjs_ks' => (float) $record->bpjs_ks,
                'bpjs_pen' => (float) $record->bpjs_pen,
                'cashbon' => (float) $record->cashbon,
                'pph' => (float) $record->pph,
                'gaji_bersih' => (float) $record->gaji_bersih,
            ];
        });

        return response()->json([
            'data' => $records,
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'is_split' => $period->is_split,
                'segment' => $segment,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment' => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = SupervisorPayRecord::with(['employee.department', 'employee.position', 'employee.groups'])
            ->where('pay_period_id', $period->id)
            ->join('employees', 'supervisor_payrolls.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('supervisor_payrolls.*');

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        $records = $query->get()->map(function ($record) {
            $emp = $record->employee;
            $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            return [
                'employee_code' => $emp?->employee_code ?? $emp?->nip ?? '-',
                'name' => $emp?->name ?? '-',
                'department' => $emp?->department?->name ?? '-',
                'position' => $emp?->position?->name ?? '-',
                'gender' => $emp?->gender ?? '-',
                'join_year' => $joinDate ? $joinDate->format('d-M-Y') : '-',
                'groups' => $emp?->groups?->pluck('reference_code')->toArray() ?? [],
                'bank_name' => $emp?->bank_name ?? '-',
                'bank_account_number' => $emp?->bank_account_number ?? '-',
                'bank_account_name' => $emp?->bank_account_name ?? '-',
                'gaji_pokok' => (float) $record->gaji_pokok,
                'premi' => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan' => (float) $record->tunjangan,
                'hari_kerja' => (int) $record->hari_kerja,
                'lm' => (int) $record->lm,
                'lembur_count' => (int) $record->lembur_count,
                'gaji' => (float) $record->gaji,
                'upah_lembur' => (float) $record->upah_lembur,
                'revisi' => (float) $record->revisi,
                'premi_hadir' => (float) $record->premi_hadir,
                'pblt' => (float) $record->pblt,
                'total' => (float) $record->gaji_kotor,
                'bpjs_tk' => (float) $record->bpjs_tk,
                'bpjs_ks' => (float) $record->bpjs_ks,
                'bpjs_pen' => (float) $record->bpjs_pen,
                'cashbon' => (float) $record->cashbon,
                'pph' => (float) $record->pph,
                'gaji_bersih' => (float) $record->gaji_bersih,
            ];
        });

        $payrollConfig = \App\Modules\Payroll\Models\PayrollConfig::getConfig('gaji_karyawan');
        $sectionA = $payrollConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];
        $sectionB = $payrollConfig['sections']['B'] ?? ['GRP-GD', 'GRP-SS', 'GRP-PS1'];

        $secAData = [];
        $secBData = [];

        foreach ($records as $r) {
            $groups = $r['groups'] ?? [];
            if (array_intersect($groups, $sectionA)) {
                $secAData[] = $r;
            } elseif (array_intersect($groups, $sectionB)) {
                $secBData[] = $r;
            }
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        $filename = 'Laporan_Gaji_Karyawan_Supervisor_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Reports\Exports\GajiKaryawanExport($secAData, $secBData, $periodName),
            $filename
        );
    }
}
