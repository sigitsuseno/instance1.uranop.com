<?php

namespace App\Modules\Supervisor\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayrollConfig;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendanceSnapshot;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use App\Modules\Payroll\Exports\TransferGajiExport;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SupervisorBreakdownController extends Controller
{
    /**
     * GET /api/v1/supervisor/payroll/breakdown
     * Tampilkan data breakdown gaji per periode.
     */
    public function index(Request $request)
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

        $records = $query->get()->map(function ($record) {
            $emp = $record->employee;
            $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

            return [
                'id'                  => $record->id,
                'employee_code'       => $emp?->nip ?? $record->employee_code ?? $emp?->employee_code ?? '-',
                'name'                => $record->employee_name ?? $emp?->name ?? '-',
                'department'          => $record->department_name ?? $emp?->department?->name ?? '-',
                'position'            => $record->position_name ?? $emp?->position?->name ?? '-',
                'gender'              => $record->gender ?? $emp?->gender ?? '-',
                'join_year'           => $joinDate ? $joinDate->format('d-M-Y') : '-',
                'groups'              => $record->group_codes ?? $emp?->groups?->pluck('reference_code')->toArray() ?? [],
                'bank_name'           => $record->bank_name ?? $emp?->bank_name ?? '-',
                'bank_account_number' => $record->bank_account_number ?? $emp?->bank_account_number ?? '-',
                'bank_account_name'   => $record->bank_account_name ?? $emp?->bank_account_name ?? '-',
                'bank_cabang'         => $emp?->bank_cabang ?? '-',
                'notes'               => $record->notes ?? '',
                // Masukan
                'gaji_pokok'    => (float) $record->gaji_pokok,
                'premi'         => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan'     => (float) $record->tunjangan,
                'hari_kerja'    => (int) $record->hari_kerja,
                'lm'            => round((float) $record->lm / 8, 1),
                'lm_count'      => (float) $record->lm_count,
                'lembur_count'  => (float) $record->lembur_count,
                // Hasil
                'gaji'         => (float) $record->gaji,
                'upah_lembur'  => (float) $record->upah_lembur,
                'revisi'       => (float) $record->revisi,
                'premi_hadir'  => (float) $record->premi_hadir,
                'pblt'         => (float) $record->pblt,
                'total'        => (float) $record->gaji_kotor,
                // Potongan
                'bpjs_tk'      => (float) $record->bpjs_tk,
                'bpjs_ks'      => (float) $record->bpjs_ks,
                'bpjs_pen'     => (float) $record->bpjs_pen,
                'cashbon'      => (float) $record->cashbon,
                'pph'          => (float) $record->pph,
                'gaji_bersih'  => (float) $record->gaji_bersih,
                // Meta
                'section' => $record->section,
            ];
        });

        return response()->json([
            'data'   => $records,
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
     * GET /api/v1/supervisor/payroll/breakdown/export-kirim-audit
     * Export daftar transfer (Kirim Audit) — format persis seperti export Kirim ALL.
     */
    public function exportKirimAudit(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = SupervisorBreakdown::with(['employee'])
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

        $secAData = [];
        $secBData = [];

        foreach ($records as $r) {
            $emp = $r->employee;
            $item = [
                'name'                => $r->employee_name ?? $emp?->name ?? '-',
                'bank_name'           => $r->bank_name ?? $emp?->bank_name ?? '-',
                'bank_account_number' => $r->bank_account_number ?? $emp?->bank_account_number ?? '-',
                'bank_account_name'   => $r->bank_account_name ?? $emp?->bank_account_name ?? '-',
                'bank_cabang'         => $emp?->bank_cabang ?? '-',
                'gaji_bersih'         => (float) $r->gaji_bersih,
                'notes'               => $r->notes ?? '',
            ];

            if ($r->section === 'A') {
                $secAData[] = $item;
            } elseif ($r->section === 'B') {
                $secBData[] = $item;
            }
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }

        $filename = 'Kirim_Audit_' . str_replace(' ', '_', $periodName) . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new TransferGajiExport($secAData, $secBData, $period->tanggal_penggajian?->format('Y-m-d')),
            $filename
        );
    }

    // ═══════════════════════════════════════════════════════════
    // PROCESS A: Kalkulasi dari Supervisor Att Snapshot
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/v1/supervisor/payroll/breakdown/calculate
     * Generate/kalkulasi ulang supervisor_breakdowns dari supervisor_att_snapshot.
     * Logika mengikuti admin recapApprove().
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $startDate = $period->start_date;
        $endDate = $period->end_date;

        // ── Ambil daftar karyawan dari supervisor_employee_groups ──
        $groupEmployeeIds = SupervisorEmployeeGroup::where('period_start', $startDate)
            ->where('period_end', $endDate)
            ->pluck('employee_id')
            ->unique()
            ->values();

        if ($groupEmployeeIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada karyawan di supervisor employee group untuk periode ini.',
            ], 400);
        }

        // ── Ambil snapshot attendance ──
        $snapshots = SupervisorAttendanceSnapshot::where('pay_period_id', $period->id)
            ->whereIn('employee_id', $groupEmployeeIds)
            ->with('employee.groups', 'employee.department', 'employee.position', 'employee.bpjs')
            ->get();

        if ($snapshots->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data snapshot untuk periode ini. Generate snapshot terlebih dahulu.',
            ], 400);
        }

        // ── Ambil payroll config ──
        $payrollSetting = SystemSetting::where('key', 'payroll_config')->first();
        if (!$payrollSetting || empty($payrollSetting->fixed_working_day)) {
            return response()->json([
                'success' => false,
                'message' => 'Hari kerja (Fixed Working Day) belum diatur di menu Pengaturan Penggajian.',
            ], 400);
        }
        $fixedDays = (int) $payrollSetting->fixed_working_day;

        // ── Data split days dari config ──
        $splitDays = json_decode($payrollSetting->value ?? '{}', true);

        // ── Group config (section A/B) ──
        $gajiConfig = PayrollConfig::getConfig('gaji_karyawan');

        // ── Holiday periode (buat hitung missing/pro-rata, konsisten dgn admin) ──
        $holidays = Holiday::whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $processed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($snapshots as $snapshot) {
                $employee = $snapshot->employee;
                if (!$employee) continue;

                // Filter: hanya karyawan yang isGroupGaji()
                if (!$employee->isGroupGaji()) continue;

                // ── Tentukan segmen ──
                if ($period->is_split) {
                    // Default: A=5, B=20 (25-5) — sama dgn admin GajiKaryawan
                    $hkA = (int) ($splitDays['A'] ?? 5);
                    $hkB = (int) ($splitDays['B'] ?? max(0, $fixedDays - $hkA));
                    $month1End = Carbon::parse($startDate)->endOfMonth()->toDateString();
                    $month2Start = Carbon::parse($endDate)->startOfMonth()->toDateString();

                    $segments = [
                        ['segment' => 'A', 'start' => $startDate, 'end' => $month1End, 'hk' => $hkA],
                        ['segment' => 'B', 'start' => $month2Start, 'end' => $endDate, 'hk' => $hkB],
                    ];
                } else {
                    $segments = [
                        ['segment' => null, 'start' => $startDate, 'end' => $endDate, 'hk' => $fixedDays],
                    ];
                }

                // ── SECTION mapping ──
                $groupCodes = $employee->groups->pluck('reference_code')->toArray();
                $section = null;
                if (array_intersect($groupCodes, $gajiConfig['sections']['A'] ?? [])) {
                    $section = 'A';
                } elseif (array_intersect($groupCodes, $gajiConfig['sections']['B'] ?? [])) {
                    $section = 'B';
                }

                foreach ($segments as $seg) {
                    $segCode   = $seg['segment'];
                    $segStart  = $seg['start'];
                    $segEnd    = $seg['end'];
                    $hkSegment = $seg['hk'];

                    // ── Query leave (izin/cuti/sakit) ──
                    $leaves = LeaveRequest::where('employee_id', $employee->id)
                        ->where('status', 'approved')
                        ->where(function ($q) use ($segStart, $segEnd) {
                            $q->whereBetween('start_date', [$segStart, $segEnd])
                              ->orWhereBetween('end_date', [$segStart, $segEnd])
                              ->orWhere(function ($q2) use ($segStart, $segEnd) {
                                  $q2->where('start_date', '<=', $segStart)
                                     ->where('end_date', '>=', $segEnd);
                              });
                        })
                        ->with('leaveType')
                        ->get();

                    $leaveIzin = 0;
                    foreach ($leaves as $l) {
                        $lStart = Carbon::parse(max($l->start_date->toDateString(), $segStart));
                        $lEnd   = Carbon::parse(min($l->end_date->toDateString(), $segEnd));
                        $dur    = max(0, $lStart->diffInDays($lEnd) + 1);

                        // Hanya leave dengan is_paid=false (unpaid) yang mengurangi hari kerja
                        $isPaid = optional($l->leaveType)->is_paid;
                        if ($isPaid === false || $isPaid === 0 || $isPaid === null) {
                            $leaveIzin += $dur;
                        }
                    }

                    // ── Hitung hari_kerja: mirror logika /admin (roster-missing + pro-rata),
                    //    sumber record = attendance_autolog (bukan att_prepares).
                    $rosterMap = EmployeeShiftRoster::where('employee_id', $employee->id)
                        ->whereBetween('date', [$segStart, $segEnd])
                        ->get(['date', 'external_code'])
                        ->mapWithKeys(fn ($r) => [$r->date->toDateString() => $r->external_code]);

                    // Record autolog dalam segmen (sumber reliabilitas supervisor)
                    $segAutologs = AttendanceAutolog::where('employee_id', $employee->id)
                        ->whereBetween('date', [$segStart, $segEnd])
                        ->get();

                    // ── Tanggal mulai kerja efektif (pro-rata dari awal kontrak) ──
                    $periodStartDate = Carbon::parse($segStart);
                    $joinDate = $employee->join_date ? Carbon::parse($employee->join_date) : null;
                    $contractStart = EmployeeContract::where('employee_id', $employee->id)
                        ->where('start_date', '<=', $segEnd)
                        ->where(function ($q) use ($segStart) {
                            $q->whereNull('end_date')->orWhere('end_date', '>=', $segStart);
                        })
                        ->min('start_date'); // string Y-m-d | null

                    // Pro-rata HANYA kalau karyawan baru masuk tengah periode (join_date > seg start)
                    $hasProRata = $joinDate && $joinDate->gt($periodStartDate);
                    $workStart = ($hasProRata && ($contractStart || $joinDate))
                        ? Carbon::parse($contractStart ?? $joinDate->toDateString())
                        : Carbon::parse($segStart);

                    $missing = 0;
                    $hasAutolog = $segAutologs->pluck('date')->map(fn ($d) => $d->toDateString())->flip();

                    // (1) PRO-RATA: hari kerja kalender (Senin-Sabtu, non-holiday) sebelum mulai kerja = "hilang"
                    if ($hasProRata) {
                        $rangeStart = Carbon::parse($segStart);
                        $rangeEnd = $workStart->copy()->subDay();
                        if ($rangeStart->lte($rangeEnd)) {
                            for ($c = $rangeStart; $c->lte($rangeEnd); $c->addDay()) {
                                $d = $c->toDateString();
                                if ($c->isSunday()) continue;
                                if (in_array($d, $holidays, true)) continue;
                                $missing++;
                            }
                        }
                    }

                    // (2) MISSING ROSTER: hari kerja (P/S/ML) yang TIDAK punya record autolog, mulai dari workStart
                    $cursor = Carbon::parse($segStart);
                    if ($cursor->lt($workStart)) $cursor = $workStart->copy();
                    $endCursor = Carbon::parse($segEnd);
                    while ($cursor->lte($endCursor)) {
                        $date = $cursor->toDateString();
                        if (!isset($hasAutolog[$date]) && in_array($rosterMap[$date] ?? null, ['P', 'S', 'ML'], true)) {
                            $missing++;
                        }
                        $cursor->addDay();
                    }

                    $deductDay = $segAutologs->where('status', 'absent')->count() + $missing + $leaveIzin;
                    $hariKerja = max(0, $hkSegment - $deductDay);

                    // ── LM / lembur (non-split dari snapshot, split dari autolog) ──
                    if ($segCode === null) {
                        $lm          = (float) $snapshot->lm;
                        $lmCount     = (float) $snapshot->lm_count;
                        $lemburCount = (float) $snapshot->lembur_count;
                    } else {
                        $segAutologs = AttendanceAutolog::where('employee_id', $employee->id)
                            ->whereBetween('date', [$segStart, $segEnd])
                            ->get();
                        $lm          = $segAutologs->sum('lm') / 60;
                        $lmCount     = (float) $segAutologs->sum('lm_calc');
                        $lemburCount = (float) $segAutologs->sum('lembur_calc');
                    }

                    // ── Data masukan (salary lookup) ──
                    $segmentMonth = $segCode !== null
                        ? Carbon::parse($segStart)->format('Y-m')
                        : $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT);

                    // TJ Masa Kerja selalu pakai periode payroll (bukan segment month)
                    $payPeriodMonth = $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT);

                    $gajiPokok    = $employee->gaji_pokok($segmentMonth);
                    $premi        = $employee->premi($segmentMonth);
                    $tjMasaKerja  = $employee->tunjangan_masa_kerja($payPeriodMonth);
                    $tunjangan    = $employee->tunjangan($segmentMonth);

                    // ── Hitungan: Gaji ──
                    $gaji = round(($gajiPokok / $fixedDays) * $hariKerja, 2);

                    // ── Hitungan: Upah Lembur ──
                    $totalLemburJam = $lmCount + $lemburCount;

                    // Zero overtime: GRP-ALLIN + GRP-GD (sama dgn admin GajiKaryawan)
                    $isZeroOvertime = $employee->groups()
                        ->whereIn('reference_code', ['GRP-ALLIN', 'GRP-GD'])
                        ->exists();

                    if ($isZeroOvertime) {
                        $upahLembur = 0;
                        $lm = 0;
                        $lmCount = 0;
                        $lemburCount = 0;
                    } else {
                        // GRP-SPR: lembur hanya dari LM
                        $isSpr = $employee->groups()->where('reference_code', 'GRP-SPR')->exists();
                        if ($isSpr) {
                            $lemburCount = 0;
                            $totalLemburJam = $lmCount;
                        }
                        $hourlyBase = $gajiPokok + $tjMasaKerja + $tunjangan;
                        if ($hourlyBase > 0 && $totalLemburJam > 0) {
                            $upahLembur = ceil(($hourlyBase / 173) * $totalLemburJam / 100) * 100;
                        } else {
                            $upahLembur = 0;
                        }
                    }

                    // ── Hitungan: Premi Hadir ──
                    $premiHadir = round(($premi / $fixedDays) * $hariKerja, 2);

                    // ── Split logic: Part 1 (seg-A) vs Part 2 (seg-B) ──
                    $isPart1 = ($segCode === 'A');
                    $revisi  = $isPart1 ? ($tjMasaKerja * -1) : 0;
                    $bpjsTk  = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_tk_karyawan ?? 0);
                    $bpjsKs  = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_kes_karyawan ?? 0);
                    $bpjsPen = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_pensiun ?? 0);
                    $pph     = 0; // PPh ditanggung pemerintah — sama dgn admin (GajiKaryawan)
                    $cashbon = 0;

                    // ── Gaji Kotor ──
                    $gajiKotor = $gaji + $tunjangan + $upahLembur + $premiHadir + $revisi;

                    // ── Potongan ──
                    $potKehadiran = round($deductDay * ($gajiPokok / $fixedDays), 2);
                    $totalPotongan = $bpjsTk + $bpjsKs + $bpjsPen + $pph + $cashbon;

                    // ── Pembulatan 100 ──
                    $beforeRounding = $gajiKotor - $totalPotongan;
                    $rounded = ceil($beforeRounding / 100) * 100;
                    $pblt = round($rounded - $beforeRounding, 2);
                    $gajiBersih = $rounded;

                    // ── SIMPAN ──
                    SupervisorBreakdown::updateOrCreate(
                        [
                            'pay_period_id' => $period->id,
                            'employee_id'   => $employee->id,
                            'segment'       => $segCode,
                        ],
                        [
                        'section'             => $section,
                        'group_codes'         => $groupCodes,
                        'employee_code'       => $employee->employee_code ?? $employee->nip,
                        'employee_name'       => $employee->name,
                        'gender'              => $employee->gender,
                        'department_name'     => $employee->department?->name,
                        'position_name'       => $employee->position?->name,
                        'join_date'           => $employee->join_date,
                        'bank_name'           => $employee->bank_name,
                        'bank_account_number' => $employee->bank_account_number,
                        'bank_account_name'   => $employee->bank_account_name,
                        // Masukan
                        'gaji_pokok'    => $gajiPokok,
                        'premi'         => $premi,
                        'tj_masa_kerja' => $tjMasaKerja,
                        'tunjangan'     => $tunjangan,
                        'hari_kerja'    => $hariKerja,
                        'deduct_day'    => $deductDay,
                        'lm'            => $lm,
                        'lm_count'      => $lmCount,
                        'lembur_count'  => $lemburCount,
                        // Hasil
                        'gaji'         => $gaji,
                        'upah_lembur'  => $upahLembur,
                        'premi_hadir'  => $premiHadir,
                        'revisi'       => $revisi,
                        'gaji_kotor'   => $gajiKotor,
                        // Potongan
                        'bpjs_tk'       => $bpjsTk,
                        'bpjs_ks'       => $bpjsKs,
                        'bpjs_pen'      => $bpjsPen,
                        'pph'           => $pph,
                        'cashbon'       => $cashbon,
                        'pot_kehadiran' => $potKehadiran,
                        // Final
                        'pblt'        => $pblt,
                        'gaji_bersih' => $gajiBersih,
                        'status'      => 'synced',
                        'synced_at'   => now(),
                        'created_by'  => Auth::id(),
                        'updated_by'  => Auth::id(),
                    ]);

                    $processed++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Berhasil mengkalkulasi {$processed} record breakdown.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SupervisorBreakdown calculate error: ' . $e->getMessage(), [
                'period_id' => $period->id,
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal kalkulasi: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PROCESS B: Import dari Excel
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/v1/supervisor/payroll/breakdown/import
     * Import data breakdown dari file CSV di database/seeders/.
     * Pattern: persis seperti importPremi() di hris-app.
     * Hanya UPDATE record yang sudah ada (dari hasil generate/kalkulasi).
     *
     * Kolom CSV (0-indexed, delimiter ;):
     *   [0]=No, [1]=NIP, [2]=Nama, [3]=Bagian, [4]=Jabatan,
     *   [5]=L/P, [6]=Tgl Masuk, [7]=Masa Kerja, [8]=Status,
     *   [9]=Jml Anak, [10]=No Rek, [11]=Premi, [12]=Gaji Pokok,
     *   [13]=TJ MK, [14]=HK, [15]=LM, [16]=Lbr Jam,
     *   [17]=Gaji, [18]=Lembur, [19]=Revisi, [20]=Tunjangan,
     *   [21]=Pr Hadir, [22]=PBLT, [23]=TOTAL,
     *   [24]=BPJS TK, [25]=BPJS KES, [26]=BPJS PEN,
     *   [27]=Cash Bon, [28]=PPh, [29]=TRIMA
     */
    public function import(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period  = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        try {
            $csvPath = $this->resolveCsvPath($period, $segment);

            if (!file_exists($csvPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File CSV tidak ditemukan: ' . $csvPath,
                ], 400);
            }

            $spreadsheet = IOFactory::load($csvPath);
            $worksheet   = $spreadsheet->getActiveSheet();
            $rows        = $worksheet->toArray();

            $updated = 0;
            $skipped = 0;

            DB::beginTransaction();

            // Helper: clean number (format Indonesia) — persis hris-app
            $cleanNum = function ($val) {
                if (empty($val)) {
                    return 0.0;
                }
                if (is_numeric($val)) {
                    return (float) $val;
                }

                $str = (string) $val;
                $str = str_replace(['Rp', ' ', "\xc2\xa0"], '', $str);

                // Handle parentheses: (62.901,76) → -62.901,76
                $isNegative = false;
                if (str_starts_with($str, '(') && str_ends_with($str, ')')) {
                    $str = substr($str, 1, -1);
                    $isNegative = true;
                }

                // If comma exists → decimal separator (Indonesian format)
                if (strpos($str, ',') !== false) {
                    $str = str_replace('.', '', $str);   // remove thousand dots
                    $str = str_replace(',', '.', $str);  // comma → decimal dot
                } else {
                    // Multiple dots → thousand separators
                    if (substr_count($str, '.') > 1) {
                        $str = str_replace('.', '', $str);
                    }
                    // Single dot + exactly 3 trailing digits → thousand separator
                    elseif (preg_match('/^\d+\.\d{3}$/', $str)) {
                        $str = str_replace('.', '', $str);
                    }
                }

                $result = (float) $str;
                return $isNegative ? -$result : $result;
            };

            foreach ($rows as $index => $row) {
                if ($index === 0) continue;

                $nip = trim((string) ($row[1] ?? ''));
                if (empty($nip)) continue;

                $employee = Employee::where('employee_code', $nip)
                    ->orWhere('nip', $nip)
                    ->first();

                if (!$employee) {
                    $skipped++;
                    continue;
                }

                // ── Parse CSV columns ──
                $premi        = $cleanNum($row[11] ?? 0);
                $gajiPokok    = $cleanNum($row[12] ?? 0);
                $tjMasaKerja  = $cleanNum($row[13] ?? 0);
                $hariKerja    = (int) $cleanNum($row[14] ?? 0);
                $lm           = $cleanNum($row[15] ?? 0);    // LM dalam jam
                $lemburJam    = $cleanNum($row[16] ?? 0);    // LBR JAM dalam jam
                $gaji         = $cleanNum($row[17] ?? 0);
                $upahLembur   = $cleanNum($row[18] ?? 0);
                $revisi       = $cleanNum($row[19] ?? 0);
                $tunjangan    = $cleanNum($row[20] ?? 0);
                $premiHadir   = $cleanNum($row[21] ?? 0);
                $pblt         = $cleanNum($row[22] ?? 0);
                $gajiKotor    = $cleanNum($row[23] ?? 0);
                $bpjsTk       = $cleanNum($row[24] ?? 0);
                $bpjsKs       = $cleanNum($row[25] ?? 0);
                $bpjsPen      = $cleanNum($row[26] ?? 0);
                $cashbon      = $cleanNum($row[27] ?? 0);
                $pph          = $cleanNum($row[28] ?? 0);
                $gajiBersih   = $cleanNum($row[29] ?? 0);

                // LM: CSV dalam HARI → (hari × 8) jam, lalu (jam - 1) × 2
                $lmHours = $lm * 8;
                $lmCount = max(0, ($lmHours / 8)) * 14;

                // LBR JAM: CSV sudah dalam JAM → langsung pakai
                $lemburCount = $lemburJam;

                // ── Cari record existing ──
                $breakdown = SupervisorBreakdown::where('pay_period_id', $period->id)
                    ->where('employee_id', $employee->id)
                    ->when($segment, fn($q) => $q->where('segment', $segment))
                    ->first();

                if (!$breakdown) {
                    $skipped++;
                    continue;
                }

                // ── Update record ──
                $breakdown->update([
                    'gaji_pokok'    => $gajiPokok,
                    'premi'         => $premi,
                    'tj_masa_kerja' => $tjMasaKerja,
                    'tunjangan'     => $tunjangan,
                    'hari_kerja'    => $hariKerja,
                    'lm'            => $lmHours,
                    'lm_count'      => $lmCount,
                    'lembur_count'  => $lemburCount,
                    'gaji'          => $gaji,
                    'upah_lembur'   => $upahLembur,
                    'revisi'        => $revisi,
                    'premi_hadir'   => $premiHadir,
                    'pblt'          => $pblt,
                    'gaji_kotor'    => $gajiKotor,
                    'bpjs_tk'       => abs($bpjsTk),
                    'bpjs_ks'       => abs($bpjsKs),
                    'bpjs_pen'      => abs($bpjsPen),
                    'cashbon'       => $cashbon,
                    'pph'           => $pph,
                    'gaji_bersih'   => $gajiBersih,
                    'status'        => 'imported',
                    'synced_at'     => now(),
                    'updated_by'    => Auth::id(),
                ]);

                $updated++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$updated} data berhasil di-update, {$skipped} data dilewati.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SupervisorBreakdown import error: ' . $e->getMessage(), [
                'period_id' => $period->id,
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve CSV file path dari database/seeders/ berdasarkan periode.
     * Mapping: bulan → UPDATE_{NAMA_BULAN}.csv
     * Untuk Januari (split): UPDATE_JAN_1.csv (seg A) / UPDATE_JAN_2.csv (seg B)
     */
    private function resolveCsvPath($period, ?string $segment): ?string
    {
        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET',
            4 => 'APRIL', 5 => 'MEI', 6 => 'JUNI',
            7 => 'JULI', 8 => 'AGUSTUS', 9 => 'SEPTEMBER',
            10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];

        $month = (int) $period->period_month;
        $monthName = $monthNames[$month] ?? strtoupper($period->name);

        // Januari split: dua file terpisah
        if ($month === 1 && $period->is_split) {
            $suffix = ($segment === 'B') ? '2' : '1';
            $filename = "UPDATE_JAN_{$suffix}.csv";
        } else {
            $filename = "UPDATE_{$monthName}.csv";
        }

        return database_path("seeders/{$filename}");
    }

    /**
     * GET /api/v1/supervisor/payroll/breakdown/export
     * Export data breakdown ke Excel — format persis sample (25 kolom, continuous).
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = SupervisorBreakdown::where('pay_period_id', $period->id)
            ->join('employees', 'supervisor_breakdowns.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('supervisor_breakdowns.*', 'employees.ptkp', 'employees.nip', 'employees.join_date as emp_join_date');

        if ($period->is_split) {
            $segment = $segment ?: 'A';
            $query->where('segment', $segment);
        }

        $records = $query->get();

        $periodeEnd = Carbon::parse($period->end_date);

        $exportData = [];
        foreach ($records as $r) {
            // Masa kerja (bulan) dari join_date ke periode.end_date
            $masaKerja = 0;
            $joinDate = $r->emp_join_date ?? $r->join_date;
            if ($joinDate) {
                $masaKerja = (int) Carbon::parse($joinDate)->diffInMonths($periodeEnd);
            }

            // Join Date format dd/mm/yyyy
            $joinDateStr = '-';
            if ($joinDate) {
                $joinDateStr = Carbon::parse($joinDate)->format('d/m/Y');
            }

            $exportData[] = [
                'employee_code' => $r->nip ?? $r->employee_code,
                'name'          => $r->employee_name,
                'department'    => $r->department_name,
                'position'      => $r->position_name,
                'gender'        => $r->gender,
                'masa_kerja'    => $masaKerja,
                'join_date_raw' => $joinDateStr,
                'ptkp'          => $r->ptkp ?? '-',
                'gaji_pokok'    => (float) $r->gaji_pokok,
                'premi'         => (float) $r->premi,
                'tj_masa_kerja' => (float) $r->tj_masa_kerja,
                'hari_kerja'    => (int) $r->hari_kerja,
                'lm'            => round((float) $r->lm / 8, 1),
                'lembur_count'  => (float) $r->lembur_count,
                'upah_lembur'   => (float) $r->upah_lembur,
                'gaji'          => (float) $r->gaji,
                'tunjangan'     => (float) $r->tunjangan,
                'premi_hadir'   => (float) $r->premi_hadir,
                'bpjs_tk'       => (float) $r->bpjs_tk,
                'bpjs_ks'       => (float) $r->bpjs_ks,
                'bpjs_pen'      => (float) $r->bpjs_pen,
                'pph'           => (float) $r->pph,
                'cashbon'       => (float) $r->cashbon,
                'pblt'          => (float) $r->pblt,
                'gaji_bersih'   => (float) $r->gaji_bersih,
            ];
        }

        $periodName = $period->name;
        if ($period->is_split && $segment) {
            $periodName .= " (Segmen {$segment})";
        }
        $filename = 'Laporan_Gaji_Karyawan_' . str_replace(' ', '_', $periodName) . '.xlsx';

        // Hitung grand total
        $totals = [
            'gaji_pokok'    => 0,
            'premi'         => 0,
            'tj_masa_kerja' => 0,
            'upah_lembur'   => 0,
            'gaji'          => 0,
            'tunjangan'     => 0,
            'premi_hadir'   => 0,
            'bpjs_tk'       => 0,
            'bpjs_ks'       => 0,
            'bpjs_pen'      => 0,
            'pph'           => 0,
            'cashbon'       => 0,
            'pblt'          => 0,
            'gaji_bersih'   => 0,
        ];
        foreach ($exportData as $row) {
            foreach ($totals as $key => &$total) {
                $total += $row[$key];
            }
            unset($total);
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Modules\Supervisor\Payroll\Exports\SupervisorPayrollExport($exportData, $periodName, $totals),
            $filename
        );
    }

    /**
     * GET /api/v1/supervisor/payroll/breakdown/print
     * Print/PDF data breakdown — format persis sample PDF.
     */
    public function printPdf(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $validated['segment'] ?? null;

        $query = SupervisorBreakdown::where('pay_period_id', $period->id)
            ->join('employees', 'supervisor_breakdowns.employee_id', '=', 'employees.id')
            ->orderByRaw('employees.no_urut IS NULL, employees.no_urut ASC')
            ->orderBy('employees.nip')
            ->select('supervisor_breakdowns.*', 'employees.ptkp', 'employees.nip', 'employees.join_date as emp_join_date');

        if ($period->is_split) {
            $segment = $segment ?: 'A';
            $query->where('segment', $segment);
        }

        $records = $query->get();

        $periodeEnd = Carbon::parse($period->end_date);

        $data = [];
        foreach ($records as $r) {
            $masaKerja = 0;
            $joinDate = $r->emp_join_date ?? $r->join_date;
            if ($joinDate) {
                $masaKerja = (int) Carbon::parse($joinDate)->diffInMonths($periodeEnd);
            }

            $joinDateStr = '-';
            if ($joinDate) {
                $joinDateStr = Carbon::parse($joinDate)->format('d/m/Y');
            }

            $lm = !empty($r->lm) ? round($r->lm / 8, 1) : 0;
            $lemburCount = !empty($r->lembur_count) ? round($r->lembur_count, 1) : 0;

            // Bagian / Jabatan digabung
            $bagianJabatan = trim(($r->department_name ?? '') . ' / ' . ($r->position_name ?? ''), ' / ');

            $data[] = [
                'no'              => count($data) + 1,
                'employee_code'   => $r->nip ?? $r->employee_code,
                'name'            => $r->employee_name,
                'bagian_jabatan'  => $bagianJabatan,
                'gender'          => $r->gender,
                'masa_kerja'      => $masaKerja,
                'join_date'       => $joinDateStr,
                'ptkp'            => $r->ptkp ?? '-',
                'gaji_pokok'      => (float) $r->gaji_pokok,
                'premi'           => (float) $r->premi,
                'tj_masa_kerja'   => (float) $r->tj_masa_kerja,
                'hari_kerja'      => (int) $r->hari_kerja,
                'lm'              => $lm,
                'lembur_count'    => $lemburCount,
                'upah_lembur'     => (float) $r->upah_lembur,
                'gaji'            => (float) $r->gaji,
                'tunjangan'       => (float) $r->tunjangan,
                'premi_hadir'     => (float) $r->premi_hadir,
                'bpjs_tk'         => (float) $r->bpjs_tk,
                'bpjs_ks'         => (float) $r->bpjs_ks,
                'bpjs_pen'        => (float) $r->bpjs_pen,
                'pph'             => (float) $r->pph,
                'cashbon'         => (float) $r->cashbon,
                'pblt'            => (float) $r->pblt,
                'gaji_bersih'     => (float) $r->gaji_bersih,
            ];
        }

        $periodLabel = $period->name;
        if ($period->is_split && $segment) {
            $periodLabel .= " (Segmen {$segment})";
        }

        // Hitung grand total
        $totals = [
            'gaji_pokok'    => 0,
            'premi'         => 0,
            'tj_masa_kerja' => 0,
            'upah_lembur'   => 0,
            'gaji'          => 0,
            'tunjangan'     => 0,
            'premi_hadir'   => 0,
            'bpjs_tk'       => 0,
            'bpjs_ks'       => 0,
            'bpjs_pen'      => 0,
            'pph'           => 0,
            'cashbon'       => 0,
            'pblt'          => 0,
            'gaji_bersih'   => 0,
        ];
        foreach ($data as $row) {
            foreach ($totals as $key => &$total) {
                $total += $row[$key];
            }
            unset($total);
        }

        // Boost limits for large datasets
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('supervisor.payroll.salary-breakdown-pdf', [
            'data'        => $data,
            'totals'      => $totals,
            'periodLabel' => $periodLabel,
            'companyName' => 'PT. KEMILAU UNGARAN SUKSES',
        ]);

        $pdf->setPaper('A4', 'landscape');

        $filename = 'Laporan_Gaji_Karyawan_' . str_replace(' ', '_', $periodLabel) . '.pdf';
        return $pdf->download($filename);
    }
}
