<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\EmployeeOvertime;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Services\ReportConfigService;
use App\Modules\Reports\Exports\UangMakanHarianExport;
use App\Modules\Reports\Exports\UangMakanBulananExport;
use App\Modules\Reports\Exports\UangMakanResumeExport;
use App\Modules\Reports\Exports\UangMakanRekapExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UangMakanReportController extends Controller
{
    // ─── Daily ────────────────────────────────────────────────────

    public function harian(Request $request)
    {
        $result = $this->buildHarianData($request);
        return response()->json($result);
    }

    public function exportHarian(Request $request)
    {
        $result = $this->buildHarianData($request);
        $filename = 'Uang_Makan_Harian_' . str_replace(' ', '_', $result['date_label']) . '.xlsx';
        return Excel::download(
            new UangMakanHarianExport($result['data']->toArray(), $result['dates'], $result['date_label']),
            $filename
        );
    }

    public function printHarian(Request $request)
    {
        $result = $this->buildHarianData($request);
        $html = $this->renderHarianPrintHtml($result);
        return response($html);
    }

    // ─── Monthly (Daily Detail) ────────────────────────────────────

    public function bulanan(Request $request)
    {
        $result = $this->buildBulananData($request);
        return response()->json($result);
    }

    public function exportBulanan(Request $request)
    {
        $result = $this->buildBulananData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : 'Laporan';
        $filename = 'Uang_Makan_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new UangMakanBulananExport($result['data']->toArray(), $result['dates'], $label),
            $filename
        );
    }

    public function printBulanan(Request $request)
    {
        $result = $this->buildBulananData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : 'Laporan';
        $html = $this->renderBulananPrintHtml($result, $label);
        return response($html);
    }

    // ─── Resume ────────────────────────────────────────────────────

    public function resume(Request $request)
    {
        $result = $this->buildResumeData($request);
        return response()->json($result);
    }

    public function exportResume(Request $request)
    {
        $result = $this->buildResumeData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : 'Resume';
        $filename = 'Resume_Uang_Makan_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new \App\Modules\Reports\Exports\ResumeExport($result['data'], $result['dates'], $label, 'RESUME UANG MAKAN'),
            $filename
        );
    }

    public function printResume(Request $request)
    {
        $result = $this->buildResumeData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : 'Resume';
        $html = $this->renderResumePrintHtml($result, $label);
        return response($html);
    }

    // ─── Rekap (Rekapitulasi per Karyawan per Bulan) ──────────────

    public function rekap(Request $request)
    {
        return response()->json($this->buildRekapPayload($request));
    }

    /**
     * Payload rekap uang makan (dipakai bersama oleh endpoint JSON dan export Excel,
     * termasuk "Export Lengkap" di laporan payroll).
     *
     * @return array{data: \Illuminate\Support\Collection, month_label: string, dates: array}
     */
    public function buildRekapPayload(Request $request): array
    {
        $result = $this->buildRekapData($request);

        $employees = $result['data']->map(function ($item) {
            // Determine employee eligibility based on employment_status and group codes
            $empStatus = strtoupper($item['employment_status'] ?? '');
            $groupCodes = $item['group_codes'] ?? [];

            $isKrySpc = str_contains($empStatus, 'SPC') || in_array('KRY-SPC', $groupCodes);
            $isKryTkn = str_contains($empStatus, 'TKN') || in_array('KRY-TKN', $groupCodes);

            $inSpr = in_array('GRP-SPR', $groupCodes);
            $inPs1 = in_array('GRP-PS1', $groupCodes);
            $inSs  = in_array('GRP-SS', $groupCodes);

            // Eligibility flags
            if ($isKrySpc) {
                $getsUangMakan = false;
            } elseif ($isKryTkn) {
                $getsUangMakan = false;
            } elseif ($inSpr) {
                $getsUangMakan = true;
            } elseif ($inPs1 || $inSs) {
                $getsUangMakan = false;
            } else {
                // Default: GRP-JKT, GRP-ALLIN, GRP-GD
                $getsUangMakan = true;
            }

            $counts = ['UM' => 0, '2' => 0, 'FULL' => 0, 'HALF' => 0, 'FULL_D' => 0];
            $nominals = [
                'uang_makan'     => 0,
                'lembur_sabtu'   => 0,
                'lembur_minggu'  => 0,
                'insentif'       => 0,
                'pblt'           => 0,
                'revisi'         => 0,
            ];

            $days = $item['days'] ?? [];

            foreach ($days as $dateStr => $day) {
                $lemburVal = $day['lembur'] ?? '';
                $lmVal     = $day['lm'] ?? '';
                $nominal   = (float)($day['nominal'] ?? 0);

                // Overtime counts & nominals — berdasarkan data, bukan eligibility
                // (um_code='' untuk SPR/PS1/SS/SPC, jadi tidak akan masuk hitungan)
                if ($lemburVal === '2') {
                    $counts['2']++;
                    $nominals['lembur_sabtu'] += $nominal;
                } elseif ($lemburVal === 'FULL') {
                    $counts['FULL']++;
                    $nominals['lembur_sabtu'] += $nominal;
                }

                if ($lmVal === 'HALF') {
                    $counts['HALF']++;
                    $nominals['lembur_minggu'] += $nominal;
                } elseif ($lmVal === 'FULL') {
                    $counts['FULL_D']++;
                    $nominals['lembur_minggu'] += $nominal;
                }

                // Uang makan — for eligible employees only
                if ($getsUangMakan && $lemburVal === 'UM') {
                    $counts['UM']++;
                    $nominals['uang_makan'] += $nominal;
                }

                // KRY-SPC / KRY-TKN: semua nominal masuk ke insentif
                if (($isKrySpc || $isKryTkn) && $nominal > 0) {
                    $nominals['insentif'] += $nominal;
                }
            }

            // Insentif dari employee_overtime pada end_date periode (non-SPC, non-TKN)
            if (!$isKrySpc && !$isKryTkn) {
                $nominals['insentif'] = (float)($item['insentif_end'] ?? 0);
            }

            $total = $nominals['uang_makan'] + $nominals['lembur_sabtu']
                   + $nominals['lembur_minggu'] + $nominals['insentif']
                   + $nominals['pblt'] + $nominals['revisi'];

            $gender = $item['gender'] === 'male' ? 'L'
                : ($item['gender'] === 'female' ? 'P' : ($item['gender'] ?? ''));

            return [
                'id'             => $item['id'],
                'name'           => $item['name'],
                'jabatan'        => $item['jabatan'],
                'gender'         => $gender,
                'group_name'     => $item['group_name'] ?? '-',
                'counts'         => $counts,
                'nominals'       => $nominals,
                'total'          => $total,
            ];
        })->values();

        // Sort by group name then by employee name
        $employees = $employees->sortBy([
            ['group_name', 'asc'],
            ['name', 'asc'],
        ])->values();

        return [
            'data'         => $employees,
            'month_label'  => $result['month_label'] ?? '',
            'dates'        => $result['dates'] ?? [],
        ];
    }

    public function exportRekap(Request $request)
    {
        $payload  = $this->buildRekapPayload($request);
        $label    = $payload['month_label'] ?? 'Rekap';

        $filename = 'Rekap_Uang_Makan_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new UangMakanRekapExport($payload['data'] ?? [], $label),
            $filename
        );
    }

    public function printRekap(Request $request)
    {
        $payload  = $this->buildRekapPayload($request);
        $label    = $payload['month_label'] ?? 'Rekap Uang Makan';
        $data     = $payload['data'] ?? [];

        $html = $this->renderRekapPrintHtml($data, $label);
        return response($html);
    }

    // ─── Rekap Resume (per Bagian) ────────────────────────────────

    public function rekapResume(Request $request)
    {
        return response()->json($this->buildRekapResumePayload($request));
    }

    /**
     * Rekap uang makan dikelompokkan per bagian/jabatan — dipakai endpoint JSON
     * dan blok "RESUME UANG MAKAN & LEMBUR" pada Export Lengkap.
     *
     * @return array{data: array, month_label: string}
     */
    public function buildRekapResumePayload(Request $request): array
    {
        $payload   = $this->buildRekapPayload($request);
        $employees = $payload['data'] ?? [];

        $grouped = [];
        foreach ($employees as $emp) {
            $bagian = $emp['jabatan'] ?? '-';
            if (!isset($grouped[$bagian])) {
                $grouped[$bagian] = [
                    'bagian'        => $bagian,
                    'uang_makan'    => 0,
                    'lembur_sabtu'  => 0,
                    'lembur_minggu' => 0,
                    'insentif'      => 0,
                    'pblt'          => 0,
                    'revisi'        => 0,
                    'total'         => 0,
                ];
            }
            $n = $emp['nominals'] ?? [];
            $grouped[$bagian]['uang_makan']    += $n['uang_makan'] ?? 0;
            $grouped[$bagian]['lembur_sabtu']  += $n['lembur_sabtu'] ?? 0;
            $grouped[$bagian]['lembur_minggu'] += $n['lembur_minggu'] ?? 0;
            $grouped[$bagian]['insentif']      += $n['insentif'] ?? 0;
            $grouped[$bagian]['pblt']          += $n['pblt'] ?? 0;
            $grouped[$bagian]['revisi']        += $n['revisi'] ?? 0;
            $grouped[$bagian]['total']         += $emp['total'] ?? 0;
        }

        $result = array_values($grouped);
        usort($result, fn($a, $b) => strcmp($a['bagian'], $b['bagian']));

        foreach ($result as &$r) {
            foreach (['uang_makan','lembur_sabtu','lembur_minggu','insentif','pblt','revisi'] as $k) {
                $r[$k] = round($r[$k], 2);
            }
            // total tidak dibulatkan — nilai asli (ditampilkan 2 desimal di frontend)
        }

        return [
            'data'         => $result,
            'month_label'  => $payload['month_label'] ?? '',
        ];
    }

    public function exportRekapResume(Request $request)
    {
        $payload  = $this->buildRekapResumePayload($request);
        $label    = $payload['month_label'] ?? 'Resume';

        $filename = 'Resume_Uang_Makan_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new \App\Modules\Reports\Exports\UangMakanRekapResumeExport($payload['data'] ?? [], $label),
            $filename
        );
    }

    public function printRekapResume(Request $request)
    {
        $payload  = $this->buildRekapResumePayload($request);
        $label    = $payload['month_label'] ?? 'Resume Uang Makan';
        $data     = $payload['data'] ?? [];

        $html = $this->renderRekapResumePrintHtml($data, $label);
        return response($html);
    }

    // ─── Data Builders ────────────────────────────────────────────

    private function buildHarianData(Request $request)
    {
        $startDateInput = $request->input('start_date', date('Y-m-d'));
        $endDateInput = $request->input('end_date', date('Y-m-d'));
        $groups = $request->input('groups', []);

        $startDate = Carbon::parse($startDateInput);
        $endDate = Carbon::parse($endDateInput);

        if ($startDate->equalTo($endDate)) {
            $label = $startDate->translatedFormat('d M Y');
        } else {
            $label = $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y');
        }

        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = $d->format('Y-m-d');
            $d->addDay();
        }

        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->get()->groupBy('employee_id');

        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['position', 'groups.master'])
            ->get();

        $rosters = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->with('shift')->get()
            ->groupBy('employee_id');

        $period = PayPeriod::where('start_date', '<=', $startDate)
            ->where('end_date', '>=', $startDate)->first();

        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)->get()->groupBy('employee_id');
        }

        $data = $employees->map(function ($employee) use ($prepares, $rosters, $payRecords, $period, $dates, $startDate) {
            $empPrepares = $prepares->get($employee->id, collect())->keyBy(fn($p) => $p->date->format('Y-m-d'));
            $empRosters  = $rosters->get($employee->id, collect())->keyBy(fn($r) => $r->date->format('Y-m-d'));
            $empPayRecords = $payRecords->get($employee->id);

            $payRecord = null;
            if ($empPayRecords && $empPayRecords->isNotEmpty()) {
                if ($period && $period->is_split) {
                    $day = (int) $startDate->day;
                    $cutOff = (int) ($period->cut_off_date ?? 25);
                    $segment = ($day >= $cutOff && $day <= 31) ? 'A' : 'B';
                    $payRecord = $empPayRecords->where('segment', $segment)->first() ?? $empPayRecords->first();
                } else {
                    $payRecord = $empPayRecords->first();
                }
            }

            $gaji = $payRecord ? (float)($payRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk = $payRecord ? (float)($payRecord->tj_masa_kerja ?? 0) : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($startDate->format('Y-m'));
            }
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);
            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            $groupName = $this->getGroupName($employee);
            $rates = $this->getGroupRates($groupName, $gaji);
            $upahPerHariValue = ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;

            $days = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);

                $lembur = 0;
                $statusRaw = $prep ? $prep->status : '-';
                $isHoliday = $roster && $roster->is_holiday;
                $dayOfWeek = $parsedDate->dayOfWeek;

                if ($prep) {
                    $lemburMinutes = ($prep->lm ?? 0) + ($prep->overtime ?? 0);
                    $lembur = round($lemburMinutes / 60, 2);
                }

                $dayInfo = $this->buildDayInfo($lembur, $dayOfWeek, $isHoliday, $rates, $statusRaw, $prep, $upahPerHariValue);

                $days[$dateStr] = [
                    'kode'     => $dayInfo['kode'],
                    'ha'       => $dayInfo['ha'],
                    'upah_per_hari' => $dayInfo['upah_per_hari'],
                    'lm'       => $dayInfo['lm'],
                    'lembur'   => $dayInfo['lembur'],
                    'nominal'  => $dayInfo['nominal'],
                ];
            }

            return [
                'id'                  => $employee->id,
                'name'                => $employee->name,
                'jabatan'             => $employee->position->name ?? '-',
                'gender'              => $employee->gender ?? '',
                'tj_mk'               => $tjMk,
                'tunjangan'           => $tunjangan,
                'upah_lembur_per_jam' => $hourlyRate,
                'days'                => $days,
                'group_name'          => $groupName,
            ];
        })->values();

        return [
            'data'       => $data,
            'dates'      => $dates,
            'date_label' => $label,
        ];
    }

    private function buildBulananData(Request $request)
    {
        $periodId = $request->input('period_id');
        $groups   = $request->input('groups', []);

        $period = PayPeriod::find($periodId);

        if ($period) {
            $startDate = Carbon::parse($period->start_date);
            $endDate   = Carbon::parse($period->end_date);
            $label     = $period->name . ' (' . $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y') . ')';
        } else {
            $startDate = Carbon::now()->startOfMonth();
            $endDate   = Carbon::now()->endOfMonth();
            $label     = $startDate->translatedFormat('F Y');
        }

        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = $d->format('Y-m-d');
            $d->addDay();
        }

        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->get()->groupBy('employee_id');

        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['position', 'groups.master'])
            ->get();

        $rosters = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->with('shift')->get()
            ->groupBy('employee_id');

        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)->get()->groupBy('employee_id');
        }

        $data = $employees->map(function ($employee) use ($prepares, $rosters, $payRecords, $period, $dates, $startDate) {
            $empPrepares = $prepares->get($employee->id, collect())->keyBy(fn($p) => $p->date->format('Y-m-d'));
            $empRosters  = $rosters->get($employee->id, collect())->keyBy(fn($r) => $r->date->format('Y-m-d'));
            $empPayRecords = $payRecords->get($employee->id);

            $payRecord = null;
            if ($empPayRecords && $empPayRecords->isNotEmpty()) {
                $payRecord = $empPayRecords->first();
            }
            $gaji      = $payRecord ? (float)($payRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk      = $payRecord ? (float)($payRecord->tj_masa_kerja ?? 0) : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($startDate->format('Y-m'));
            }
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);
            $hourlyRate  = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            $groupName = $this->getGroupName($employee);
            $rates = $this->getGroupRates($groupName, $gaji);

            // Upah/Hari = (gaji_pokok + tj_mk) / 25
            $upahPerHariValue = ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;

            $days = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);

                $lembur = 0;
                $statusRaw = $prep ? $prep->status : '-';
                $isHoliday = $roster && $roster->is_holiday;
                $dayOfWeek = $parsedDate->dayOfWeek;

                if ($prep) {
                    $lemburMinutes = ($prep->lm ?? 0) + ($prep->overtime ?? 0);
                    $lembur = round($lemburMinutes / 60, 2);
                }

                $dayInfo = $this->buildDayInfo($lembur, $dayOfWeek, $isHoliday, $rates, $statusRaw, $prep, $upahPerHariValue);

                $days[$dateStr] = [
                    'kode'     => $dayInfo['kode'],
                    'ha'       => $dayInfo['ha'],
                    'upah_per_hari' => $dayInfo['upah_per_hari'],
                    'lm'       => $dayInfo['lm'],
                    'lembur'   => $dayInfo['lembur'],
                    'nominal'  => $dayInfo['nominal'],
                ];
            }

            return [
                'id'                  => $employee->id,
                'name'                => $employee->name,
                'jabatan'             => $employee->position->name ?? '-',
                'gender'              => $employee->gender ?? '',
                'tj_mk'               => $tjMk,
                'tunjangan'           => $tunjangan,
                'upah_lembur_per_jam' => $hourlyRate,
                'group_name'          => $groupName,
                'employment_status'   => $employee->employment_status ?? '',
                'group_codes'         => $employee->groups->pluck('reference_code')->toArray(),
                'days'                => $days,
            ];
        })->values();

        return [
            'data'         => $data,
            'dates'        => $dates,
            'month_label'  => $label,
        ];
    }

    /**
     * Build data for Rekap Uang Makan from employee_overtime table.
     */
    private function buildRekapData(Request $request): array
    {
        $periodId = $request->input('period_id');
        $groups   = $request->input('groups', []);

        $period = PayPeriod::find($periodId);

        if ($period) {
            $startDate = Carbon::parse($period->start_date);
            $endDate   = Carbon::parse($period->end_date);
            $label     = $period->name . ' (' . $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y') . ')';
        } else {
            $startDate = Carbon::now()->startOfMonth();
            $endDate   = Carbon::now()->endOfMonth();
            $label     = $startDate->translatedFormat('F Y');
        }

        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = $d->format('Y-m-d');
            $d->addDay();
        }

        // Query employee_overtime by period
        $overtimes = EmployeeOvertime::where('pay_periode_id', $periodId)
            ->get()
            ->groupBy('employee_id');

        // Employees that have shift roster in this period (same filter as buildBulananData)
        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['position', 'groups.master'])
            ->get();

        $data = $employees->map(function ($employee) use ($overtimes, $dates, $endDate) {
            $empOvertimes = $overtimes->get($employee->id, collect())->keyBy(fn($o) => $o->date->format('Y-m-d'));

            $groupName = $this->getGroupName($employee);

            // Insentif dari record end_date periode
            $endDateStr = $endDate instanceof Carbon ? $endDate->format('Y-m-d') : $endDate;
            $insentifEnd = 0;
            $endOvt = $empOvertimes->get($endDateStr);
            if ($endOvt) {
                $insentifEnd = (float)($endOvt->insentif ?? 0);
            }

            $days = [];
            foreach ($dates as $dateStr) {
                $ovt = $empOvertimes->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);
                $dayOfWeek = $parsedDate->dayOfWeek; // 0=Minggu, 6=Sabtu

                $lemburStr = '';
                $lmStr = '';
                $nominal = 0;

                if ($ovt) {
                    $umCode = $ovt->um_code ?? '';
                    $nominal = (float)($ovt->nominal ?? 0);

                    switch ($umCode) {
                        case 'UM':
                            $lemburStr = 'UM';
                            break;
                        case '2':
                            $lemburStr = '2';
                            break;
                        case 'FULL':
                            // Sabtu (6) → lemburStr, Minggu/Holiday (0) → lmStr
                            if ($dayOfWeek == 6) {
                                $lemburStr = 'FULL';
                            } else {
                                $lmStr = 'FULL';
                            }
                            break;
                        case 'HALF':
                            $lmStr = 'HALF';
                            break;
                        // TKN, SPR, PS1, SS, SPC — um_code empty or 'TKN', no count
                        // matches existing rekap() behaviour (not counted as UM/2/FULL/HALF)
                    }
                }

                $days[$dateStr] = [
                    'kode'          => $lemburStr !== '' ? 'L' : '',
                    'ha'            => '-',
                    'upah_per_hari' => 0,
                    'lm'            => $lmStr,
                    'lembur'        => $lemburStr,
                    'nominal'       => $nominal,
                ];
            }

            return [
                'id'                  => $employee->id,
                'name'                => $employee->name,
                'jabatan'             => $employee->position->name ?? '-',
                'gender'              => $employee->gender ?? '',
                'tj_mk'               => 0,
                'tunjangan'           => 0,
                'upah_lembur_per_jam' => 0,
                'group_name'          => $groupName,
                'employment_status'   => $employee->employment_status ?? '',
                'group_codes'         => $employee->groups->pluck('reference_code')->toArray(),
                'insentif_end'        => $insentifEnd,
                'days'                => $days,
            ];
        })->values();

        return [
            'data'         => $data,
            'dates'        => $dates,
            'month_label'  => $label,
        ];
    }

    private function buildResumeData(Request $request)
    {
        $periodId = $request->input('period_id');
        $groups   = $request->input('groups', []);

        $period = PayPeriod::find($periodId);
        if ($period) {
            $startDate = Carbon::parse($period->start_date);
            $endDate   = Carbon::parse($period->end_date);
            $label     = $period->name . ' (' . $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y') . ')';
        } else {
            $startDate = Carbon::now()->startOfMonth();
            $endDate   = Carbon::now()->endOfMonth();
            $label     = $startDate->translatedFormat('F Y');
        }

        $today = Carbon::today();
        if ($endDate->gt($today)) {
            $endDate = $today;
        }

        // Generate list of dates
        $dates = [];
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            $dates[] = $d->format('Y-m-d');
            $d->addDay();
        }

        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->get()->groupBy('employee_id');

        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['position', 'groups.master'])
            ->get();

        $rosters = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->with('shift')->get()
            ->groupBy('employee_id');

        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)->get()->groupBy('employee_id');
        }

        // Compute per-employee per-date upah & nominal
        $employeeData = [];
        foreach ($employees as $employee) {
            $empPrepares = $prepares->get($employee->id, collect())->keyBy(fn($p) => $p->date->format('Y-m-d'));
            $empRosters  = $rosters->get($employee->id, collect())->keyBy(fn($r) => $r->date->format('Y-m-d'));
            $empPayRecords = $payRecords->get($employee->id);

            $payRecord = null;
            if ($empPayRecords && $empPayRecords->isNotEmpty()) {
                $payRecord = $empPayRecords->first();
            }
            $gaji      = $payRecord ? (float)($payRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk      = $payRecord ? (float)($payRecord->tj_masa_kerja ?? 0) : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($startDate->format('Y-m'));
            }
            $groupName = $this->getGroupName($employee);
            $rates = $this->getGroupRates($groupName, $gaji);
            $upahPerHariValue = ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;

            $posName = $employee->position->name ?? '-';

            $perDate = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);

                $lembur = 0;
                $statusRaw = $prep ? $prep->status : '-';
                $isHoliday = $roster && $roster->is_holiday;
                $dayOfWeek = $parsedDate->dayOfWeek;

                if ($prep) {
                    $lemburMinutes = ($prep->lm ?? 0) + ($prep->overtime ?? 0);
                    $lembur = round($lemburMinutes / 60, 2);
                }

                $dayInfo = $this->buildDayInfo($lembur, $dayOfWeek, $isHoliday, $rates, $statusRaw, $prep, $upahPerHariValue);

                $perDate[$dateStr] = [
                    'hari_kerja' => $dayInfo['upah_per_hari'],
                    'overtime'   => $dayInfo['nominal'],
                ];
            }

            $employeeData[] = [
                'position' => $posName,
                'gender'   => $employee->gender ?? '',
                'per_date' => $perDate,
            ];
        }

        // Group by position and aggregate
        $posGroups = collect($employeeData)->groupBy('position');

        $data = [];
        foreach ($posGroups as $posName => $emps) {
            $l = $emps->where('gender', 'L')->count();
            $p = $emps->where('gender', 'P')->count();

            $days = [];
            $totalHariKerja = 0;
            $totalOvertime = 0;

            foreach ($dates as $dateStr) {
                $hariKerja = $emps->sum(fn($e) => $e['per_date'][$dateStr]['hari_kerja'] ?? 0);
                $overtime = $emps->sum(fn($e) => $e['per_date'][$dateStr]['overtime'] ?? 0);
                $days[$dateStr] = [
                    'hari_kerja' => round($hariKerja, 2),
                    'overtime'   => round($overtime, 2),
                ];
                $totalHariKerja += $hariKerja;
                $totalOvertime += $overtime;
            }

            $data[] = [
                'bagian'           => $posName,
                'l'                => $l,
                'p'                => $p,
                'days'             => $days,
                'total_hari_kerja' => round($totalHariKerja, 2),
                'total_overtime'   => round($totalOvertime, 2),
                'total_terima'     => round($totalHariKerja + $totalOvertime, 2),
            ];
        }

        // Sort by department name
        usort($data, fn($a, $b) => strcmp($a['bagian'], $b['bagian']));

        return [
            'data'         => $data,
            'dates'        => $dates,
            'period_label' => $label,
        ];
    }

    // ─── Shared Helpers ──────────────────────────────────────────

    private function getGroupName($employee): string
    {
        // Cari grup dengan master group_label = 'Uang Makan'
        // (jangan ->first() mentah — bisa kena grup non-Uang Makan)
        $group = $employee->groups->first(fn($g) =>
            $g->master && strtoupper($g->master->group_label ?? '') === 'UANG MAKAN'
        );
        return strtoupper($group?->master?->name ?? '');
    }

    private function getGroupRates(string $groupName, float $gajiPokok): array
    {
        $config = app(ReportConfigService::class)->getConfig('lembur_uang_makan');
        $upper = strtoupper($groupName);

        // Match with aliases (same logic as old hardcode)
        if (str_contains($upper, 'KABAG')) {
            return $config['KABAG'] ?? [];
        }
        if (str_contains($upper, 'KEPALA SHIFT') || str_contains($upper, 'KASHIFT')) {
            return $config['KASHIFT'] ?? [];
        }
        if (str_contains($upper, 'ALL IN') || str_contains($upper, 'ALL-IN')) {
            return $config['ALL IN'] ?? [];
        }

        // Default: weekday only, sabtu/minggu = 0
        return [
            'weekday'      => 15000,
            'sabtu_dua'    => 0,
            'sabtu_full'   => 0,
            'minggu_half'  => 0,
            'minggu_full'  => 0,
        ];
    }

    /**
     * Build per-day info for Uang Makan.
     *
     * Returns:
     *   kode   — 'L' if lembur>0 else ''
     *   ha     — status code (H/A/C/S/I/OFF)
     *   upah_per_hari — (gaji+tmk)/25, kosong utk I/A/OFF & Minggu/Holiday
     *   lm     — '2'/'FULL'/'HALF' for Minggu/Libur, '' otherwise
     *   lembur — 'UM'/'2'/'FULL' for Weekday/Sabtu, '' otherwise
     *   nominal — dari config weekday / rate sabtu / rate minggu
     */
    private function buildDayInfo(float $lembur, int $dayOfWeek, bool $isHoliday, array $rates, string $statusRaw, $prepare, float $upahPerHari): array
    {
        // H/A mapping
        $ha = match (true) {
            $statusRaw === 'hadir'          => 'H',
            $statusRaw === 'absent'         => 'A',
            $statusRaw === 'libur',
            $statusRaw === 'off'            => 'OFF',
            $statusRaw === 'skt'            => 'S',
            str_starts_with($statusRaw, 'c') => 'C',
            $statusRaw === 'imt',
            $statusRaw === 'ipa'            => 'H',
            str_starts_with($statusRaw, 'i') => 'I',
            default                         => $statusRaw === '-' ? '-' : 'I',
        };

        $kode = '';
        $upah = 0;
        $lm = '';
        $lemburStr = '';
        $nominal = 0;

        // Upah/Hari: (gaji+tmk)/25 — KECUALI I, A, OFF, atau Minggu/Holiday
        $isMingguHoliday = ($dayOfWeek == 0 || $isHoliday);
        $dapatUpah = !in_array($ha, ['I', 'A', 'OFF']) && !$isMingguHoliday;
        $upah = $dapatUpah ? $upahPerHari : 0;

        if ($lembur > 0) {
            $kode = 'L';

            if ($isMingguHoliday) {
                // Minggu / Libur — Upah/Hari tetap 0, no minimum threshold
                if ($lembur >= 8) {
                    $nominal = $rates['minggu_full'];
                    $lm = 'FULL';
                } elseif ($lembur > 0) {
                    $nominal = $rates['minggu_half'];
                    $lm = 'HALF';
                }
            } elseif ($dayOfWeek == 6) {
                // Sabtu — no minimum threshold
                if ($lembur >= 4) {
                    $nominal = $rates['sabtu_full'];
                    $lemburStr = 'FULL';
                } elseif ($lembur > 0) {
                    $nominal = $rates['sabtu_dua'];
                    $lemburStr = '2';
                }
            } else {
                // Weekday: >= 2 jam → UM, nominal dari config
                if ($lembur >= 2) {
                    $nominal = $rates['weekday'] ?? 15000;
                    $lemburStr = 'UM';
                }
            }
        }

        return [
            'kode'          => $kode,
            'ha'            => $ha,
            'upah_per_hari' => $upah,
            'lm'            => $lm,
            'lembur'        => $lemburStr,
            'nominal'       => $nominal,
        ];
    }

    // ─── Print HTML Renderers ──────────────────────────────────────

    private function renderHarianPrintHtml($result)
    {
        $data  = $result['data'];
        $dates = $result['dates'];
        $label = $result['date_label'];

        $dayHeaders1 = '';
        $dayHeaders2 = '';
        foreach ($dates as $dateStr) {
            $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
            $dayHeaders1 .= "<th colspan=\"6\" style='background:#dbeafe'>" . strtoupper($formatted) . "</th>";
            $dayHeaders2 .= "<th>Kode</th><th>H/A</th><th>Upah/Hari</th><th>L/M</th><th>Lbr</th><th>Nominal</th>";
        }

        $rows = '';
        $i = 0;
        foreach ($data as $item) {
            $i++;
            $gender = $item['gender'] === 'male' ? 'L' : ($item['gender'] === 'female' ? 'P' : ($item['gender'] ?? ''));
            $dayCells = '';
            foreach ($dates as $dateStr) {
                $d = $item['days'][$dateStr] ?? null;
                if ($d) {
                    $nominal = $d['nominal'] ? number_format($d['nominal'], 0, ',', '.') : '';
                    $upahHari = $d['upah_per_hari'] ? number_format($d['upah_per_hari'], 0, ',', '.') : '-';
                    $dayCells .= "<td>{$d['kode']}</td>"
                              . "<td>{$d['ha']}</td>"
                              . "<td class='text-right'>{$upahHari}</td>"
                              . "<td>" . ($d['lm'] ?: '-') . "</td>"
                              . "<td>" . ($d['lembur'] ?: '-') . "</td>"
                              . "<td class='text-right'>" . ($nominal ?: '-') . "</td>";
                } else {
                    $dayCells .= "<td>-</td><td>-</td><td class='text-right'>-</td><td>-</td><td>-</td><td class='text-right'>-</td>";
                }
            }
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['name']}</td><td>{$item['jabatan']}</td><td>{$gender}</td>
                <td class='text-right'>".number_format($item['tj_mk'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tunjangan'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['upah_lembur_per_jam'],0,',','.')."</td>
                {$dayCells}
            </tr>";
        }

        return '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Uang Makan Harian</title>
<style>
@page{size:A3 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:8px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:3px 4px;border:1px solid #d1d5db;font-size:7px;text-align:center}
td{padding:2px 4px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>LAPORAN UANG MAKAN HARIAN — ' . strtoupper($label) . '</h1>
<div style="overflow-x:auto">
<table><thead>
<tr>
<th rowspan="2">No</th><th rowspan="2">Nama</th><th rowspan="2">Bagian/Jabatan</th><th rowspan="2">L/P</th>
<th rowspan="2">Tj. MK</th><th rowspan="2">Tunjangan</th><th rowspan="2">Upah Lbr/Jam</th>
' . $dayHeaders1 . '</tr>
<tr>' . $dayHeaders2 . '</tr>
</thead><tbody>' . $rows . '</tbody></table>
</div>
<div style="text-align:center;margin-top:8px"><button onclick="window.print()" style="padding:6px 16px;font-size:12px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨 Print</button></div>
</body></html>';
    }

    private function renderBulananPrintHtml($result, $label)
    {
        $data  = $result['data'];
        $dates = $result['dates'];

        $dayHeaders1 = '';
        $dayHeaders2 = '';
        foreach ($dates as $dateStr) {
            $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
            $dayHeaders1 .= "<th colspan=\"6\" style='background:#dbeafe'>" . strtoupper($formatted) . "</th>";
            $dayHeaders2 .= "<th>Kode</th><th>H/A</th><th>Upah/Hari</th><th>L/M</th><th>Lbr</th><th>Nominal</th>";
        }

        $rows = '';
        $i = 0;
        foreach ($data as $item) {
            $i++;
            $gender = $item['gender'] === 'male' ? 'L' : ($item['gender'] === 'female' ? 'P' : ($item['gender'] ?? ''));
            $dayCells = '';
            foreach ($dates as $dateStr) {
                $d = $item['days'][$dateStr] ?? null;
                if ($d) {
                    $nominal = $d['nominal'] ? number_format($d['nominal'], 0, ',', '.') : '';
                    $upahHari = $d['upah_per_hari'] ? number_format($d['upah_per_hari'], 0, ',', '.') : '-';
                    $dayCells .= "<td>{$d['kode']}</td>"
                              . "<td>{$d['ha']}</td>"
                              . "<td class='text-right'>{$upahHari}</td>"
                              . "<td>" . ($d['lm'] ?: '-') . "</td>"
                              . "<td>" . ($d['lembur'] ?: '-') . "</td>"
                              . "<td class='text-right'>" . ($nominal ?: '-') . "</td>";
                } else {
                    $dayCells .= "<td>-</td><td>-</td><td class='text-right'>-</td><td>-</td><td>-</td><td class='text-right'>-</td>";
                }
            }
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['name']}</td><td>{$item['jabatan']}</td><td>{$gender}</td>
                <td class='text-right'>".number_format($item['tj_mk'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tunjangan'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['upah_lembur_per_jam'],0,',','.')."</td>
                {$dayCells}
            </tr>";
        }

        return '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Uang Makan Bulanan</title>
<style>
@page{size:A3 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:8px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:3px 4px;border:1px solid #d1d5db;font-size:7px;text-align:center}
td{padding:2px 4px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>LAPORAN UANG MAKAN BULANAN — ' . strtoupper($label) . '</h1>
<div style="overflow-x:auto">
<table><thead>
<tr>
<th rowspan="2">No</th><th rowspan="2">Nama</th><th rowspan="2">Bagian/Jabatan</th><th rowspan="2">L/P</th>
<th rowspan="2">Tj. MK</th><th rowspan="2">Tunjangan</th><th rowspan="2">Upah Lbr/Jam</th>
' . $dayHeaders1 . '</tr>
<tr>' . $dayHeaders2 . '</tr>
</thead><tbody>' . $rows . '</tbody></table>
</div>
<div style="text-align:center;margin-top:8px"><button onclick="window.print()" style="padding:6px 16px;font-size:12px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨 Print</button></div>
</body></html>';
    }

    private function renderResumePrintHtml($result, $label)
    {
        $data  = $result['data'];
        $dates = $result['dates'];

        $dayHeaders = '';
        foreach ($dates as $dateStr) {
            $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
            $dayHeaders .= '<th colspan="2" style="background:#dbeafe">' . strtoupper($formatted) . '</th>';
        }

        $subHeaders = '';
        foreach ($dates as $dateStr) {
            $subHeaders .= '<th>Hari Kerja</th><th>Overtime</th>';
        }

        $rows = '';
        $i = 0;
        foreach ($data as $item) {
            $i++;
            $dayCells = '';
            foreach ($dates as $dateStr) {
                $d = $item['days'][$dateStr] ?? ['hari_kerja' => 0, 'overtime' => 0];
                $hk = $d['hari_kerja'] ? number_format($d['hari_kerja'], 0, ',', '.') : '-';
                $ot = $d['overtime'] ? number_format($d['overtime'], 0, ',', '.') : '-';
                $dayCells .= "<td class='text-right'>{$hk}</td><td class='text-right'>{$ot}</td>";
            }
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['bagian']}</td>
                <td class='text-center'>{$item['l']}</td><td class='text-center'>{$item['p']}</td>
                {$dayCells}
                <td class='text-right'>".number_format($item['total_hari_kerja'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['total_overtime'],0,',','.')."</td>
                <td class='text-right'><strong>".number_format($item['total_terima'],0,',','.')."</strong></td>
            </tr>";
        }

        return '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Resume Uang Makan</title>
<style>
@page{size:A3 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:8px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:3px 4px;border:1px solid #d1d5db;font-size:7px;text-align:center}
td{padding:2px 4px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}.text-center{text-align:center}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>RESUME UANG MAKAN — ' . strtoupper($label) . '</h1>
<div style="overflow-x:auto">
<table><thead>
<tr>
<th rowspan="2">No</th><th rowspan="2">Bagian</th><th colspan="2">Jml Karyawan</th>
' . $dayHeaders . '
<th rowspan="2">Total Hari Kerja</th><th rowspan="2">Total Overtime</th><th rowspan="2">Total Terima</th>
</tr>
<tr>
<th>L</th><th>P</th>
' . $subHeaders . '
</tr>
</thead><tbody>' . $rows . '</tbody></table>
</div>
<div style="text-align:center;margin-top:8px"><button onclick="window.print()" style="padding:6px 16px;font-size:12px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨 Print</button></div>
</body></html>';
    }

    private function renderRekapPrintHtml($data, $label)
    {
        $rows = '';
        $i = 0;
        $totalDua = $totalFull = $totalHalf = $totalFullD = $totalUm = 0;
        $totalUmNominal = $totalLbrSabtu = $totalLbrMinggu = 0;
        $totalInsentif = $totalPblt = $totalRevisi = 0;
        $grandTotal = 0;

        foreach ($data as $item) {
            $i++;
            $c = $item['counts'] ?? [];
            $n = $item['nominals'] ?? [];
            $t = $item['total'] ?? 0;

            $totalDua   += $c['2'] ?? 0;
            $totalFull  += $c['FULL'] ?? 0;
            $totalHalf  += $c['HALF'] ?? 0;
            $totalFullD     += $c['FULL_D'] ?? 0;
            $totalUm    += $c['UM'] ?? 0;
            $totalUmNominal    += $n['uang_makan'] ?? 0;
            $totalLbrSabtu  += $n['lembur_sabtu'] ?? 0;
            $totalLbrMinggu += $n['lembur_minggu'] ?? 0;
            $totalInsentif   += $n['insentif'] ?? 0;
            $totalPblt       += $n['pblt'] ?? 0;
            $totalRevisi     += $n['revisi'] ?? 0;
            $grandTotal      += $t;

            $umCnt = ($c['UM'] ?? 0) ?: '-';
            $dua   = ($c['2'] ?? 0) ?: '-';
            $full  = ($c['FULL'] ?? 0) ?: '-';
            $half  = ($c['HALF'] ?? 0) ?: '-';
            $fulld = ($c['FULL_D'] ?? 0) ?: '-';
            $um    = ($n['uang_makan'] ?? 0) ? number_format($n['uang_makan'], 0, ',', '.') : '-';
            $ls    = ($n['lembur_sabtu'] ?? 0) ? number_format($n['lembur_sabtu'], 0, ',', '.') : '-';
            $lm    = ($n['lembur_minggu'] ?? 0) ? number_format($n['lembur_minggu'], 0, ',', '.') : '-';
            $ins   = ($n['insentif'] ?? 0) ? number_format($n['insentif'], 0, ',', '.') : '-';
            $pblt  = ($n['pblt'] ?? 0) ? number_format($n['pblt'], 0, ',', '.') : '-';
            $rev   = ($n['revisi'] ?? 0) ? number_format($n['revisi'], 0, ',', '.') : '-';
            $tot   = $t ? number_format($t, 2, ',', '.') : '-';

            $rows .= "<tr>
                <td>{$i}</td>
                <td>{$item['name']}</td>
                <td class='text-center'>" . ($item['group_name'] ?? '-') . "</td>
                <td>" . ($item['jabatan'] ?? '-') . "</td>
                <td class='text-center'>{$umCnt}</td>
                <td class='text-center'>{$dua}</td>
                <td class='text-center'>{$full}</td>
                <td class='text-center'>{$half}</td>
                <td class='text-center'>{$fulld}</td>
                <td class='text-right'>{$um}</td>
                <td class='text-right'>{$ls}</td>
                <td class='text-right'>{$lm}</td>
                <td class='text-right'>{$ins}</td>
                <td class='text-right'>{$pblt}</td>
                <td class='text-right'>{$rev}</td>
                <td class='text-right'><strong>{$tot}</strong></td>
            </tr>";
        }

        $gtFmt = $grandTotal ? number_format($grandTotal, 2, ',', '.') : '-';

        $totalRow = "<tr style='background:#f3f4f6;font-weight:bold;border-top:2px solid #6366f1'>
            <td colspan='4' class='text-right'>TOTAL</td>
            <td class='text-center'>{$totalUm}</td>
            <td class='text-center'>{$totalDua}</td>
            <td class='text-center'>{$totalFull}</td>
            <td class='text-center'>{$totalHalf}</td>
            <td class='text-center'>{$totalFullD}</td>
            <td class='text-right'>" . number_format($totalUmNominal, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalLbrSabtu, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalLbrMinggu, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalInsentif, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalPblt, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalRevisi, 0, ',', '.') . "</td>
            <td class='text-right' style='font-size:14px;color:#4f46e5'><strong>{$gtFmt}</strong></td>
        </tr>";

        return '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Rekap Uang Makan</title>
<style>
@page{size:A4 landscape;margin:8mm}
body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:9px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}
th{background:#e8eaed;font-weight:600;padding:4px 6px;border:1px solid #d1d5db;font-size:8px;text-align:center}
td{padding:3px 6px;border:1px solid #e5e7eb}
tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}.text-center{text-align:center}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>REKAP UANG MAKAN — ' . strtoupper($label) . '</h1>
<div style="overflow-x:auto">
<table>
<thead>
<tr>
<th rowspan="2">No</th><th rowspan="2">Nama</th><th rowspan="2">Group</th><th rowspan="2">Jabatan</th>
<th colspan="5" style="background:#fef3c7">LEMBUR</th>
<th rowspan="2" style="background:#dcfce7">Uang Makan</th>
<th rowspan="2" style="background:#dbeafe">Lembur<br>Sabtu</th>
<th rowspan="2" style="background:#fee2e2">Lembur<br>Minggu</th>
<th rowspan="2">Insentif</th><th rowspan="2">PBLT</th><th rowspan="2">Revisi</th>
<th rowspan="2" style="background:#e0e7ff">TOTAL</th>
</tr>
<tr>
<th style="background:#fef3c7">UM</th>
<th style="background:#fef3c7">DUA</th><th style="background:#fef3c7">FULL</th>
<th style="background:#fef3c7">1/2 HK</th><th style="background:#fef3c7">FULL D</th>
</tr>
</thead>
<tbody>' . $rows . $totalRow . '</tbody>
</table>
</div>
<div style="text-align:center;margin-top:8px"><button onclick="window.print()" style="padding:6px 16px;font-size:12px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨 Print</button></div>
</body></html>';
    }

    private function renderRekapResumePrintHtml($data, $label)
    {
        $rows = '';
        $i = 0;
        $totalUm = $totalSabtu = $totalMinggu = $totalInsentif = $totalPblt = $totalRevisi = $grandTotal = 0;

        foreach ($data as $item) {
            $i++;
            $um    = $item['uang_makan'] ?? 0;
            $sabtu = $item['lembur_sabtu'] ?? 0;
            $minggu = $item['lembur_minggu'] ?? 0;
            $ins   = $item['insentif'] ?? 0;
            $pblt  = $item['pblt'] ?? 0;
            $rev   = $item['revisi'] ?? 0;
            $tot   = $item['total'] ?? 0;

            $totalUm    += $um;
            $totalSabtu += $sabtu;
            $totalMinggu += $minggu;
            $totalInsentif += $ins;
            $totalPblt   += $pblt;
            $totalRevisi += $rev;
            $grandTotal  += $tot;

            $umF    = $um ? number_format($um, 0, ',', '.') : '-';
            $sabtuF = $sabtu ? number_format($sabtu, 0, ',', '.') : '-';
            $mingguF = $minggu ? number_format($minggu, 0, ',', '.') : '-';
            $insF   = $ins ? number_format($ins, 0, ',', '.') : '-';
            $pbltF  = $pblt ? number_format($pblt, 0, ',', '.') : '-';
            $revF   = $rev ? number_format($rev, 0, ',', '.') : '-';
            $totF   = $tot ? number_format($tot, 2, ',', '.') : '-';

            $rows .= "<tr>
                <td>{$i}</td>
                <td>{$item['bagian']}</td>
                <td class='text-right'>{$umF}</td>
                <td class='text-right'>{$sabtuF}</td>
                <td class='text-right'>{$mingguF}</td>
                <td class='text-right'>{$insF}</td>
                <td class='text-right'>{$pbltF}</td>
                <td class='text-right'>{$revF}</td>
                <td class='text-right'><strong>{$totF}</strong></td>
            </tr>";
        }

        $gtFmt = $grandTotal ? number_format($grandTotal, 2, ',', '.') : '-';

        $totalRow = "<tr style='background:#f3f4f6;font-weight:bold;border-top:2px solid #6366f1'>
            <td colspan='2' class='text-right' style='padding-right:12px'>TOTAL</td>
            <td class='text-right'>" . number_format($totalUm, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalSabtu, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalMinggu, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalInsentif, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalPblt, 0, ',', '.') . "</td>
            <td class='text-right'>" . number_format($totalRevisi, 0, ',', '.') . "</td>
            <td class='text-right' style='font-size:14px;color:#4f46e5'><strong>{$gtFmt}</strong></td>
        </tr>";

        return '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Resume Uang Makan</title>
<style>
@page{size:A4 landscape;margin:8mm}
body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:10px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:4px}
table{width:100%;border-collapse:collapse}
th{background:#e8eaed;font-weight:600;padding:5px 8px;border:1px solid #d1d5db;font-size:9px;text-align:center}
td{padding:4px 8px;border:1px solid #e5e7eb}
tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}.text-center{text-align:center}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>RESUME UANG MAKAN &amp; LEMBUR — ' . strtoupper($label) . '</h1>
<div style="overflow-x:auto">
<table><thead>
<tr>
<th>No</th><th>BAGIAN</th>
<th style="background:#dcfce7">UANG MAKAN</th>
<th style="background:#dbeafe">LEMBUR<br>SABTU</th>
<th style="background:#fee2e2">LEMBUR<br>MINGGU</th>
<th>INSENTIF</th><th>PBLT</th><th>REVISI</th>
<th style="background:#e0e7ff">TOTAL</th>
</tr>
</thead><tbody>' . $rows . $totalRow . '</tbody>
</table>
</div>
<div style="text-align:center;margin-top:8px"><button onclick="window.print()" style="padding:6px 16px;font-size:12px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨 Print</button></div>
</body></html>';
    }
}
