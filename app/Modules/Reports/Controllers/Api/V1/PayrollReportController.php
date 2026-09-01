<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExtraEmployee;
use App\Modules\Attendance\Models\EmployeeOvertime;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Payroll\Models\PayrollConfig;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        // Build data untuk All-In
        $reqAllIn = clone $request;
        $reqAllIn->merge(['tab' => 'all-in']);
        $resultAllIn = $this->buildResumeData($reqAllIn);

        // Build data untuk Print
        $reqPrint = clone $request;
        $reqPrint->merge(['tab' => 'print']);
        $resultPrint = $this->buildResumeData($reqPrint);

        $periodName = $resultAllIn['period_name'] ?: $resultPrint['period_name'];
        $dataAllIn = ($resultAllIn['data'] ?? collect())->toArray();
        $dataPrint = ($resultPrint['data'] ?? collect())->toArray();

        $filename = 'Laporan_Resume_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Reports\Exports\PayrollResumeExport($dataAllIn, $dataPrint, $periodName),
            $filename
        );
    }

    /**
     * GET /api/v1/laporan/payroll/kirim-audit
     * Data untuk laporan Kirim Audit.
     *
     * NOMINAL = (pay_records.gaji_bersih - supervisor_breakdowns.gaji_bersih)
     *         + uangMakan + insentif
     */
    public function kirimAudit(Request $request)
    {
        [$data, $period, $segment] = $this->buildKirimAuditData($request);

        return response()->json([
            'data'   => $data,
            'period' => [
                'id'                  => $period->id,
                'name'                => $period->name,
                'is_split'            => $period->is_split,
                'segment'             => $segment,
                'tanggal_penggajian'  => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * GET /api/v1/laporan/payroll/kirim-audit/export
     * Export daftar transfer (Kirim Bank) — format persis seperti export Kirim ALL.
     */
    public function exportKirimBank(Request $request)
    {
        [$data, $period, $segment] = $this->buildKirimAuditData($request);

        $secAData = [];
        $secBData = [];
        foreach ($data as $item) {
            if (($item['section'] ?? null) === 'A') {
                $secAData[] = $item;
            } elseif (($item['section'] ?? null) === 'B') {
                $secBData[] = $item;
            }
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        $filename = 'Kirim_Bank_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Payroll\Exports\TransferGajiExport(
                $secAData,
                $secBData,
                $period->tanggal_penggajian?->format('Y-m-d')
            ),
            $filename
        );
    }

    /**
     * Bangun data untuk laporan Kirim Audit / Kirim Bank.
     * NOMINAL = (pay_records.gaji_bersih - supervisor_breakdowns.gaji_bersih)
     *         + uangMakan + insentif
     */
    private function buildKirimAuditData(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = SupervisorBreakdown::with(['employee.department', 'employee.position', 'employee.groups'])
            ->where('pay_period_id', $period->id)
            ->join('employees', 'supervisor_breakdowns.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('supervisor_breakdowns.*');

        if ($period->is_split) {
            if (!$segment) {
                $segment = 'A';
            }
            $query->where('segment', $segment);
        }

        $records = $query->get();

        // Batch query pay records: gaji_bersih & notes per employee
        $employeeIds = $records->pluck('employee_id')->unique()->values();
        $payRecordData = collect();
        if ($employeeIds->isNotEmpty()) {
            $payRecordData = PayRecord::where('pay_period_id', $period->id)
                ->whereIn('employee_id', $employeeIds->toArray())
                ->select('employee_id', 'gaji_bersih', 'notes')
                ->get()
                ->keyBy('employee_id');
        }

        // Batch query overtime: insentif & uangMakan per employee
        $overtimeData = collect();
        if ($employeeIds->isNotEmpty()) {
            $overtimeData = EmployeeOvertime::where('pay_periode_id', $period->id)
                ->whereIn('employee_id', $employeeIds->toArray())
                ->selectRaw('employee_id, SUM(insentif) as total_insentif, SUM(nominal) as total_nominal')
                ->groupBy('employee_id')
                ->get()
                ->keyBy('employee_id');
        }

        $data = $records->map(function ($record) use ($overtimeData, $payRecordData) {
            $emp       = $record->employee;
            $payRecord = $payRecordData->get($record->employee_id);
            $joinDate  = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            // NOMINAL = (gajiKus - gajiAudit) + uangMakan + insentif
            $gajiKus   = (float) ($payRecord?->gaji_bersih ?? 0);
            $gajiAudit = (float) $record->gaji_bersih;

            $overtimeRow = $overtimeData->get($record->employee_id);
            $insentif    = (float) ($overtimeRow->total_insentif ?? 0);

            // uangMakan hanya Section A (ALLIN), kecuali GRP-SPR
            $uangMakan  = 0;
            $groupCodes = $record->group_codes
                ?? $emp?->groups?->pluck('reference_code')->toArray()
                ?? [];
            if ($record->section === 'A' && !in_array('GRP-SPR', $groupCodes)) {
                $uangMakan = (float) ($overtimeRow->total_nominal ?? 0);
            }

            $nominal = ($gajiKus - $gajiAudit) + $uangMakan + $insentif;

            return [
                'id'                  => $record->id,
                'employee_id'         => $emp?->id,
                'employee_code'       => $emp?->nip ?? $record->employee_code ?? '-',
                'name'                => $record->employee_name ?? $emp?->name ?? '-',
                'department'          => $record->department_name ?? $emp?->department?->name ?? '-',
                'position'            => $record->position_name ?? $emp?->position?->name ?? '-',
                'gender'              => $record->gender ?? $emp?->gender ?? '-',
                'join_year'           => $joinDate ? $joinDate->format('d-M-Y') : '-',
                'groups'              => $groupCodes,
                'bank_name'           => $record->bank_name ?? $emp?->bank_name ?? '-',
                'bank_account_number' => $record->bank_account_number ?? $emp?->bank_account_number ?? '-',
                'bank_account_name'   => $record->bank_account_name ?? $emp?->bank_account_name ?? '-',
                'bank_cabang'         => $emp?->bank_cabang ?? '-',
                'notes'               => $payRecord?->notes ?? $record->notes ?? '',
                'section'             => $record->section,
                'gaji_pokok'    => (float) $record->gaji_pokok,
                'premi'         => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan'     => (float) $record->tunjangan,
                'hari_kerja'    => (int) $record->hari_kerja,
                'lm'            => round((float) $record->lm / 8, 1),
                'lm_count'      => (float) $record->lm_count,
                'lembur_count'  => (float) $record->lembur_count,
                'gaji'         => (float) $record->gaji,
                'upah_lembur'  => (float) $record->upah_lembur,
                'revisi'       => (float) $record->revisi,
                'premi_hadir'  => (float) $record->premi_hadir,
                'pblt'         => (float) $record->pblt,
                'total'        => (float) $record->gaji_kotor,
                'bpjs_tk'      => (float) $record->bpjs_tk,
                'bpjs_ks'      => (float) $record->bpjs_ks,
                'bpjs_pen'     => (float) $record->bpjs_pen,
                'cashbon'      => (float) $record->cashbon,
                'pph'          => (float) $record->pph,
                'gaji_bersih'  => $nominal,
            ];
        });

        // ── Tambahan: Karyawan tambahan (ExtraEmployee) masuk ke Section A ──
        $extraEmployees = ExtraEmployee::all();
        foreach ($extraEmployees as $emp) {
            $g = $emp->komponen_gaji ?? [];
            $data->push([
                'id'                  => 'ext-' . $emp->id,
                'employee_id'         => null,
                'employee_code'       => $emp->kode ?? '-',
                'name'                => $emp->nama ?? '-',
                'department'          => '-',
                'position'            => '-',
                'gender'              => $emp->gender ?? '-',
                'join_year'           => '-',
                'groups'              => [],
                'bank_name'           => '-',
                'bank_account_number' => $emp->account ?? '-',
                'bank_account_name'   => $emp->nama ?? '-',
                'bank_cabang'         => '-',
                'notes'               => '',
                'section'             => 'A',
                'gaji_pokok'    => (float) ($g['gaji_pokok'] ?? 0),
                'premi'         => (float) ($g['premi'] ?? 0),
                'tj_masa_kerja' => (float) ($g['tj_mk'] ?? 0),
                'tunjangan'     => (float) ($g['tunjangan'] ?? 0),
                'hari_kerja'    => 0,
                'lm'            => 0,
                'lm_count'      => 0,
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
            ]);
        }

        return [$data, $period, $segment];
    }

    // =====================================================================
    // KIRIM ALL — daftar transfer bank gabungan (All-In + Print)
    // GET /api/v1/laporan/payroll/kirim-all            → JSON (tab Kirim ALL)
    // GET /api/v1/laporan/payroll/kirim-all/export     → Excel (multi-sheet)
    //
    // Hanya baca pay_records TERSIMPAN. Builder dipakai bersama JSON & Excel
    // supaya angka di tabel dan di Excel tidak mungkin beda.
    // =====================================================================

    public function kirimAll(Request $request)
    {
        [$records, $period, $segment] = $this->buildKirimAllData($request);

        return response()->json([
            'data' => $records,
            'period' => [
                'id'                 => $period->id,
                'name'               => $period->name,
                'is_split'           => $period->is_split,
                'segment'            => $segment,
                'end_date'           => $period->end_date?->format('Y-m-d'),
                'tanggal_penggajian' => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
        ]);
    }

    public function exportKirimAll(Request $request)
    {
        [$records, $period, $segment] = $this->buildKirimAllData($request);

        $payrollConfig = PayrollConfig::getConfig('gaji_karyawan');
        $sectionA = $payrollConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];
        $sectionB = $payrollConfig['sections']['B'] ?? ['GRP-GD', 'GRP-SS', 'GRP-PS1'];

        $secAData = [];
        $secBData = [];
        foreach ($records as $r) {
            $groups = $r['groups'] ?? [];
            // GRP-EXTRA (karyawan titipan) selalu masuk Section A — sama dengan grouping di tab
            if (array_intersect($groups, $sectionA) || in_array('GRP-EXTRA', $groups)) {
                $secAData[] = $r;
            } elseif (array_intersect($groups, $sectionB)) {
                $secBData[] = $r;
            }
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        $filename = 'Kirim_ALL_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Payroll\Exports\KirimAllExport(
                $secAData,
                $secBData,
                $period->tanggal_penggajian?->format('Y-m-d')
            ),
            $filename
        );
    }

    /** Bangun daftar transfer Kirim ALL (JSON & Excel pakai builder yang sama). */
    private function buildKirimAllData(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
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
                'id'                  => $record->id,
                'employee_id'         => $emp?->id,
                'employee_code'       => $emp?->employee_code ?? $emp?->nip ?? '-',
                'name'                => $emp?->name ?? '-',
                'bank_name'           => $emp?->bank_name ?? '-',
                'bank_account_number' => $emp?->bank_account_number ?? '-',
                'bank_account_name'   => $emp?->bank_account_name ?? '-',
                'bank_cabang'         => $emp?->bank_cabang ?? '',
                'gaji_bersih'         => (float) $record->gaji_bersih,
                'notes'               => $record->notes ?? '',
                'groups'              => $emp?->groups?->pluck('reference_code')->toArray() ?? [],
            ];
        });

        // ── Karyawan tambahan (ExtraEmployee) masuk ke All-In ──
        $extraEmployees = ExtraEmployee::all();
        foreach ($extraEmployees as $emp) {
            $g = $emp->komponen_gaji ?? [];
            $records->push([
                'id'                  => 'ext-' . $emp->id,
                'employee_id'         => $emp->id,
                'employee_code'       => $emp->kode ?? '-',
                'name'                => $emp->nama ?? '-',
                'bank_name'           => '-',
                'bank_account_number' => $emp->account ?? '-',
                'bank_account_name'   => $emp->nama ?? '-',
                'bank_cabang'         => '-',
                'gaji_bersih'         => (float) ($g['total_terima'] ?? 0),
                'notes'               => '',
                'groups'              => ['GRP-EXTRA'],
            ]);
        }

        return [$records, $period, $segment];
    }

    private function buildPayrollData(Request $request)
    {
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        
        // Tab All In: group code GRP-ALLIN, GRP-SPR
        // Karyawan Bulanan Print: GRP-SS, GRP-PS1, GRP-GD
        $tab = $request->input('tab', 'all-in'); // 'all-in' or 'print'

        $groupCodes = $tab === 'all-in'
            ? ['GRP-ALLIN', 'GRP-SPR']
            : ['GRP-SS', 'GRP-PS1', 'GRP-GD'];

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
                'gender' => $emp->gender,
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
            ? ['GRP-ALLIN', 'GRP-SPR']
            : ['GRP-SS', 'GRP-PS1', 'GRP-GD'];

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
            $maleCount = $groupRecords->filter(fn($r) => $r->employee->gender === 'L')->count();
            $femaleCount = $groupRecords->filter(fn($r) => $r->employee->gender === 'P')->count();

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

        // ── Tambahan: Karyawan tambahan (ExtraEmployee) masuk ke tab all-in (Section A) ──
        if ($tab === 'all-in') {
            $extras = ExtraEmployee::all();
            if ($extras->isNotEmpty()) {
                $maleCount = 0;
                $femaleCount = 0;
                $sumTjMk = 0;
                $sumTunjangan = 0;
                $sumTotalGaji = 0;
                $sumCashbon = 0;
                $sumPph = 0;
                $sumTotalTerima = 0;

                foreach ($extras as $emp) {
                    $g = $emp->komponen_gaji ?? [];
                    if ($emp->gender === 'male' || $emp->gender === 'L') {
                        $maleCount++;
                    } else {
                        $femaleCount++;
                    }
                    $sumTjMk        += (float) ($g['tj_mk'] ?? 0);
                    $sumTunjangan   += (float) ($g['tunjangan'] ?? 0);
                    $sumTotalGaji   += (float) ($g['total_gaji'] ?? 0);
                    $sumCashbon     += (float) ($g['cashbon'] ?? 0);
                    $sumPph         += (float) ($g['ttl_pph'] ?? 0);
                    $sumTotalTerima += (float) ($g['total_terima'] ?? 0);
                }

                $data[] = [
                    'bagian'             => 'Karyawan Tambahan',
                    'jml_karyawan_l'     => $maleCount,
                    'jml_karyawan_p'     => $femaleCount,
                    'jml_karyawan_total' => $maleCount + $femaleCount,
                    'gaji'               => 0,
                    'lembur'             => 0,
                    'revisi'             => 0,
                    'tj_masa_kerja'      => $sumTjMk,
                    'tunjangan'          => $sumTunjangan,
                    'premi_hadir'        => 0,
                    'pblt'               => 0,
                    'total'              => $sumTotalGaji,
                    'bpjs_tk'            => 0,
                    'bpjs_ks'            => 0,
                    'bpjs_pen'           => 0,
                    'cashbon'            => $sumCashbon,
                    'revisi_pph'         => $sumPph,
                    'total_terima'       => $sumTotalTerima,
                ];
            }
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

    // =====================================================================
    // LAPORAN PAYROLL (tab Payroll) — dipisah dari GajiKaryawanController
    // GET /api/v1/laporan/payroll/laporan-payroll  &  /laporan-payroll/export
    //
    // Hanya baca data TERSIMPAN (pay_records). Tidak ada mode on-the-fly /
    // status lifecycle — itu domain halaman pengelolaan (gaji-karyawan).
    // =====================================================================

    public function laporanPayroll(Request $request)
    {
        [$records, $period, $segment] = $this->buildLaporanPayrollData($request);

        return response()->json([
            'data' => $records,
            'period' => [
                'id'                 => $period->id,
                'name'               => $period->name,
                'is_split'           => $period->is_split,
                'segment'            => $segment,
                'end_date'           => $period->end_date?->format('Y-m-d'),
                'tanggal_penggajian' => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
        ]);
    }

    public function exportLaporanPayroll(Request $request)
    {
        [$records, $period, $segment] = $this->buildLaporanPayrollExportData($request);

        $payrollConfig = PayrollConfig::getConfig('gaji_karyawan');
        $sectionA = $payrollConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];
        $sectionB = $payrollConfig['sections']['B'] ?? ['GRP-GD', 'GRP-SS', 'GRP-PS1'];

        $secAData = [];
        $secBData = [];

        foreach ($records as $r) {
            $groups = $r['groups'] ?? [];
            // GRP-EXTRA (karyawan titipan) selalu masuk Section A — sama dengan grouping di tab
            if (array_intersect($groups, $sectionA) || in_array('GRP-EXTRA', $groups)) {
                $secAData[] = $r;
            } elseif (array_intersect($groups, $sectionB)) {
                $secBData[] = $r;
            }
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

    /** Data rekap tab Payroll (JSON) — shape sama dengan GajiKaryawanController@index (on_record). */
    private function buildLaporanPayrollData(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
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
            $query->where('segment', $segment ?? 'A');
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
                'gaji_pokok' => (float) $record->gaji_pokok,
                'premi' => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan' => (float) $record->tunjangan,
                'hari_kerja' => (int) $record->hari_kerja,
                'lm' => (int) $record->lm,
                'lm_count' => (int) $record->lm_count,
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

        // ExtraEmployee masuk (sama seperti index on_record) → konsisten utk summary/grand total
        $records = $this->appendExtraEmployeeRecords($records);

        return [$records, $period, $segment];
    }

    /** Data export tab Payroll (Excel) — shape sama dengan GajiKaryawanController@export. */
    private function buildLaporanPayrollExportData(Request $request): array
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = PayRecord::with(['employee.department', 'employee.position', 'employee.groups', 'employee.latestContract'])
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
            $periodStart = $period->start_date ? Carbon::parse($period->start_date) : null;
            $periodEnd = $period->end_date ? Carbon::parse($period->end_date) : null;

            // Masa kerja (bulan, dibulatkan ke bawah):
            // 1) join_date SESUDAH start_date periode & kontrak NON-freelance berakhir sebelum end_date periode
            //    → dihitung dari origin_join_date (tanggal masuk asal / sistem lama).
            // 2) join_date SESUDAH start_date periode & kontrak FREELANCE berakhir sebelum end_date periode
            //    → masa_kerja = 0.
            // 3) else → hitungan lama: join_date → end_date periode.
            $masaKerja = 0;
            if ($joinDate && $periodEnd) {
                $contract = $emp?->latestContract;
                $joinedAfterPeriodStart = $periodStart && $joinDate->gt($periodStart);
                $contractEndedBeforePeriodEnd = $contract?->end_date && $periodEnd && $contract->end_date->lt($periodEnd);

                if ($joinedAfterPeriodStart && $contractEndedBeforePeriodEnd) {
                    if ($contract->contract_type === 'freelance') {
                        // Kondisi 2 — kontrak freelance yang sudah berakhir
                        $masaKerja = 0;
                    } else {
                        // Kondisi 1 — fallback ke join_date bila origin_join_date kosong
                        $originDate = $emp?->origin_join_date ? Carbon::parse($emp->origin_join_date) : $joinDate;
                        $masaKerja = (int) floor($originDate->diffInMonths($periodEnd));
                    }
                } else {
                    // Kondisi 3 — hitungan sekarang
                    $masaKerja = (int) floor($joinDate->diffInMonths($periodEnd));
                }
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

        $records = $this->appendExtraEmployeeExportRecords($records);

        return [$records, $period, $segment];
    }

    /** Tambah ExtraEmployee ke koleksi (shape JSON — summary/grand total). */
    private function appendExtraEmployeeRecords(Collection $records): Collection
    {
        $extraEmployees = ExtraEmployee::all();
        foreach ($extraEmployees as $emp) {
            $g = $emp->komponen_gaji ?? [];
            $records->push([
                'id'                  => 'ext-' . $emp->id,
                'employee_id'         => $emp->id,
                'employee_code'       => $emp->kode ?? '-',
                'name'                => $emp->nama ?? '-',
                'department'          => '-',
                'position'            => '-',
                'gender'              => $emp->gender ?? '-',
                'join_year'           => '-',
                'groups'              => ['GRP-EXTRA'],
                'bank_name'           => '-',
                'bank_account_number' => $emp->account ?? '-',
                'bank_account_name'   => $emp->nama ?? '-',
                'bank_cabang'         => '-',
                'notes'               => '',
                'gaji_pokok'          => (float) ($g['gaji_pokok'] ?? 0),
                'premi'               => (float) ($g['premi'] ?? 0),
                'tj_masa_kerja'       => (float) ($g['tj_mk'] ?? 0),
                'tunjangan'           => (float) ($g['tunjangan'] ?? 0),
                'hari_kerja'          => 0,
                'lm'                  => 0,
                'lm_count'            => 0,
                'lembur_count'        => 0,
                'gaji'                => 0,
                'upah_lembur'         => 0,
                'revisi'              => 0,
                'premi_hadir'         => 0,
                'pblt'                => 0,
                'total'               => (float) ($g['total_gaji'] ?? 0),
                'bpjs_tk'             => 0,
                'bpjs_ks'             => 0,
                'bpjs_pen'            => 0,
                'cashbon'             => (float) ($g['cashbon'] ?? 0),
                'pph'                 => (float) ($g['ttl_pph'] ?? 0),
                'gaji_bersih'         => (float) ($g['total_terima'] ?? 0),
            ]);
        }

        return $records;
    }

    /** Tambah ExtraEmployee ke koleksi (shape export Excel — Section A). */
    private function appendExtraEmployeeExportRecords(Collection $records): Collection
    {
        $extraEmployees = ExtraEmployee::all();
        foreach ($extraEmployees as $emp) {
            $g = $emp->komponen_gaji ?? [];
            $records->push([
                'employee_code'       => $emp->kode ?? '-',
                'name'                => $emp->nama ?? '-',
                'department'          => '-',
                'position'            => '-',
                'gender'              => $emp->gender ?? '-',
                'join_year'           => '-',
                'masa_kerja'          => 0,
                'ptkp'                => $emp->status_ptkp ?? '-',
                'groups'              => ['GRP-EXTRA'],
                'bank_name'           => '-',
                'bank_account_number' => $emp->account ?? '-',
                'bank_account_name'   => $emp->nama ?? '-',
                'gaji_pokok'          => (float) ($g['gaji_pokok'] ?? 0),
                'premi'               => (float) ($g['premi'] ?? 0),
                'tj_masa_kerja'       => (float) ($g['tj_mk'] ?? 0),
                'tunjangan'           => (float) ($g['tunjangan'] ?? 0),
                'hari_kerja'          => 0,
                'lm'                  => 0,
                'lembur_count'        => 0,
                'gaji'                => 0,
                'upah_lembur'         => 0,
                'revisi'              => 0,
                'premi_hadir'         => 0,
                'pblt'                => 0,
                'total'               => (float) ($g['total_gaji'] ?? 0),
                'bpjs_tk'             => 0,
                'bpjs_ks'             => 0,
                'bpjs_pen'            => 0,
                'cashbon'             => (float) ($g['cashbon'] ?? 0),
                'pph'                 => (float) ($g['ttl_pph'] ?? 0),
                'gaji_bersih'         => (float) ($g['total_terima'] ?? 0),
            ]);
        }

        return $records;
    }
}
