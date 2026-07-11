<?php

namespace App\Modules\Supervisor\Payroll\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayrollConfig;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Supervisor\Attendance\Models\SupervisorAttendanceSnapshot;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
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
                // Masukan
                'gaji_pokok'    => (float) $record->gaji_pokok,
                'premi'         => (float) $record->premi,
                'tj_masa_kerja' => (float) $record->tj_masa_kerja,
                'tunjangan'     => (float) $record->tunjangan,
                'hari_kerja'    => (int) $record->hari_kerja,
                'lm'            => (int) $record->lm,
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
                'id'       => $period->id,
                'name'     => $period->name,
                'is_split' => $period->is_split,
                'segment'  => $segment,
            ],
        ]);
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
        $sectionAGroups = $gajiConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];

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
                    if (!isset($splitDays['A']) || !isset($splitDays['B'])) {
                        throw new \Exception('Nilai hari kerja untuk split periode (A dan B) belum diatur.');
                    }
                    $hkA = (int) $splitDays['A'];
                    $hkB = (int) $splitDays['B'];
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

                    // Untuk normal (non-split), ambil langsung dari snapshot
                    if ($segCode === null) {
                        $hariKerja = (int) $snapshot->hari_kerja;
                        $deductDay = (float) $snapshot->deduct_day;
                        $lm        = (int) $snapshot->lm;
                        $lmCount   = (float) $snapshot->lm_count;
                        $lemburCount = (float) $snapshot->lembur_count;
                    } else {
                        // Split: ambil LM & lembur langsung dari attendance_autologs per segmen
                        // Part 1 (A): tgl 25-31, Part 2 (B): tgl 1-24
                        $segLogs = AttendanceAutolog::where('employee_id', $employee->id)
                            ->whereBetween('date', [$segStart, $segEnd])
                            ->get();

                        $deductDay   = (float) $segLogs->sum('deduct_day') + $segLogs->where('deduct_attendance', 1)->count();
                        $hariKerja   = $hkSegment - $deductDay;
                        $lm          = (int) $segLogs->sum('lm');
                        $lmCount     = (float) $segLogs->sum('lm_calc');
                        $lemburCount = (float) $segLogs->sum('lembur_calc');
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

                    // Zero overtime untuk section A (ALL IN, kecuali GRP-SPR)
                    $isZeroOvertime = $employee->groups()
                        ->whereIn('reference_code', $sectionAGroups)
                        ->where('reference_code', '!=', 'GRP-SPR')
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
                    $pph     = 0; // TODO: dari pengelolaan PPH
                    $cashbon = 0;

                    // ── Gaji Kotor ──
                    $gajiKotor = $gaji + $tjMasaKerja + $upahLembur + $revisi + $premiHadir + $tunjangan;

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
                $lm           = (int) $cleanNum($row[15] ?? 0);    // LM dalam menit
                $lemburJam    = $cleanNum($row[16] ?? 0);           // LBR JAM
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

                // LM count = LM * 60 (konversi jam→menit, karena CSV simpan dalam jam)
                $lmCount = $lm * 60;

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
                    'lm'            => $lm,
                    'lm_count'      => $lmCount,
                    'lembur_count'  => $lemburJam,
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
     * Export data breakdown ke Excel.
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
            ->select('supervisor_breakdowns.*', 'employees.ptkp', 'employees.nip');

        if ($period->is_split) {
            $segment = $segment ?: 'A';
            $query->where('segment', $segment);
        }

        $records = $query->get();

        $payrollConfig = PayrollConfig::getConfig('gaji_karyawan');
        $sectionAGroups = $payrollConfig['sections']['A'] ?? ['GRP-ALLIN', 'GRP-SPR'];
        $sectionBGroups = $payrollConfig['sections']['B'] ?? ['GRP-GD', 'GRP-SS', 'GRP-PS1'];

        $periodeEnd = Carbon::parse($period->end_date);

        $secAData = [];
        $secBData = [];
        foreach ($records as $r) {
            $groups = $r->group_codes ?? [];

            // Masa kerja (bulan)
            $masaKerja = 0;
            if ($r->join_date) {
                $masaKerja = (int) Carbon::parse($r->join_date)->diffInMonths($periodeEnd);
            }

            $row = [
                'employee_code'       => $r->nip ?? $r->employee_code,
                'name'                => $r->employee_name,
                'department'          => $r->department_name,
                'position'            => $r->position_name,
                'gender'              => $r->gender,
                'masa_kerja'          => $masaKerja,
                'join_year'           => $r->join_date ? Carbon::parse($r->join_date)->format('d-M-Y') : '-',
                'ptkp'                => $r->ptkp ?? '-',
                'groups'              => $groups,
                'bank_name'           => $r->bank_name,
                'bank_account_number' => $r->bank_account_number,
                'bank_account_name'   => $r->bank_account_name,
                'gaji_pokok'    => (float) $r->gaji_pokok,
                'premi'         => (float) $r->premi,
                'tj_masa_kerja' => (float) $r->tj_masa_kerja,
                'tunjangan'     => (float) $r->tunjangan,
                'hari_kerja'    => (int) $r->hari_kerja,
                'lm'            => (int) $r->lm,
                'lembur_count'  => (float) $r->lembur_count,
                'gaji'          => (float) $r->gaji,
                'upah_lembur'   => (float) $r->upah_lembur,
                'revisi'        => (float) $r->revisi,
                'premi_hadir'   => (float) $r->premi_hadir,
                'pblt'          => (float) $r->pblt,
                'total'         => (float) $r->gaji_kotor,
                'bpjs_tk'       => (float) $r->bpjs_tk,
                'bpjs_ks'       => (float) $r->bpjs_ks,
                'bpjs_pen'      => (float) $r->bpjs_pen,
                'cashbon'       => (float) $r->cashbon,
                'pph'           => (float) $r->pph,
                'gaji_bersih'   => (float) $r->gaji_bersih,
            ];
            if (array_intersect($groups, $sectionAGroups)) {
                $secAData[] = $row;
            } elseif (array_intersect($groups, $sectionBGroups)) {
                $secBData[] = $row;
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
}
