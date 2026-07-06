<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PayrollReportController extends Controller
{
    public function payroll(Request $request)
    {
        $result = $this->buildPayrollData($request);
        return response()->json($result);
    }

    public function resume(Request $request)
    {
        $result = $this->buildResumeData($request);
        return response()->json($result);
    }

    public function exportPayroll(Request $request)
    {
        $result = $this->buildPayrollData($request);
        $tab = $request->input('tab', 'all-in');
        $filename = 'Laporan_Payroll_' . $tab . '_' . str_replace(' ', '_', $result['period_name']) . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Reports\Exports\PayrollDetailExport($result['data'], $result['period_name']),
            $filename
        );
    }

    public function exportResume(Request $request)
    {
        $result = $this->buildResumeData($request);
        $tab = $request->input('tab', 'all-in');
        $filename = 'Laporan_Resume_' . $tab . '_' . str_replace(' ', '_', $result['period_name']) . '.xlsx';
        
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Reports\Exports\PayrollResumeExport($result['data'], $result['period_name']),
            $filename
        );
    }

    private function buildPayrollData(Request $request)
    {
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        
        // Tab All In: group code GRP-ALLIN
        // Karyawan Bulanan Print: GRP-SS, GRP-PS1, GRP-GD, GRP-SPR
        $tab = $request->input('tab', 'all-in'); // 'all-in' or 'print'

        $groupCodes = $tab === 'all-in' 
            ? ['GRP-ALLIN'] 
            : ['GRP-SS', 'GRP-PS1', 'GRP-GD', 'GRP-SPR'];

        $segment = $request->input('segment');

        $query = PayRecord::with(['employee', 'employee.position', 'employee.groups'])
            ->whereHas('employee', function ($q) use ($groupCodes) {
                $q->whereHas('groups', function ($gq) use ($groupCodes) {
                    $gq->whereIn('reference_code', $groupCodes);
                });
            });

        if ($period) {
            $query->where('pay_period_id', $period->id);
            if ($period->is_split && $segment) {
                $query->where('segment', $segment);
            }
        } else {
            // fallback to latest generated period if exists
            $latestRecord = PayRecord::latest('id')->first();
            if ($latestRecord) {
                $query->where('pay_period_id', $latestRecord->pay_period_id);
                $period = PayPeriod::find($latestRecord->pay_period_id);
                if ($period && $period->is_split && $segment) {
                    $query->where('segment', $segment);
                }
            }
        }

        $records = $query->get();

        $data = $records->map(function ($record) {
            $emp = $record->employee;
            return [
                'id' => $emp->id,
                'no_id' => $emp->employee_id,
                'name' => $emp->name,
                'bagian' => $emp->position->name ?? '-',
                'gender' => $emp->gender === 'male' ? 'L' : ($emp->gender === 'female' ? 'P' : '-'),
                'join_date' => $emp->join_date ? Carbon::parse($emp->join_date)->format('d/m/Y') : '-',
                'masa_kerja' => $record->hari_kerja,
                'status' => $emp->marital_status === 'single' ? 'TK' : 'K',
                'jml_anak' => $emp->number_of_children ?? 0,
                'account_no' => $emp->bank_account_number ?? '-',
                'premi' => (float)$record->premi,
                'gaji_pokok' => (float)$record->gaji_pokok,
                'tj_masa_kerja' => (float)$record->tj_masa_kerja,
                'hk' => $record->hari_kerja,
                'lm' => $record->lm_count,
                'lbr_jam' => $record->lembur_count,
                'gaji' => (float)$record->gaji,
                'lembur' => (float)$record->upah_lembur,
                'revisi' => (float)$record->revisi,
                'tunjangan' => (float)$record->tunjangan,
                'premi_hadir' => (float)$record->premi_hadir,
                'pblt' => (float)$record->pblt,
                'total' => (float)$record->gaji_kotor,
                'total_gaji' => (float)$record->gaji_kotor,
                'bpjs_tk' => (float)$record->bpjs_tk,
                'bpjs_ks' => (float)$record->bpjs_ks,
                'bpjs_pen' => (float)$record->bpjs_pen,
                'cashbon' => (float)$record->cashbon,
                'pph' => (float)$record->pph,
                'total_terima' => (float)$record->gaji_bersih,
                'uang_makan' => 0, // dihitung terpisah dari laporan uang makan
            ];
        });

        // Add dummy summary row logic here if needed or let frontend handle it

        $periodName = $period ? $period->name : 'Unknown Period';
        if ($period && $period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        return [
            'data' => $data,
            'period_name' => $periodName,
        ];
    }

    private function buildResumeData(Request $request)
    {
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $tab = $request->input('tab', 'all-in'); 

        $groupCodes = $tab === 'all-in' 
            ? ['GRP-ALLIN'] 
            : ['GRP-SS', 'GRP-PS1', 'GRP-GD', 'GRP-SPR'];

        $segment = $request->input('segment');

        $query = PayRecord::with(['employee', 'employee.position', 'employee.groups'])
            ->whereHas('employee', function ($q) use ($groupCodes) {
                $q->whereHas('groups', function ($gq) use ($groupCodes) {
                    $gq->whereIn('reference_code', $groupCodes);
                });
            });

        if ($period) {
            $query->where('pay_period_id', $period->id);
            if ($period->is_split && $segment) {
                $query->where('segment', $segment);
            }
        } else {
            $latestRecord = PayRecord::latest('id')->first();
            if ($latestRecord) {
                $query->where('pay_period_id', $latestRecord->pay_period_id);
                $period = PayPeriod::find($latestRecord->pay_period_id);
                if ($period && $period->is_split && $segment) {
                    $query->where('segment', $segment);
                }
            }
        }

        $records = $query->get();

        // Group by position
        $grouped = $records->groupBy(function($record) {
            return $record->employee->position->name ?? 'Unknown';
        });

        $data = [];
        foreach ($grouped as $position => $groupRecords) {
            $maleCount = $groupRecords->filter(fn($r) => $r->employee->gender === 'male')->count();
            $femaleCount = $groupRecords->filter(fn($r) => $r->employee->gender === 'female')->count();

            $data[] = [
                'bagian' => $position,
                'jml_karyawan_l' => $maleCount,
                'jml_karyawan_p' => $femaleCount,
                'jml_karyawan_total' => $maleCount + $femaleCount,
                'gaji' => $groupRecords->sum('gaji'),
                'lembur' => $groupRecords->sum('upah_lembur'),
                'revisi' => $groupRecords->sum('revisi'),
                'tj_masa_kerja' => $groupRecords->sum('tj_masa_kerja'),
                'tunjangan' => $groupRecords->sum('tunjangan'),
                'premi_hadir' => $groupRecords->sum('premi_hadir'),
                'pblt' => $groupRecords->sum('pblt'),
                'total' => $groupRecords->sum('gaji_kotor'),
                'bpjs_tk' => $groupRecords->sum('bpjs_tk'),
                'bpjs_ks' => $groupRecords->sum('bpjs_ks'),
                'bpjs_pen' => $groupRecords->sum('bpjs_pen'),
                'cashbon' => $groupRecords->sum('cashbon'),
                'revisi_pph' => $groupRecords->sum('pph'),
                'total_terima' => $groupRecords->sum('gaji_bersih'),
            ];
        }

        $periodName = $period ? $period->name : 'Unknown Period';
        if ($period && $period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        return [
            'data' => collect($data)->sortBy('bagian')->values(),
            'period_name' => $periodName,
        ];
    }
}
