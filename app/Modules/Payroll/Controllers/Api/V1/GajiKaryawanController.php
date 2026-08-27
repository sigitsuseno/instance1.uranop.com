<?php

namespace App\Modules\Payroll\Controllers\Api\V1;

use App\Models\ExtraEmployee;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Settings\Models\SystemSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class GajiKaryawanController extends Controller
{
    // ─── STATUS LIFECYCLE: draft → generated → locked ───
    private const STATUS_DRAFT     = 'draft';
    private const STATUS_GENERATED = 'generated';
    private const STATUS_LOCKED    = 'locked';

    // ─── ZERO OVERTIME (KEPUTUSAN #4): GRP-ALLIN + GRP-GD ───
    private const ZERO_OVERTIME_GROUPS = ['GRP-ALLIN', 'GRP-GD'];

    /**
     * GET /api/v1/payroll/gaji-karyawan?period_id=X&segment=Y
     *
     * Satu endpoint, dua mode (logic_payroll_baru.md §2.1):
     *  - end_date BELUM lewat  → ON_THE_FLY (hitung live dari att_prepares)
     *  - end_date SUDAH lewat  → ON_RECORD (baca pay_records)
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        if ($period->end_date && Carbon::today()->lte($period->end_date)) {
            return $this->onTheFly($period, $segment);
        }

        return $this->onRecord($period, $segment);
    }

    /**
     * Total estimasi gaji_kotor seluruh segmen periode (on-the-fly dari att_prepares).
     * Dipakai dashboard (card Total Payroll) — konsisten dengan tabel Gaji Karyawan.
     * Catatan: tidak menyertakan ExtraEmployee (sama seperti sum pay_records di dashboard).
     */
    public function estimateGajiKotorTotal(PayPeriod $period): float
    {
        $computed = $this->computeOnTheFlyRows($period, null);

        return (float) collect($computed['rows'])->sum('gaji_kotor');
    }

    // =====================================================================
    // MODE ON_RECORD — baca pay_records (logika existing, + mode/status)
    // =====================================================================

    private function onRecord(PayPeriod $period, ?string $segment)
    {
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

        // ── Tambahan: Karyawan tambahan (ExtraEmployee) masuk ke All-In ──
        $records = $this->appendExtraEmployees($records);

        return response()->json([
            'mode' => 'on_record',
            'record_status' => $this->recordStatusFor($period, $segment),
            'data' => $records,
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'is_split' => $period->is_split,
                'segment' => $segment,
                'end_date' => $period->end_date?->format('Y-m-d'),
                'tanggal_penggajian' => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
        ]);
    }

    // =====================================================================
    // MODE ON_THE_FLY — hitung live dari att_prepares (logic_payroll_baru.md §3)
    // =====================================================================

    private function onTheFly(PayPeriod $period, ?string $segment)
    {
        $computed = $this->computeOnTheFlyRows($period, $segment);

        $records = collect($computed['rows'])->map(fn ($row) => $this->mapOnTheFlyRow($row));

        // ── Tambahan: Karyawan tambahan (ExtraEmployee) masuk ke All-In ──
        $records = $this->appendExtraEmployees($records);

        return response()->json([
            'mode' => 'on_the_fly',
            'record_status' => $this->recordStatusFor($period, $segment),
            'data' => $records,
            'period' => [
                'id' => $period->id,
                'name' => $period->name,
                'is_split' => $period->is_split,
                'segment' => $segment,
                'end_date' => $period->end_date?->format('Y-m-d'),
                'tanggal_penggajian' => $period->tanggal_penggajian?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Hitung data on_the_fly untuk semua karyawan (isGroupGaji + activeInPeriod + roster).
     * Dipakai bersama oleh onTheFly() (response) dan simpan() (snapshot).
     *
     * @return array{rows: array, fixed_days: int}
     */
    private function computeOnTheFlyRows(PayPeriod $period, ?string $segment): array
    {
        $now = Carbon::today();
        $fixedDays = $this->fixedWorkingDay();

        // KEPUTUSAN #9: holiday dari tabel sch_holidays, rentang start–end periode
        $holidays = Holiday::whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $segments = $this->buildSegments($period, $segment);

        // KEPUTUSAN #5: filter isGroupGaji() — whitelist 5 group
        $employees = Employee::with(['department', 'position', 'groups', 'bpjs'])
            ->activeInPeriod($period->start_date->toDateString(), $period->end_date->toDateString())
            ->whereHas('shiftRosters', fn ($q) => $q->whereBetween('date', [
                $period->start_date->toDateString(),
                $period->end_date->toDateString(),
            ]))
            ->get()
            ->filter(fn ($emp) => $emp->isGroupGaji())
            ->sortBy('no_urut')->sortBy('nip')
            ->values();

        $rows = [];

        foreach ($employees as $employee) {
            foreach ($segments as $seg) {
                $segStart = $seg['start'];
                $effEnd = Carbon::parse($seg['end'])->lt($now) ? $seg['end'] : $now->toDateString();

                // KEPUTUSAN #8: count record aktual start→NOW (segmen yang lewat dihitung full)
                $prepares = AttendancePrepare::where('employee_id', $employee->id)
                    ->whereBetween('date', [$segStart, $effEnd])
                    ->get();

                $hariKerja = $prepares->filter(function ($p) use ($holidays) {
                    $date = $p->date->toDateString();
                    if (Carbon::parse($date)->isSunday()) return false;   // Minggu → skip
                    if (in_array($date, $holidays)) return false;          // holiday → skip
                    if ($p->status === AttendancePrepare::STATUS_ABSENT) return false; // absent → skip
                    return true;
                })->count();

                $lm          = $prepares->sum('lm');
                $lmCount     = $prepares->sum('lm_count');
                $lemburCount = $prepares->sum('overtime_count');

                $segmentMonth = $seg['segment'] !== null
                    ? Carbon::parse($segStart)->format('Y-m')
                    : $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT);

                $gajiPokok   = $employee->gaji_pokok($segmentMonth);
                $premi       = $employee->premi($segmentMonth);
                $tjMasaKerja = $employee->tunjangan_masa_kerja($segmentMonth);
                $tunjangan   = $employee->tunjangan($segmentMonth);

                // KEPUTUSAN #1: pembagi fixed_working_day (25)
                $gaji       = round(($gajiPokok / $fixedDays) * $hariKerja, 2);
                $premiHadir = round(($premi / $fixedDays) * $hariKerja, 2);

                // KEPUTUSAN #4: GRP-ALLIN & GRP-GD di-0-kan; GRP-SPR hanya LM
                $isZeroOvertime = $employee->groups()->whereIn('reference_code', self::ZERO_OVERTIME_GROUPS)->exists();

                if ($isZeroOvertime) {
                    $lm = 0; $lmCount = 0; $lemburCount = 0;
                    $upahLembur = 0;
                } else {
                    $isSpr = $employee->hasGroup('GRP-SPR');
                    if ($isSpr) {
                        $lemburCount = 0;
                        $totalLemburJam = $lmCount / 60;
                    } else {
                        $totalLemburJam = ($lmCount + $lemburCount) / 60;
                    }

                    // KEPUTUSAN #3: round-up kelipatan 100
                    $upahLembur = $totalLemburJam > 0
                        ? ceil((($gajiPokok + $tjMasaKerja + $tunjangan) / 173) * $totalLemburJam / 100) * 100
                        : 0;
                }

                $isPart1 = ($seg['segment'] === 'A');
                $revisi  = $isPart1 ? ($tjMasaKerja * -1) : 0;
                $bpjsTk  = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_tk_karyawan ?? 0);
                $bpjsKs  = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_kes_karyawan ?? 0);
                $bpjsPen = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_pensiun ?? 0);

                // gaji_kotor = gaji + tunjangan + upah_lembur + premi_hadir + revisi + tj_masa_kerja
                $gajiKotor = $gaji + $tunjangan + $upahLembur + $premiHadir + $revisi + $tjMasaKerja;
                $pph     = 0;    // beda dari final
                $cashbon = 0;

                $beforeRounding = $gajiKotor - ($bpjsTk + $bpjsKs + $bpjsPen + $pph + $cashbon);
                $rounded = ceil($beforeRounding / 100) * 100;
                $pblt = round($rounded - $beforeRounding, 2);
                $gajiBersih = $rounded;

                $rows[] = [
                    'employee'      => $employee,
                    'segment'       => $seg['segment'],
                    'gaji_pokok'    => $gajiPokok,
                    'premi'         => $premi,
                    'tj_masa_kerja' => $tjMasaKerja,
                    'tunjangan'     => $tunjangan,
                    'hari_kerja'    => $hariKerja,
                    'lm'            => $lm,
                    'lm_count'      => $lmCount,
                    'lembur_count'  => $lemburCount,
                    'gaji'          => $gaji,
                    'upah_lembur'   => $upahLembur,
                    'revisi'        => $revisi,
                    'premi_hadir'   => $premiHadir,
                    'gaji_kotor'    => $gajiKotor,
                    'bpjs_tk'       => $bpjsTk,
                    'bpjs_ks'       => $bpjsKs,
                    'bpjs_pen'      => $bpjsPen,
                    'pph'           => $pph,
                    'cashbon'       => $cashbon,
                    'pblt'          => $pblt,
                    'gaji_bersih'   => $gajiBersih,
                ];
            }
        }

        return ['rows' => $rows, 'fixed_days' => $fixedDays];
    }

    /**
     * Mapping row on_the_fly → shape response (id unik 'est-*' — belum ada pay_record).
     */
    private function mapOnTheFlyRow(array $row): array
    {
        $emp = $row['employee'];
        $joinDate = $emp?->join_date ? Carbon::parse($emp->join_date) : null;

        return [
            'id' => 'est-' . $emp->id,
            'employee_id' => $emp->id,
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
            'notes' => '',
            'gaji_pokok' => (float) $row['gaji_pokok'],
            'premi' => (float) $row['premi'],
            'tj_masa_kerja' => (float) $row['tj_masa_kerja'],
            'tunjangan' => (float) $row['tunjangan'],
            'hari_kerja' => (int) $row['hari_kerja'],
            'lm' => (int) $row['lm'],
            'lm_count' => (int) $row['lm_count'],
            'lembur_count' => (int) $row['lembur_count'],
            'gaji' => (float) $row['gaji'],
            'upah_lembur' => (float) $row['upah_lembur'],
            'revisi' => (float) $row['revisi'],
            'premi_hadir' => (float) $row['premi_hadir'],
            'pblt' => (float) $row['pblt'],
            'total' => (float) $row['gaji_kotor'],
            'bpjs_tk' => (float) $row['bpjs_tk'],
            'bpjs_ks' => (float) $row['bpjs_ks'],
            'bpjs_pen' => (float) $row['bpjs_pen'],
            'cashbon' => (float) $row['cashbon'],
            'pph' => (float) $row['pph'],
            'gaji_bersih' => (float) $row['gaji_bersih'],
        ];
    }

    // =====================================================================
    // SNAPSHOT — POST /gaji-karyawan/simpan (logic_payroll_baru.md §2.2)
    // Copy hasil on_the_fly → pay_records (status: draft). TANPA hitung ulang.
    // =====================================================================

    public function simpan(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        $existing = PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->get();

        abort_if(
            $existing->contains(fn ($r) => in_array($r->status, [self::STATUS_GENERATED, self::STATUS_LOCKED], true)),
            403,
            'Payroll sudah final/dikunci. Simpan ulang hanya untuk status draft.'
        );

        $computed = $this->computeOnTheFlyRows($period, $segment);
        $saved = 0;

        foreach ($computed['rows'] as $row) {
            PayRecord::updateOrCreate(
                [
                    'employee_id'   => $row['employee']->id,
                    'pay_period_id' => $period->id,
                    'segment'       => $row['segment'],
                ],
                [
                    'status'        => self::STATUS_DRAFT,
                    // data masukan
                    'gaji_pokok'    => $row['gaji_pokok'],
                    'premi'         => $row['premi'],
                    'tj_masa_kerja' => $row['tj_masa_kerja'],
                    'tunjangan'     => $row['tunjangan'],
                    'deduct_day'    => 0,
                    // hasil hitungan on_the_fly (dicopy, bukan dihitung ulang)
                    'hari_kerja'    => $row['hari_kerja'],
                    'lm'            => $row['lm'],
                    'lm_count'      => $row['lm_count'],
                    'lembur_count'  => $row['lembur_count'],
                    'gaji'          => $row['gaji'],
                    'upah_lembur'   => $row['upah_lembur'],
                    'premi_hadir'   => $row['premi_hadir'],
                    'revisi'        => $row['revisi'],
                    'gaji_kotor'    => $row['gaji_kotor'],
                    'bpjs_tk'       => $row['bpjs_tk'],
                    'bpjs_ks'       => $row['bpjs_ks'],
                    'bpjs_pen'      => $row['bpjs_pen'],
                    'pph'           => $row['pph'],
                    'cashbon'       => $row['cashbon'],
                    'pot_kehadiran' => 0,
                    'pblt'          => $row['pblt'],
                    'gaji_bersih'   => $row['gaji_bersih'],
                ]
            );
            $saved++;
        }

        return response()->json([
            'message' => "Snapshot berhasil disimpan untuk {$saved} karyawan (status draft).",
            'mode' => 'on_the_fly',
            'record_status' => self::STATUS_DRAFT,
        ]);
    }

    // =====================================================================
    // FINALISASI — POST /gaji-karyawan/finalisasi (logic_payroll_baru.md §2.3)
    // Hitung ulang rumus LAMA (referensi recapApprove) + status generated.
    // =====================================================================

    public function finalisasi(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        // Prasyarat: now() > end_date
        abort_if($period->end_date && Carbon::today()->lte($period->end_date), 403, 'Periode belum berakhir — finalisasi hanya bisa dilakukan setelah end_date.');

        $records = PayRecord::with(['employee.groups'])
            ->where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->get();

        abort_if($records->isEmpty(), 404, 'Tidak ada data payroll untuk difinalisasi. Klik Simpan dulu untuk membuat snapshot.');
        abort_if($records->contains(fn ($r) => $r->status === self::STATUS_LOCKED), 403, 'Payroll sudah dikunci.');

        $fixedDays  = $this->fixedWorkingDay();
        $splitDays  = $this->splitDays();
        $startDate  = $period->start_date->toDateString();
        $endDate    = $period->end_date->toDateString();
        $month1End  = Carbon::parse($startDate)->endOfMonth()->toDateString();
        $month2Start = Carbon::parse($endDate)->startOfMonth()->toDateString();

        // Holiday periode (buat hitung "hari kerja" pro-rata, konsisten dengan fixed_working_day)
        $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->toArray();

        $processed = 0;

        foreach ($records as $record) {
            $employee = $record->employee;

            // ── Segmen (sama dengan recapApprove) ──
            if ($period->is_split) {
                $hkA = (int) ($splitDays['A'] ?? 5);
                $hkB = (int) ($splitDays['B'] ?? max(0, $fixedDays - $hkA));
                $segments = [
                    'A' => ['start' => $startDate,  'end' => $month1End,  'hk' => $hkA],
                    'B' => ['start' => $month2Start, 'end' => $endDate,    'hk' => $hkB],
                ];
                $seg     = $segments[$record->segment ?? 'A'];
                $isPart1 = $record->segment === 'A';
            } else {
                $seg     = ['start' => $startDate, 'end' => $endDate, 'hk' => $fixedDays];
                $isPart1 = false;
            }

            $segStart = $seg['start'];
            $segEnd   = $seg['end'];
            $hkSegment = $seg['hk'];

            // ── Aggregate att_prepares (full periode/segmen) ──
            $prepares = AttendancePrepare::where('employee_id', $employee->id)
                ->whereBetween('date', [$segStart, $segEnd])
                ->get();

            // Roster map: tanggal → external_code utk segmen ini (deteksi missing)
            $rosterMap = EmployeeShiftRoster::where('employee_id', $employee->id)
                ->whereBetween('date', [$segStart, $segEnd])
                ->get(['date', 'external_code'])
                ->mapWithKeys(fn ($r) => [$r->date->toDateString() => $r->external_code]);

            // ── Tanggal mulai kerja efektif (pro-rata dari tanggal awal kontrak) ──
            $periodStartDate = Carbon::parse($period->start_date->toDateString());
            $joinDate = $employee->join_date ? Carbon::parse($employee->join_date) : null;
            $contractStart = EmployeeContract::where('employee_id', $employee->id)
                ->where('start_date', '<=', $segEnd)
                ->where(function ($q) use ($segStart) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $segStart);
                })
                ->min('start_date'); // string Y-m-d | null

            // Pro-rata HANYA kalau karyawan baru masuk di tengah periode (join_date > period start)
            $hasProRata = $joinDate && $joinDate->gt($periodStartDate);
            $workStart = ($hasProRata && ($contractStart || $joinDate))
                ? Carbon::parse($contractStart ?? $joinDate->toDateString())
                : Carbon::parse($segStart);

            $missing = 0;
            $hasPrepare = $prepares->pluck('date')->map(fn ($d) => $d->toDateString())->flip();

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

            // (2) MISSING ROSTER: hari kerja (P/S/ML) yang TIDAK punya att_prepare, mulai dari workStart
            $cursor = Carbon::parse($segStart);
            if ($cursor->lt($workStart)) $cursor = $workStart->copy();
            $endCursor = Carbon::parse($segEnd);
            while ($cursor->lte($endCursor)) {
                $date = $cursor->toDateString();
                if (!isset($hasPrepare[$date]) && in_array($rosterMap[$date] ?? null, ['P', 'S', 'ML'], true)) {
                    $missing++;
                }
                $cursor->addDay();
            }

            // absent = count status absent + missing (hari kerja roster tak ada record / baru masuk pro-rata)
            $absen = $prepares->where('status', 'absent')->count() + $missing;
            $lm = $prepares->sum('lm');
            $lmCount = $prepares->sum('lm_count');
            $lemburCount = $prepares->sum('overtime_count');

            // ── Aggregate leave approved dalam segmen ──
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

            $unpaid = 0;
            foreach ($leaves as $l) {
                $lStart = Carbon::parse(max($l->start_date->toDateString(), $segStart));
                $lEnd   = Carbon::parse(min($l->end_date->toDateString(), $segEnd));
                $dur    = max(0, $lStart->diffInDays($lEnd) + 1);
                if (optional($l->leaveType)->is_paid === false) {
                    $unpaid += $dur;
                }
            }

            // hari_kerja pakai hitungan LAMA: hk − (izin tak dibayar + absent)
            $deductDay = $unpaid + $absen;
            $hariKerja = max(0, $hkSegment - $deductDay);

            // ── Data masukan ──
            $segmentMonth = $record->segment !== null
                ? Carbon::parse($segStart)->format('Y-m')
                : $period->period_year . '-' . str_pad($period->period_month, 2, '0', STR_PAD_LEFT);

            $gajiPokok   = $employee->gaji_pokok($segmentMonth);
            $premi       = $employee->premi($segmentMonth);
            $tjMasaKerja = $employee->tunjangan_masa_kerja($segmentMonth);
            $tunjangan   = $employee->tunjangan($segmentMonth);

            // ── Hitungan (rumus LAMA) ──
            $gaji = round(($gajiPokok / $fixedDays) * $hariKerja, 2);

            $isZeroOvertime = $employee->groups()->whereIn('reference_code', self::ZERO_OVERTIME_GROUPS)->exists();
            if ($isZeroOvertime) {
                $upahLembur = 0;
                $lm = 0; $lmCount = 0; $lemburCount = 0;
            } else {
                $isSpr = $employee->hasGroup('GRP-SPR');
                if ($isSpr) {
                    $lemburCount = 0;
                    $totalLemburJam = $lmCount / 60;
                } else {
                    $totalLemburJam = ($lmCount + $lemburCount) / 60;
                }
                $upahLembur = $totalLemburJam > 0
                    ? ceil((($gajiPokok + $tjMasaKerja + $tunjangan) / 173) * $totalLemburJam / 100) * 100
                    : 0;
            }

            $premiHadir = round(($premi / $fixedDays) * $hariKerja, 2);

            $revisi  = $isPart1 ? ($tjMasaKerja * -1) : 0;
            $bpjsTk  = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_tk_karyawan ?? 0);
            $bpjsKs  = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_kes_karyawan ?? 0);
            $bpjsPen = $isPart1 ? 0 : (float) ($employee->bpjs?->bpjs_pensiun ?? 0);

            // PPh = 0 — ditanggung pemerintah, dikelola perusahaan (sama dengan on_the_fly)
            $pph = 0;
            // Cashbon dipertahankan dari draft (tidak di-reset 0)
            $cashbon = (float) $record->cashbon;

            // gaji_kotor = gaji + tunjangan + upah_lembur + premi_hadir + revisi + tj_masa_kerja
            $gajiKotor = $gaji + $tunjangan + $upahLembur + $premiHadir + $revisi + $tjMasaKerja;
            $potKehadiran = round($deductDay * ($gajiPokok / $fixedDays), 2);

            $totalPotongan = $bpjsTk + $bpjsKs + $bpjsPen + $pph + $cashbon;
            $beforeRounding = $gajiKotor - $totalPotongan;
            $rounded = ceil($beforeRounding / 100) * 100;
            $pblt = round($rounded - $beforeRounding, 2);
            $gajiBersih = $rounded;

            $record->update([
                'gaji_pokok'    => $gajiPokok,
                'premi'         => $premi,
                'tj_masa_kerja' => $tjMasaKerja,
                'tunjangan'     => $tunjangan,
                'hari_kerja'    => $hariKerja,
                'deduct_day'    => $deductDay,
                'lm'            => $lm,
                'lm_count'      => $lmCount,
                'lembur_count'  => $lemburCount,
                'gaji'          => $gaji,
                'upah_lembur'   => $upahLembur,
                'premi_hadir'   => $premiHadir,
                'revisi'        => $revisi,
                'gaji_kotor'    => $gajiKotor,
                'bpjs_tk'       => $bpjsTk,
                'bpjs_ks'       => $bpjsKs,
                'bpjs_pen'      => $bpjsPen,
                'pph'           => $pph,
                'cashbon'       => $cashbon,
                'pot_kehadiran' => $potKehadiran,
                'pblt'          => $pblt,
                'gaji_bersih'   => $gajiBersih,
                'status'        => self::STATUS_GENERATED,
            ]);

            $processed++;
        }

        return response()->json([
            'message' => "Payroll berhasil difinalisasi untuk {$processed} karyawan.",
            'mode' => 'on_record',
            'record_status' => self::STATUS_GENERATED,
        ]);
    }

    // =====================================================================
    // LOCK / UNLOCK — logic_payroll_baru.md §2.4 & §2.5
    // =====================================================================

    public function lock(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        $records = PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->get();

        abort_if($records->isEmpty(), 404, 'Tidak ada data payroll untuk dikunci.');
        abort_if(
            $records->contains(fn ($r) => $r->status !== self::STATUS_GENERATED),
            403,
            'Finalisasi dulu sebelum mengunci payroll.'
        );

        PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->update(['status' => self::STATUS_LOCKED]);

        return response()->json([
            'message' => 'Payroll berhasil dikunci.',
            'mode' => 'on_record',
            'record_status' => self::STATUS_LOCKED,
        ]);
    }

    public function unlock(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
            'password'  => 'required|string',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        $records = PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->get();

        abort_if($records->isEmpty(), 404, 'Tidak ada data payroll.');

        // KEPUTUSAN #10: password plain text di SystemSetting key payroll_lock_password
        $stored = SystemSetting::where('key', 'payroll_lock_password')->value('value');

        abort_if(empty($stored) || !hash_equals((string) $stored, (string) $validated['password']), 403, 'Password salah.');

        PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->update(['status' => self::STATUS_GENERATED]);

        return response()->json([
            'message' => 'Payroll berhasil dibuka kuncinya (status generated).',
            'mode' => 'on_record',
            'record_status' => self::STATUS_GENERATED,
        ]);
    }

    /**
     * POST /api/v1/payroll/gaji-karyawan/sync-missing
     *
     * FUNGSI BARU: tambahkan baris pay_record HANYA untuk karyawan yang BELUM ada
     * (skip yang sudah ada). Dipakai untuk menambal karyawan yang kelewat di-snapshot
     * (mis. baru di-assign group / baru aktif). Tidak mengubah data existing.
     * Record baru dibuat status DRAFT, biar bisa ikut finalisasi (rumus lama) menyusul.
     */
    public function syncMissingRecords(Request $request)
    {
        $validated = $request->validate([
            'period_id' => 'required|exists:pay_periods,id',
            'segment'   => 'nullable|in:A,B',
        ]);

        $period = PayPeriod::findOrFail($validated['period_id']);
        $segment = $this->resolveSegment($period, $validated['segment'] ?? null);

        // Guard: jangan tambah record ke payroll yang sudah di-lock
        $isLocked = PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->where('status', self::STATUS_LOCKED)
            ->exists();
        abort_if($isLocked, 403, 'Payroll periode ini sudah dikunci. Buka kunci dulu sebelum menambahkan record.');

        $computed = $this->computeOnTheFlyRows($period, $segment);

        // Set employee_id yang SUDAH ada pay_record pada periode ini
        $existing = PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $created = 0;
        $skipped = 0;

        foreach ($computed['rows'] as $row) {
            $empId = (int) $row['employee']->id;

            if (in_array($empId, $existing, true)) {
                $skipped++; // sudah ada → abaikan
                continue;
            }

            PayRecord::create([
                'employee_id'   => $empId,
                'pay_period_id' => $period->id,
                'segment'       => $row['segment'],
                'status'        => self::STATUS_DRAFT,
                // data masukan
                'gaji_pokok'    => $row['gaji_pokok'],
                'premi'         => $row['premi'],
                'tj_masa_kerja' => $row['tj_masa_kerja'],
                'tunjangan'     => $row['tunjangan'],
                'deduct_day'    => 0,
                // hasil hitungan on_the_fly (dicopy, bukan dihitung ulang)
                'hari_kerja'    => $row['hari_kerja'],
                'lm'            => $row['lm'],
                'lm_count'      => $row['lm_count'],
                'lembur_count'  => $row['lembur_count'],
                'gaji'          => $row['gaji'],
                'upah_lembur'   => $row['upah_lembur'],
                'premi_hadir'   => $row['premi_hadir'],
                'revisi'        => $row['revisi'],
                'gaji_kotor'    => $row['gaji_kotor'],
                'bpjs_tk'       => $row['bpjs_tk'],
                'bpjs_ks'       => $row['bpjs_ks'],
                'bpjs_pen'      => $row['bpjs_pen'],
                'pph'           => $row['pph'],
                'cashbon'       => $row['cashbon'],
                'pot_kehadiran' => 0,
                'pblt'          => $row['pblt'],
                'gaji_bersih'   => $row['gaji_bersih'],
            ]);
            $created++;
        }

        return response()->json([
            'message' => "{$created} karyawan baru ditambahkan; {$skipped} sudah ada (diabaikan).",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // =====================================================================
    // GUARD — semua mutasi pay_records mati saat status locked (logic_payroll_baru.md §5)
    // =====================================================================

    private function ensureEditable(PayRecord $record): void
    {
        abort_if($record->status === self::STATUS_LOCKED, 403, 'Payroll sudah dikunci.');
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
        $this->ensureEditable($record);

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

        abort_if($query->where('status', 'locked')->exists(), 403, 'Payroll sudah dikunci — tidak bisa bulk update.');

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
        $this->ensureEditable($record);

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

        // ── Recalculate gaji_kotor (tj_masa_kerja masuk) ──
        $record->gaji_kotor = round(
            (float) $record->gaji
            + (float) $record->tunjangan
            + (float) $record->upah_lembur
            + (float) $record->premi_hadir
            + (float) $record->revisi
            + (float) $record->tj_masa_kerja,
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

    // =====================================================================
    // HELPERS
    // =====================================================================

    /** Normalisasi segment: split selalu butuh segment (default A). */
    private function resolveSegment(PayPeriod $period, ?string $segment): ?string
    {
        if ($period->is_split) {
            return $segment ?? 'A';
        }

        return null;
    }

    /** fixed_working_day dari SystemSetting payroll_config (default 25). */
    private function fixedWorkingDay(): int
    {
        return (int) (SystemSetting::where('key', 'payroll_config')->first()?->fixed_working_day ?? 25);
    }

    /** Split days {"A":5,"B":20} dari SystemSetting payroll_config.value. */
    private function splitDays(): array
    {
        $raw = SystemSetting::where('key', 'payroll_config')->first()?->value;

        return json_decode($raw ?? '{}', true) ?? [];
    }

    /** Status lifecycle pay_records untuk periode/segmen (null = belum ada). */
    private function recordStatusFor(PayPeriod $period, ?string $segment): ?string
    {
        $statuses = PayRecord::where('pay_period_id', $period->id)
            ->when($period->is_split, fn ($q) => $q->where('segment', $segment))
            ->pluck('status');

        if ($statuses->contains(self::STATUS_LOCKED)) return self::STATUS_LOCKED;
        if ($statuses->contains(self::STATUS_GENERATED)) return self::STATUS_GENERATED;
        if ($statuses->contains(self::STATUS_DRAFT)) return self::STATUS_DRAFT;

        return $statuses->isNotEmpty() ? (string) $statuses->first() : null;
    }

    /** Segmen periode: non-split = 1 segmen; split = A/B (rentang + hk config). */
    private function buildSegments(PayPeriod $period, ?string $segment): array
    {
        $fixedDays = $this->fixedWorkingDay();

        if (! $period->is_split) {
            return [[
                'segment' => null,
                'start'   => $period->start_date->toDateString(),
                'end'     => $period->end_date->toDateString(),
                'hk'      => $fixedDays,
            ]];
        }

        $split = $this->splitDays();
        $hkA = (int) ($split['A'] ?? 5);
        $hkB = (int) ($split['B'] ?? max(0, $fixedDays - $hkA));

        $month1End  = Carbon::parse($period->start_date)->endOfMonth()->toDateString();
        $month2Start = Carbon::parse($period->end_date)->startOfMonth()->toDateString();

        $segments = [
            ['segment' => 'A', 'start' => $period->start_date->toDateString(), 'end' => $month1End,  'hk' => $hkA],
            ['segment' => 'B', 'start' => $month2Start,                          'end' => $period->end_date->toDateString(), 'hk' => $hkB],
        ];

        if ($segment) {
            $segments = array_values(array_filter($segments, fn ($s) => $s['segment'] === $segment));
        }

        return $segments;
    }

    /** Tambahkan ExtraEmployee ke koleksi records (masuk ke All-In). */
    private function appendExtraEmployees(Collection $records): Collection
    {
        $extraEmployees = ExtraEmployee::all();
        foreach ($extraEmployees as $emp) {
            $g = $emp->komponen_gaji ?? [];
            $records->push([
                'id'             => 'ext-' . $emp->id,
                'employee_id'    => $emp->id,
                'employee_code'  => $emp->kode ?? '-',
                'name'           => $emp->nama ?? '-',
                'department'     => '-',
                'position'       => '-',
                'gender'         => $emp->gender ?? '-',
                'join_year'      => '-',
                'groups'         => ['GRP-EXTRA'],
                'bank_name'      => '-',
                'bank_account_number' => $emp->account ?? '-',
                'bank_account_name'   => $emp->nama ?? '-',
                'bank_cabang'    => '-',
                'notes'          => '',
                'gaji_pokok'     => (float) ($g['gaji_pokok'] ?? 0),
                'premi'          => (float) ($g['premi'] ?? 0),
                'tj_masa_kerja'  => (float) ($g['tj_mk'] ?? 0),
                'tunjangan'      => (float) ($g['tunjangan'] ?? 0),
                'hari_kerja'     => 0,
                'lm'             => 0,
                'lm_count'       => 0,
                'lembur_count'   => 0,
                'gaji'           => 0,
                'upah_lembur'    => 0,
                'revisi'         => 0,
                'premi_hadir'    => 0,
                'pblt'           => 0,
                'total'          => (float) ($g['total_gaji'] ?? 0),
                'bpjs_tk'        => 0,
                'bpjs_ks'        => 0,
                'bpjs_pen'       => 0,
                'cashbon'        => (float) ($g['cashbon'] ?? 0),
                'pph'            => (float) ($g['ttl_pph'] ?? 0),
                'gaji_bersih'    => (float) ($g['total_terima'] ?? 0),
            ]);
        }

        return $records;
    }
}
