<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PayslipController extends Controller
{
    /**
     * Get payslip data for a given period (and optionally segment).
     * Returns the pay records formatted for payslip rendering.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
            'search'    => 'nullable|string|max:100',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        // Get HK (fixed_work_day) from system settings
        $setting = SystemSetting::first();
        $fixedWorkDay = (int) ($setting?->fixed_working_day ?? 25);

        $query = PayRecord::with(['employee.department', 'employee.position'])
            ->where('pay_period_id', $period->id)
            ->where('status', 'generated');

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('employee_code', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $records = $query->orderBy('id')->get();

        // If split, we also need the other segment's data for combined slip
        $otherSegmentRecords = collect();
        if ($period->is_split) {
            $otherSegment = $segment === 'A' ? 'B' : 'A';
            $otherSegmentRecords = PayRecord::with(['employee'])
                ->where('pay_period_id', $period->id)
                ->where('status', 'generated')
                ->where('segment', $otherSegment)
                ->get()
                ->keyBy('employee_id');
        }

        $data = $records->map(function ($record) use ($period, $fixedWorkDay, $otherSegmentRecords) {
            $emp = $record->employee;
            $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            // Determine HK for this segment
            $hk = $fixedWorkDay;
            if ($period->is_split && $record->segment) {
                // For split, use the hari_kerja + deduct_day to recover HK for this segment
                $hk = (int) $record->hari_kerja + (int) round((float) $record->deduct_day);
            }

            $ratePerHari = $hk > 0 ? (float) $record->gaji_pokok / $fixedWorkDay : 0;
            $deductDays = (float) $record->deduct_day;
            $potKehadiran = $deductDays * $ratePerHari;

            $item = [
                'id'              => $record->id,
                'employee_id'     => $record->employee_id,
                'employee_code'   => $emp?->employee_code ?? $emp?->nip ?? '-',
                'employee_name'   => $emp?->name ?? '-',
                'department'      => $emp?->department?->name ?? '-',
                'position'        => $emp?->position?->name ?? '-',
                'gender'          => $emp?->gender ?? '-',
                'join_year'       => $joinDate ? $joinDate->format('Y') : '-',
                'remaining_leave' => 0,
                'segment'         => $record->segment,
                // Payslip display fields
                'gaji_pokok'      => (float) $record->gaji_pokok,
                'premi'           => (float) $record->premi,
                'tj_masa_kerja'   => (float) $record->tj_masa_kerja,
                'tunjangan'       => (float) $record->tunjangan,
                'hk'              => $hk,
                'hari_kerja'      => (int) $record->hari_kerja,
                'deduct_day'      => $deductDays,
                'rate_per_hari'   => round($ratePerHari),
                'lm'              => (int) $record->lm,
                'lm_count'        => (int) $record->lm_count,
                'lembur_count'    => (int) $record->lembur_count,
                // Calculated values
                'gaji'            => (float) $record->gaji,
                'upah_lembur'     => (float) $record->upah_lembur,
                'premi_hadir'     => (float) $record->premi_hadir,
                'revisi'          => (float) $record->revisi,
                'gaji_kotor'      => (float) $record->gaji_kotor,
                // Deductions
                'bpjs_tk'         => (float) $record->bpjs_tk,
                'bpjs_ks'         => (float) $record->bpjs_ks,
                'bpjs_pen'        => (float) $record->bpjs_pen,
                'pph'             => (float) $record->pph,
                'cashbon'         => (float) $record->cashbon,
                'pot_kehadiran'   => round($potKehadiran),
                'pblt'            => (float) $record->pblt,
                'gaji_bersih'     => (float) $record->gaji_bersih,
            ];

            // If split period, attach the other segment's data
            if ($period->is_split && $otherSegmentRecords->has($record->employee_id)) {
                $other = $otherSegmentRecords->get($record->employee_id);
                $otherHk = (int) $other->hari_kerja + (int) round((float) $other->deduct_day);
                $otherRate = $fixedWorkDay > 0 ? (float) $other->gaji_pokok / $fixedWorkDay : 0;
                $otherDeductDays = (float) $other->deduct_day;
                $otherPotKehadiran = $otherDeductDays * $otherRate;

                $item['other_segment'] = [
                    'segment'       => $other->segment,
                    'gaji_pokok'    => (float) $other->gaji_pokok,
                    'premi'         => (float) $other->premi,
                    'tj_masa_kerja' => (float) $other->tj_masa_kerja,
                    'tunjangan'     => (float) $other->tunjangan,
                    'hk'            => $otherHk,
                    'hari_kerja'    => (int) $other->hari_kerja,
                    'deduct_day'    => $otherDeductDays,
                    'rate_per_hari' => round($otherRate),
                    'lm'            => (int) $other->lm,
                    'lm_count'      => (int) $other->lm_count,
                    'lembur_count'  => (int) $other->lembur_count,
                    'gaji'          => (float) $other->gaji,
                    'upah_lembur'   => (float) $other->upah_lembur,
                    'premi_hadir'   => (float) $other->premi_hadir,
                    'revisi'        => (float) $other->revisi,
                    'gaji_kotor'    => (float) $other->gaji_kotor,
                    'bpjs_tk'       => (float) $other->bpjs_tk,
                    'bpjs_ks'       => (float) $other->bpjs_ks,
                    'bpjs_pen'      => (float) $other->bpjs_pen,
                    'pph'           => (float) $other->pph,
                    'cashbon'       => (float) $other->cashbon,
                    'pot_kehadiran' => round($otherPotKehadiran),
                    'pblt'          => (float) $other->pblt,
                    'gaji_bersih'   => (float) $other->gaji_bersih,
                ];
            }

            return $item;
        });

        // Summary stats
        $stats = [
            'total_karyawan'  => $data->count(),
            'total_gaji_kotor' => $data->sum('gaji_kotor'),
            'total_potongan'  => $data->sum(fn($d) => $d['bpjs_tk'] + $d['bpjs_ks'] + $d['bpjs_pen'] + $d['pph'] + $d['cashbon'] + $d['pot_kehadiran']),
            'total_bersih'    => $data->sum('gaji_bersih'),
        ];

        return response()->json([
            'data'    => $data->values(),
            'stats'   => $stats,
            'period'  => [
                'id'         => $period->id,
                'name'       => $period->name,
                'is_split'   => $period->is_split,
                'segment'    => $segment,
                'start_date' => $period->start_date?->format('Y-m-d'),
                'end_date'   => $period->end_date?->format('Y-m-d'),
            ],
            'fixed_work_day' => $fixedWorkDay,
        ]);
    }
}
