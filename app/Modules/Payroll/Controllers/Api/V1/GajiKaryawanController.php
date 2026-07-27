<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Models\ExtraEmployee;
use App\Modules\Employee\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Payroll\Exports\KirimAllExport;
use App\Modules\Payroll\Models\PayrollConfig;
use Illuminate\Http\Request;
use Carbon\Carbon;

class GajiKaryawanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment' => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = PayRecord::with(['employee.department', 'employee.position', 'employee.groups'])
            ->where('pay_period_id', $period->id)
            ->join('employees', 'pay_records.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('pay_records.*');

        if ($period->is_split) {
            // Split: harus pilih segment
            if (!$segment) {
                $segment = 'A'; // default ke seg-1
            }
            $query->where('segment', $segment);
        }

        $records = $query->get()->map(function ($record) {
            $emp = $record->employee;
            $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            return [
                'id' => $record->id,
                'employee_id' => $emp?->id,
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
                'bank_cabang' => $emp?->bank_cabang ?? '',
                'notes' => $record->notes ?? '',
                // Data masukan
                'gaji_pokok' => (float) $record->gaji_pokok,
                'premi' => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan' => (float) $record->tunjangan,
                'hari_kerja' => (int) $record->hari_kerja,
                'lm' => (int) $record->lm,
                'lm_count' => (int) $record->lm_count,
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
                'tanggal_penggajian' => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Update transfer info: bank_cabang (employee) & notes (pay_record).
     * PUT /api/v1/payroll/gaji-karyawan/{id}/transfer-info
     */
    public function updateTransferInfo(Request $request, $id)
    {
        $validated = $request->validate([
            'bank_cabang' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $record = PayRecord::with('employee')->findOrFail($id);

        if (array_key_exists('bank_cabang', $validated) && $record->employee) {
            $record->employee->bank_cabang = $validated['bank_cabang'];
            $record->employee->save();
        }

        if (array_key_exists('notes', $validated)) {
            $record->notes = $validated['notes'];
            $record->save();
        }

        $emp = $record->employee;

        return response()->json([
            'message' => 'Data transfer berhasil diupdate.',
            'data' => [
                'id' => $record->id,
                'employee_id' => $emp?->id,
                'bank_cabang' => $emp?->bank_cabang ?? '',
                'notes' => $record->notes ?? '',
            ],
        ]);
    }

    /**
     * Bulk update bank_cabang untuk semua employee di periode tertentu.
     * PUT /api/v1/payroll/gaji-karyawan/bulk-update-cabang
     */
    public function bulkUpdateCabang(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment' => 'nullable|in:A,B',
            'bank_cabang' => 'required|string|max:100',
        ]);

        $query = PayRecord::where('pay_period_id', $validated['period_id']);

        if (!empty($validated['segment'])) {
            $query->where('segment', $validated['segment']);
        }

        $employeeIds = $query->pluck('employee_id');

        $updated = Employee::whereIn('id', $employeeIds)->update([
            'bank_cabang' => $validated['bank_cabang'],
        ]);

        return response()->json([
            'message' => "Cabang berhasil diupdate untuk {$updated} karyawan.",
            'updated_count' => $updated,
        ]);
    }

    /**
     * Update lembur fields + cashbon + recalculate upah_lembur, gaji_kotor & gaji_bersih.
     * PUT /api/v1/payroll/gaji-karyawan/{id}/upah-lembur
     */
    public function updateUpahLembur(Request $request, $id)
    {
        $validated = $request->validate([
            'lm'           => 'nullable|integer|min:0',
            'lm_count'     => 'nullable|integer|min:0',
            'lembur_count' => 'nullable|integer|min:0',
            'cashbon'      => 'nullable|numeric|min:0',
        ]);

        $record = PayRecord::with('employee.groups')->findOrFail($id);

        // Update field yang dikirim, sisanya pakai nilai existing
        if (array_key_exists('lm', $validated)) {
            $record->lm = $validated['lm'];
        }
        if (array_key_exists('lm_count', $validated)) {
            $record->lm_count = $validated['lm_count'];
        }
        if (array_key_exists('lembur_count', $validated)) {
            $record->lembur_count = $validated['lembur_count'];
        }
        if (array_key_exists('cashbon', $validated)) {
            $record->cashbon = $validated['cashbon'];
        }

        // ── Kalkulasi ulang upah_lembur ──
        $gajiPokok   = (float) $record->gaji_pokok;
        $tjMasaKerja = (float) $record->tj_masa_kerja;
        $tunjangan   = (float) $record->tunjangan;
        $lmCount     = (int) $record->lm_count;
        $lemburCount = (int) $record->lembur_count;

        // Cek GRP-SPR: hanya dari lm_count
        $employee = $record->employee;
        $isSpr = $employee && $employee->groups->contains(fn($g) => $g->reference_code === 'GRP-SPR');

        if ($isSpr) {
            $lemburCount = 0;
        }

        $totalLemburJam = ($lmCount + $lemburCount) / 60;
        $hourlyBase = $gajiPokok + $tjMasaKerja + $tunjangan;

        if ($hourlyBase > 0 && $totalLemburJam > 0) {
            $upahLembur = ceil(($hourlyBase / 173) * $totalLemburJam / 100) * 100;
        } else {
            $upahLembur = 0;
        }

        $record->upah_lembur = $upahLembur;

        // ── Recalculate gaji_kotor ──
        $record->gaji_kotor = round(
            (float) $record->gaji
            + (float) $record->tj_masa_kerja
            + (float) $record->upah_lembur
            + (float) $record->revisi
            + (float) $record->premi_hadir
            + (float) $record->tunjangan,
            2
        );

        // ── Recalculate gaji_bersih ──
        $beforeRounding = (float) $record->gaji_kotor
            - (float) $record->bpjs_tk
            - (float) $record->bpjs_ks
            - (float) $record->bpjs_pen
            - (float) $record->cashbon
            - (float) $record->pph;

        $rounded = ceil($beforeRounding / 100) * 100;
        $record->pblt = round($rounded - $beforeRounding, 2);
        $record->gaji_bersih = $rounded;

        $record->save();

        return response()->json([
            'message' => 'Data lembur berhasil diupdate.',
            'data' => [
                'id'           => $record->id,
                'lm'           => (int) $record->lm,
                'lm_count'     => (int) $record->lm_count,
                'lembur_count' => (int) $record->lembur_count,
                'cashbon'      => (float) $record->cashbon,
                'upah_lembur'  => (float) $record->upah_lembur,
                'pblt'         => (float) $record->pblt,
                'total'        => (float) $record->gaji_kotor,
                'gaji_bersih'  => (float) $record->gaji_bersih,
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

        $query = PayRecord::with(['employee.department', 'employee.position', 'employee.groups'])
            ->where('pay_period_id', $period->id)
            ->join('employees', 'pay_records.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('pay_records.*');

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        $records = $query->get()->map(function ($record) use ($period) {
            $emp = $record->employee;
            $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            // Masa kerja: selisih bulan dari join_date ke end_date periode (dibulatkan ke bawah)
            $masaKerja = 0;
            if ($joinDate && $period->end_date) {
                $masaKerja = (int) floor($joinDate->diffInMonths(Carbon::parse($period->end_date)));
            }

            return [
                'employee_code' => $emp?->employee_code ?? $emp?->nip ?? '-',
                'name' => $emp?->name ?? '-',
                'department' => $emp?->department?->name ?? '-',
                'position' => $emp?->position?->name ?? '-',
                'gender' => $emp?->gender ?? '-',
                'join_year' => $joinDate ? $joinDate->format('d-M-Y') : '-',
                'masa_kerja' => $masaKerja,
                'ptkp' => $emp?->ptkp ?? '-',
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

        // Group by section from payroll config
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

        // ── Tambahan: Karyawan tambahan (ExtraEmployee) masuk ke Section A ──
        $extraEmployees = ExtraEmployee::all();
        foreach ($extraEmployees as $emp) {
            $g = $emp->komponen_gaji ?? [];
            $secAData[] = [
                'employee_code' => $emp->kode ?? '-',
                'name'          => $emp->nama ?? '-',
                'department'    => '-',
                'position'      => '-',
                'gender'        => $emp->gender ?? '-',
                'join_year'     => '-',
                'masa_kerja'    => 0,
                'ptkp'          => $emp->status_ptkp ?? '-',
                'premi'         => (float) ($g['premi'] ?? 0),
                'gaji_pokok'    => (float) ($g['gaji_pokok'] ?? 0),
                'tj_masa_kerja' => (float) ($g['tj_mk'] ?? 0),
                'tunjangan'     => (float) ($g['tunjangan'] ?? 0),
                'hari_kerja'    => 0,
                'lm'            => 0,
                'lembur_count'  => 0,
                'gaji'          => 0,
                'upah_lembur'   => 0,
                'revisi'        => 0,
                'premi_hadir'   => 0,
                'pblt'          => 0,
                'total'         => (float) ($g['total_gaji'] ?? 0),
                'bpjs_tk'       => 0,
                'bpjs_ks'       => 0,
                'bpjs_pen'      => 0,
                'cashbon'       => (float) ($g['cashbon'] ?? 0),
                'pph'           => (float) ($g['ttl_pph'] ?? 0),
                'gaji_bersih'   => (float) ($g['total_terima'] ?? 0),
            ];
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        $filename = 'Laporan_Gaji_Karyawan_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Reports\Exports\GajiKaryawanExport($secAData, $secBData, $periodName),
            $filename
        );
    }

    /**
     * Export Kirim ALL (multi-sheet Excel: A=ALLIN, B=PRINT).
     * GET /api/v1/payroll/gaji-karyawan/export-kirim-all?period_id=...&segment=...
     */
    public function exportKirimAll(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment' => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = PayRecord::with(['employee.groups'])
            ->where('pay_period_id', $period->id)
            ->join('employees', 'pay_records.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('pay_records.*');

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        $records = $query->get()->map(function ($record) {
            $emp = $record->employee;
            return [
                'id' => $record->id,
                'name' => $emp?->name ?? '-',
                'bank_name' => $emp?->bank_name ?? '-',
                'bank_account_number' => $emp?->bank_account_number ?? '-',
                'bank_account_name' => $emp?->bank_account_name ?? '-',
                'bank_cabang' => $emp?->bank_cabang ?? '',
                'gaji_bersih' => (float) $record->gaji_bersih,
                'notes' => $record->notes ?? '',
                'groups' => $emp?->groups?->pluck('reference_code')->toArray() ?? [],
            ];
        });

        // Group by section from payroll config
        $payrollConfig = PayrollConfig::getConfig('gaji_karyawan');
        $sectionA = $payrollConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];
        $sectionB = $payrollConfig['sections']['B'] ?? ['GRP-GD', 'GRP-SS', 'GRP-PS1'];

        $dataAllIn = [];
        $dataPrint = [];

        foreach ($records as $r) {
            $groups = $r['groups'] ?? [];
            if (array_intersect($groups, $sectionA)) {
                $dataAllIn[] = $r;
            } elseif (array_intersect($groups, $sectionB)) {
                $dataPrint[] = $r;
            }
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        $filename = 'Kirim_ALL_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new KirimAllExport($dataAllIn, $dataPrint, $period->tanggal_penggajian?->format('Y-m-d')),
            $filename
        );
    }
}
