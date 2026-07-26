<?php

namespace App\Modules\Reports\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\EmployeeOvertime;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeReserve;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Reports\Helpers\Lembur\AllInHelper;
use App\Modules\Reports\Helpers\Lembur\JakartaHelper;
use App\Modules\Reports\Helpers\Lembur\PrintingHelper;
use App\Modules\Reports\Helpers\Lembur\SpcHelper;
use App\Modules\Reports\Helpers\Lembur\TknHelper;
use App\Modules\Settings\Services\ReportConfigService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LemburUangMakanUpdateService
{
    /**
     * Update data lembur & uang makan untuk periode tertentu.
     * Baca dari att_prepares → proses per helper → 1 record per tanggal per karyawan.
     */
    public function update(int $periodId): array
    {
        $period = PayPeriod::findOrFail($periodId);
        $startDate = Carbon::parse($period->start_date);
        $endDate   = Carbon::parse($period->end_date);
        $today     = Carbon::today();

        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // ── Generate date list ────────────────────────────────────
        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = $d->format('Y-m-d');
            $d->addDay();
        }

        // ── Config ─────────────────────────────────────────────────
        $config = app(ReportConfigService::class)->getConfig('lembur_uang_makan');

        // ── Fetch data ─────────────────────────────────────────────
        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when(!empty($config['employee_groups'] ?? []), fn($q) =>
                $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $config['employee_groups']))
            )
            ->with(['position', 'groups', 'groups.master'])
            ->get();

        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->get()->groupBy('employee_id');

        $rosters = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->with('shift')->get()->groupBy('employee_id');

        $payRecords = PayRecord::where('pay_period_id', $period->id)
            ->get()->groupBy('employee_id');

        $employeeReserves = EmployeeReserve::where('pay_periode_id', $period->id)
            ->get()->keyBy('employee_id');

        // ── Helpers ────────────────────────────────────────────────
        $jakartaHelper  = new JakartaHelper();
        $allInHelper    = new AllInHelper();
        $printingHelper = new PrintingHelper();
        $spcHelper      = new SpcHelper();
        $tknHelper      = new TknHelper();

        // ── Process & save dalam transaction ──────────────────────
        $inserts = [];
        $now = now();

        foreach ($employees as $employee) {
            $empPrepares  = $prepares->get($employee->id, collect())->keyBy(fn($p) => $p->date->format('Y-m-d'));
            $empRosters   = $rosters->get($employee->id, collect())->keyBy(fn($r) => $r->date->format('Y-m-d'));
            $empPayRecord = $payRecords->get($employee->id)?->first();
            $reserve      = $employeeReserves->get($employee->id);

            // ── Salary data ────────────────────────────────────────
            $gaji      = $empPayRecord ? (float)($empPayRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk      = $empPayRecord ? (float)($empPayRecord->tj_masa_kerja ?? 0)
                : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($startDate->format('Y-m'));
            }
            $tunjangan = $empPayRecord ? (float)($empPayRecord->tunjangan ?? 0)
                : (float)($employee->activeSalary()?->tunjangan ?? 0);

            // ── Klasifikasi & proses ──────────────────────────────
            $item = null;

            // SPC: cek shouldShow dengan period ID
            $showSpc = false;
            try {
                $showSpc = SpcHelper::shouldShow($period->id);
            } catch (\Exception $e) {
                $showSpc = false;
            }

            if (TknHelper::matches($employee)) {
                $item = $tknHelper->processEmployee($employee, $empPrepares, $empRosters, $empPayRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
            } elseif ($showSpc && SpcHelper::matches($employee)) {
                $item = $spcHelper->processEmployee($employee, $empPrepares, $empRosters, $empPayRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
            } elseif (JakartaHelper::matches($employee)) {
                $item = $jakartaHelper->processEmployee($employee, $empPrepares, $empRosters, $empPayRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
            } elseif (PrintingHelper::matches($employee)) {
                $item = $printingHelper->processEmployee($employee, $empPrepares, $empRosters, $empPayRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
            } elseif ($employee->groups->contains(fn($g) => $g->reference_code === 'GRP-SPR')) {
                // SOPIR — perlakuan khusus
                $item = $printingHelper->processEmployee($employee, $empPrepares, $empRosters, $empPayRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
                foreach ($item['days'] as $dateStr => &$day) {
                    $roster = $empRosters->get($dateStr);
                    $isHoliday = $roster && $roster->is_holiday;
                    $dayOfWeek = Carbon::parse($dateStr)->dayOfWeek;
                    if ($dayOfWeek !== 0 && !$isHoliday) {
                        $day['kode'] = '';
                        $day['ha'] = '-';
                        $day['upah_per_hari'] = 0;
                        $day['lm'] = '';
                        $day['lembur'] = '';
                        $day['nominal'] = 0;
                        $day['overtime_nominal'] = 0;
                        $day['uang_makan'] = 0;
                    } else {
                        $day['uang_makan'] = 0;
                    }
                }
                unset($day);
                $item['_is_spr'] = true;
            } else {
                $item = $allInHelper->processEmployee($employee, $empPrepares, $empRosters, $empPayRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
            }

            if (!$item) continue;

            // Insentif dari EmployeeReserves (per-period, disimpan di komponen hari pertama)
            $insentifDetail = [];
            $totalInsentif = 0;
            if ($reserve && $reserve->komponen) {
                $insentifDetail = $reserve->komponen;
                $totalInsentif = collect($insentifDetail)->sum(fn($k) => (float)($k['nilai'] ?? 0));
            }

            // ── 1 record per tanggal ───────────────────────────────
            $insentifAlreadyAdded = false;

            foreach ($item['days'] as $dateStr => $day) {
                $ha         = $day['ha'] ?? '-';
                $kode       = $day['kode'] ?? '';
                $lmRaw      = $day['lm'] ?? 0;
                $lemburRaw  = $day['lembur'] ?? 0;
                $nominalDay = (float)($day['nominal'] ?? 0);
                $umDay      = (float)($day['uang_makan'] ?? 0);
                $upahHarian = (float)($day['upah_per_hari'] ?? 0);

                // UM Code: deteksi string SEBELUM cast ke float
                // Jakarta/AllIn helper return string code: 'FULL','HALF','UM','2','TKN'
                // Printing/SPC helper return angka float
                $umCode = null;
                if (is_string($lmRaw) && $lmRaw !== '') {
                    $umCode = $lmRaw;
                } elseif (is_string($lemburRaw) && $lemburRaw !== '') {
                    $umCode = $lemburRaw;
                }

                // Total jam lembur: kalau string → 0 (kode UM bukan jam), kalau angka → nilai
                $lmJam     = is_numeric($lmRaw) ? (float)$lmRaw : 0;
                $lemburJam = is_numeric($lemburRaw) ? (float)$lemburRaw : 0;
                $totalJam  = $lmJam + $lemburJam;

                // Simpan raw string di komponen (bukan hasil cast)
                $lmKomponen     = is_string($lmRaw) ? $lmRaw : (float)$lmRaw;
                $lemburKomponen = is_string($lemburRaw) ? $lemburRaw : (float)$lemburRaw;

                // Overtime nominal dari helper (uang lembur berdasarkan jam)
                $overtimeNominalDay = (float)($day['overtime_nominal'] ?? 0);

                // Insentif: taruh di tanggal pertama aja yg ada data
                $insentifDay = 0;
                $insentifAdded = false;
                if (!$insentifAlreadyAdded && $totalInsentif > 0) {
                    $insentifDay = $totalInsentif;
                    $insentifAlreadyAdded = true;
                    $insentifAdded = true;
                }

                // Komponen JSON: detail hari ini
                $komponen = [
                    'kode'              => $kode,
                    'ha'                => $ha,
                    'upah_harian'       => $upahHarian,
                    'lm'                => $lmKomponen,
                    'lembur'            => $lemburKomponen,
                    'nominal'           => $nominalDay,
                    'uang_makan'        => $umDay,
                    'overtime_nominal'  => $overtimeNominalDay,
                    'insentif'          => $insentifDetail,
                    'insentif_ditambahkan' => $insentifAdded,
                ];

                $inserts[] = [
                    'uuid'           => (string) \Illuminate\Support\Str::uuid(),
                    'autolog_id'     => null,
                    'employee_id'    => $employee->id,
                    'pay_periode_id' => $period->id,
                    'date'           => $dateStr,
                    'lembur'         => round($totalJam, 2),
                    'lembur_hitung'  => round($nominalDay, 2),
                    'um_code'        => $umCode,
                    'nominal'        => round($umDay, 2),
                    'insentif'       => round($insentifDay, 2),
                    'komponen'       => json_encode($komponen),
                    'created_by'     => auth()->id(),
                    'updated_by'     => auth()->id(),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
        }

        // ── Simpan ────────────────────────────────────────────────
        DB::transaction(function () use ($period, $inserts) {
            // Hapus data lama untuk periode ini (hard delete — biar unique index ga bentrok)
            EmployeeOvertime::where('pay_periode_id', $period->id)->forceDelete();

            // Insert batch
            if (!empty($inserts)) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    EmployeeOvertime::insert($chunk);
                }
            }
        });

        return [
            'success'    => true,
            'period_id'  => $period->id,
            'period'     => $period->name,
            'total_rows' => count($inserts),
            'message'    => "Data lembur & uang makan periode {$period->name} berhasil diupdate (" . count($inserts) . " baris)",
        ];
    }
}