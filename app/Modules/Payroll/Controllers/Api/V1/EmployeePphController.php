<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Payroll\Models\EmployeePph;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\PphConfig;
use App\Modules\Settings\Models\PtkpRate;
use App\Modules\Settings\Models\TerRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mengelola data PPh 21 per karyawan per periode.
 *
 * Alur:
 *   1. GET  /api/v1/payroll/pph/data      → list data PPh untuk suatu periode
 *   2. POST /api/v1/payroll/pph/generate   → generate PPh dari sumber data (salary, bpjs, attendance)
 *   3. PUT  /api/v1/payroll/pph/data/{id}  → edit manual satu record
 */
class EmployeePphController extends Controller
{
    // Group yang mendapat upah lembur 0 (sama dengan payroll)
    private const ZERO_OVERTIME_GROUPS = ['GRP-ALLIN', 'GRP-GD'];

    /** Group payroll yang eligible */
    private const GAJI_GROUPS = ['GRP-ALLIN', 'GRP-PS1', 'GRP-GD', 'GRP-SS', 'GRP-SPR'];

    // ──────────────────────────────────────────────
    //  Index
    // ──────────────────────────────────────────────

    /**
     * GET /api/v1/payroll/pph/data?period_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $periodId = $request->input('period_id');
        if (! $periodId) {
            return response()->json(['message' => 'period_id diperlukan'], 422);
        }

        $period = PayPeriod::find($periodId);
        if (! $period) {
            return response()->json(['message' => 'Periode tidak ditemukan'], 404);
        }

        $records = EmployeePph::with(['employee'])
            ->where('pay_period_id', $periodId)
            ->get()
            ->map(fn ($r) => $this->mapRecord($r));

        $stats = [
            'total_karyawan'    => $records->count(),
            'total_dtp'         => $records->where('is_dtp', true)->count(),
            'total_non_npwp'    => $records->where('has_npwp', false)->count(),
            'total_pph'         => $records->sum('pph_amount'),
            'total_pph_deducted'=> $records->sum('pph_deducted'),
        ];

        return response()->json([
            'period'  => ['id' => $period->id, 'name' => $period->name],
            'data'    => $records,
            'stats'   => $stats,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Generate
    // ──────────────────────────────────────────────

    /**
     * POST /api/v1/payroll/pph/generate
     *
     * Generate employee_pph untuk semua karyawan group gaji di periode ini.
     * Sumber data komponen gaji dibaca via accessor Employee (gaji_pokok,
     * premi, tunjangan, tunjangan_masa_kerja) yang mengambil dari
     * employee_salaries (sama seperti payroll utama), plus employee_bpjs
     * dan att_prepares. Tidak bergantung pada pay_records.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'force'     => 'nullable|boolean',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);

        // Ambil konfigurasi PPh aktif
        $config = PphConfig::where('is_active', true)->first();
        if (! $config) {
            return response()->json(['message' => 'Konfigurasi PPh belum diatur. Buka /admin/payroll/pph dulu.'], 422);
        }

        $segmentMonth = $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT);

        // Karyawan eligible: punya roster shift di periode terpilih,
        // bukan group GRP-JKT, dan memiliki NPWP terisi
        $rosteredIds = EmployeeShiftRoster::whereBetween('date', [
            $period->start_date,
            $period->end_date,
        ])->distinct()->pluck('employee_id');

        $employees = Employee::with(['groups', 'bpjs'])
            ->whereIn('id', $rosteredIds)
            ->whereNotNull('npwp')
            ->get()
            ->filter(fn ($emp) => ! $emp->groups->contains('reference_code', 'GRP-JKT'))
            ->values();

        if ($employees->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada karyawan yang memiliki roster shift di periode ini, bukan group GRP-JKT, dan memiliki NPWP. Gunakan halaman Pajak Karyawan untuk setup NPWP & PTKP tiap karyawan.',
            ], 422);
        }

        // Attendance aggregates
        $startDate = $period->start_date->toDateString();
        $endDate   = $period->end_date->toDateString();

        $force     = (bool) ($validated['force'] ?? false);
        $generated = 0;
        $skipped   = 0;
        $replaced  = 0;
        $errors    = [];

        foreach ($employees as $employee) {
            try {
                $existing = EmployeePph::where('employee_id', $employee->id)
                    ->where('pay_period_id', $period->id)
                    ->first();

                if ($existing) {
                    if ($force) {
                        $existing->delete();
                    } else {
                        $skipped++;
                        continue;
                    }
                }

                // ── Sumber data: salary (tiap komponen via accessor Employee) ──
                $gajiPokok = $employee->gaji_pokok($segmentMonth);
                $premi     = $employee->premi($segmentMonth);
                $tunjangan = $employee->tunjangan($segmentMonth);

                // ── Sumber data: BPJS per periode ──
                $bpjs = EmployeeBpjs::where('employee_id', $employee->id)
                    ->where('pay_period_id', $period->id)
                    ->first()
                    ?? $employee->bpjs; // fallback ke latest

                $jkk           = (float) ($bpjs?->employer_jkk ?? 0);
                $jkm           = (float) ($bpjs?->employer_jkm ?? 0);
                $bpjsKesEmploy = (float) ($bpjs?->employer_kesehatan ?? 0);
                $jhtKaryawan   = (float) ($bpjs?->employee_jht ?? 0);
                $jpKaryawan    = (float) ($bpjs?->employee_jp ?? 0);
                $bpjsKesKary   = (float) ($bpjs?->employee_kesehatan ?? 0);

                // ── Sumber data: attendance (untuk lembur) ──
                $prepares = AttendancePrepare::where('employee_id', $employee->id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $lm          = $prepares->sum('lm');
                $lmCount     = $prepares->sum('lm_count');
                $lemburCount = $prepares->sum('overtime_count');

                $isZeroOvertime = $employee->groups()
                    ->whereIn('reference_code', self::ZERO_OVERTIME_GROUPS)
                    ->exists();

                if ($isZeroOvertime) {
                    $upahLembur = 0;
                } else {
                    $isSpr = $employee->hasGroup('GRP-SPR');
                    if ($isSpr) {
                        $lemburCount = 0;
                        $totalLemburJam = $lmCount / 60;
                    } else {
                        $totalLemburJam = ($lmCount + $lemburCount) / 60;
                    }

                    $tjMasaKerja = $employee->tunjangan_masa_kerja($segmentMonth);
                    $hourlyBase  = $gajiPokok + $tjMasaKerja + $tunjangan;
                    $upahLembur  = $totalLemburJam > 0 && $hourlyBase > 0
                        ? ceil(($hourlyBase / 173) * $totalLemburJam / 100) * 100
                        : 0;
                }

                $lemburBonusThr = $upahLembur;

                // ── Hitung PPh ──
                // Penghasilan bruto = gaji pokok + premi + tunjangan + lembur/bonus/THR + BPJS perusahaan
                $grossIncome = $gajiPokok + $premi + $tunjangan + $lemburBonusThr
                    + $jkk + $jkm + $bpjsKesEmploy;

                // Biaya jabatan: 5% × bruto, max 500.000/bulan
                $biayaJabatan = min(500000, round($grossIncome * 0.05, 2));

                // Pengurang
                $totalPengurang = $biayaJabatan + $jhtKaryawan + $jpKaryawan + $bpjsKesKary;
                $nettoIncome    = max(0, $grossIncome - $totalPengurang);

                // TER lookup
                $ptkpStatus = $employee->ptkp ?? 'TK/0';
                $hasNpwp    = (bool) ($employee->has_npwp ?? $config->nik_as_npwp);
                $terCategory = $this->getTerCategory($ptkpStatus);
                $terRate     = $this->getTerRate($terCategory, $grossIncome);

                // PPh bulanan
                $pphAmount = round($grossIncome * ($terRate / 100), 2);

                // Non-NPWP penalty
                if (! $hasNpwp && $config->non_npwp_penalty) {
                    $pphAmount = round($pphAmount * (float) $config->non_npwp_multiplier, 2);
                }

                // DTP global
                $isDtp  = (bool) $config->is_dtp;
                $pphDeducted = $isDtp ? 0 : $pphAmount;

                // Simpan
                EmployeePph::create([
                    'uuid'                => (string) Str::uuid(),
                    'employee_id'         => $employee->id,
                    'pay_period_id'       => $period->id,
                    'pay_record_id'       => null,
                    'source_salary_id'    => $employee->activeSalary($segmentMonth)?->id,
                    'source_bpjs_id'      => $bpjs?->id,

                    'npwp'                => $employee->npwp ?? null,
                    'nik'                 => $employee->nik ?? null,
                    'has_npwp'            => $hasNpwp,
                    'ptkp_status'         => $ptkpStatus,

                    'gaji_pokok'          => $gajiPokok,
                    'premi'               => $premi,
                    'tunjangan'           => $tunjangan,
                    'lembur_bonus_thr'    => $lemburBonusThr,
                    'bpjs_jkk_perusahaan' => $jkk,
                    'bpjs_jkm_perusahaan' => $jkm,
                    'bpjs_kes_perusahaan' => $bpjsKesEmploy,
                    'gross_income'        => $grossIncome,

                    'biaya_jabatan'       => $biayaJabatan,
                    'bpjs_jht_karyawan'   => $jhtKaryawan,
                    'bpjs_jp_karyawan'    => $jpKaryawan,
                    'bpjs_kes_karyawan'   => $bpjsKesKary,
                    'total_pengurang'     => $totalPengurang,

                    'calculation_method'  => $config->calculation_method,
                    'pph_method'          => $config->pph_method,
                    'netto_income'        => $nettoIncome,
                    'annualized_income'   => round($nettoIncome * 12, 2),
                    'pkp'                 => 0, // dihitung tahunan di report
                    'pph_rate'            => $terRate,
                    'pph_amount'          => $pphAmount,
                    'pph_deducted'        => $pphDeducted,
                    'is_dtp'              => $isDtp,
                    'is_december_calc'    => false,
                    'pph_paid_until_nov'  => 0,

                    'created_by'          => auth()->id() ?? 1,
                ]);

                $generated++;
            } catch (\Throwable $e) {
                $errors[] = $employee->name . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'message'   => $force
                ? "PPh berhasil diregenerate untuk {$generated} karyawan"
                : "PPh berhasil digenerate untuk {$generated} karyawan"
                    . ($skipped > 0 ? ", {$skipped} dilewati (sudah ada)" : ''),
            'generated' => $generated,
            'skipped'   => $skipped,
            'errors'    => $errors,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Update
    // ──────────────────────────────────────────────

    /**
     * PUT /api/v1/payroll/pph/data/{id}
     *
     * Edit manual: koreksi nilai PPh, toggle DTP, ubah metode, dll.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $record = EmployeePph::findOrFail($id);

        $validated = $request->validate([
            'gaji_pokok'          => 'nullable|numeric|min:0',
            'premi'               => 'nullable|numeric|min:0',
            'tunjangan'           => 'nullable|numeric|min:0',
            'lembur_bonus_thr'    => 'nullable|numeric|min:0',
            'gross_income'        => 'nullable|numeric|min:0',
            'biaya_jabatan'       => 'nullable|numeric|min:0',
            'bpjs_jht_karyawan'   => 'nullable|numeric|min:0',
            'bpjs_jp_karyawan'    => 'nullable|numeric|min:0',
            'bpjs_kes_karyawan'   => 'nullable|numeric|min:0',
            'total_pengurang'     => 'nullable|numeric|min:0',
            'netto_income'        => 'nullable|numeric|min:0',
            'ptkp_status'         => 'nullable|string|max:10',
            'has_npwp'            => 'nullable|boolean',
            'pph_rate'            => 'nullable|numeric|min:0',
            'pph_amount'          => 'nullable|numeric|min:0',
            'pph_deducted'        => 'nullable|numeric|min:0',
            'is_dtp'              => 'nullable|boolean',
            'calculation_method'  => 'nullable|in:ter,progressive',
            'pph_method'          => 'nullable|in:gross,gross_up,net',
        ]);

        $record->update($validated);

        return response()->json([
            'message' => 'Data PPh berhasil diupdate',
            'data'    => $this->mapRecord($record->fresh('employee')),
        ]);
    }

    // ──────────────────────────────────────────────
    //  Delete
    // ──────────────────────────────────────────────

    /**
     * DELETE /api/v1/payroll/pph/data/{id}
     */
    public function destroy($id): JsonResponse
    {
        $record = EmployeePph::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Data PPh dihapus']);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function mapRecord($r): array
    {
        return [
            'id'               => $r->id,
            'employee_id'      => $r->employee_id,
            'employee_name'    => $r->employee?->name ?? '-',
            'employee_code'    => $r->employee?->employee_code ?? '-',
            'ptkp_status'      => $r->ptkp_status,
            'has_npwp'         => (bool) $r->has_npwp,
            'nik'              => $r->nik ?? $r->npwp,
            'gaji_pokok'       => (float) $r->gaji_pokok,
            'premi'            => (float) $r->premi,
            'tunjangan'        => (float) $r->tunjangan,
            'lembur_bonus_thr' => (float) $r->lembur_bonus_thr,
            'gross_income'     => (float) $r->gross_income,
            'biaya_jabatan'    => (float) $r->biaya_jabatan,
            'bpjs_jht_karyawan'=> (float) $r->bpjs_jht_karyawan,
            'bpjs_jp_karyawan' => (float) $r->bpjs_jp_karyawan,
            'bpjs_kes_karyawan'=> (float) $r->bpjs_kes_karyawan,
            'total_pengurang'  => (float) $r->total_pengurang,
            'netto_income'     => (float) $r->netto_income,
            'pph_rate'         => (float) $r->pph_rate,
            'pph_amount'       => (float) $r->pph_amount,
            'pph_deducted'     => (float) $r->pph_deducted,
            'is_dtp'           => (bool) $r->is_dtp,
            'calculation_method'=> $r->calculation_method,
        ];
    }

    /**
     * Kategori TER berdasarkan PTKP status.
     */
    private function getTerCategory(?string $ptkp): string
    {
        return match ($ptkp) {
            'TK/0', 'TK/1', 'K/0'      => 'A',
            'TK/2', 'TK/3', 'K/1', 'K/2' => 'B',
            'K/3'                       => 'C',
            default                     => 'A',
        };
    }

    /**
     * Ambil tarif TER dari database (ter_rates).
     * Fallback: return 0.
     */
    private function getTerRate(string $category, float $grossIncome): float
    {
        $rate = TerRate::where('category', $category)
            ->where('min_income', '<=', $grossIncome)
            ->where(function ($q) use ($grossIncome) {
                $q->where('max_income', '>=', $grossIncome)
                  ->orWhereNull('max_income');
            })
            ->orderBy('sort_order')
            ->first();

        return $rate ? (float) $rate->rate : 0;
    }
}
