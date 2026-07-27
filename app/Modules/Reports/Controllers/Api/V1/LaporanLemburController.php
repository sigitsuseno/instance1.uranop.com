<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeReserve;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Reports\Exports\LemburHarianExport;
use App\Modules\Reports\Exports\LemburBulananExport;
use App\Modules\Reports\Exports\ResumeExport;
use App\Modules\Reports\Exports\LemburUangMakanDetailExport;
use App\Modules\Reports\Exports\LemburUangMakanResumeExport;
use App\Modules\Reports\Helpers\Lembur\JakartaHelper;
use App\Modules\Reports\Helpers\Lembur\AllInHelper;
use App\Modules\Reports\Helpers\Lembur\PrintingHelper;
use App\Modules\Reports\Helpers\Lembur\SpcHelper;
use App\Modules\Reports\Helpers\Lembur\TknHelper;
use App\Modules\Reports\Helpers\Lembur\LemburHelperTrait;
use App\Modules\Settings\Services\ReportConfigService;
use App\Modules\Reports\Services\LemburUangMakanUpdateService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class LaporanLemburController extends Controller
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
        $filename = 'Laporan_Lembur_Harian_' . str_replace(' ', '_', $result['date_label']) . '.xlsx';
        return Excel::download(
            new LemburHarianExport($result['data']->toArray(), $result['dates'], $result['date_label']),
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
        $filename = 'Laporan_Lembur_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new LemburBulananExport($result['data']->toArray(), $result['dates'], $label),
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

    // ─── Data Builders (private) ───────────────────────────────────

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
            ->with(['position', 'groups'])
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

            $upahPerHari = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 25, 2) : 0;
            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            $isSG = $employee->groups->contains('reference_code', 'SG');
            $days = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);

                $lmRaw = $prep ? (int)$prep->lm : 0;
                $overtimeRaw = $prep ? (int)$prep->overtime : 0;

                $lmCount = $prep ? (int)$prep->lm_count : 0;
                $overtimeCount = $prep ? (int)$prep->overtime_count : 0;

                $lmNominal = $lmCount > 0 ? round(($lmCount / 60) * $hourlyRate, 2) : 0;
                $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;
                $totalNominal = round($lmNominal + $overtimeNominal, 2);

                $lmDisplay = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
                $overtimeDisplay = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;

                $statusRaw = $prep ? $prep->status : '-';
                if (in_array($statusRaw, ['absent', 'libur', 'off', 'itm', 'izn', '-'])) {
                    $kode = '';
                } else {
                    $kode = $isSG ? 'SG' : 'L';
                }

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

                $dapatUpah = in_array($ha, ['H', 'C', 'S']);
                $isHoliday = $roster && $roster->is_holiday;
                $upahHarian = ($isHoliday && $ha === 'H') ? 0 : ($dapatUpah ? $upahPerHari : 0);

                $days[$dateStr] = [
                    'kode'     => $kode,
                    'ha'       => $ha,
                    'upah_per_hari' => $upahHarian,
                    'lm'       => $lmDisplay,
                    'lembur'   => $overtimeDisplay,
                    'nominal'  => $totalNominal,
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
                'group_name'          => $employee->groups->pluck('reference_code')->first() ?? '',
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
            // Fallback: bulan ini
            $startDate = Carbon::now()->startOfMonth();
            $endDate   = Carbon::now()->endOfMonth();
            $label     = $startDate->translatedFormat('F Y');
        }

        // Jangan lewati hari ini
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
            ->with(['position', 'groups'])
            ->get();

        // Ambil semua rosters dalam range
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

            // Hitung komponen gaji
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
            $upahPerHari = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 25, 2) : 0;
            $hourlyRate  = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            // Bangun data per hari
            $isSG = $employee->groups->contains('reference_code', 'SG');
            $days = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);

                // Display raw
                $lmRaw = $prep ? (int)$prep->lm : 0;
                $overtimeRaw = $prep ? (int)$prep->overtime : 0;

                // Count values for calculation
                $lmCount = $prep ? (int)$prep->lm_count : 0;
                $overtimeCount = $prep ? (int)$prep->overtime_count : 0;

                // LM: lm_count sudah include potongan 1 jam + multiplier dari code
                $lmNominal = $lmCount > 0 ? round(($lmCount / 60) * $hourlyRate, 2) : 0;
                // Lembur biasa: semua jam dibayar
                $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;
                $totalNominal = round($lmNominal + $overtimeNominal, 2);

                // Display hours
                $lmDisplay = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
                $overtimeDisplay = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;

                // Kode: SG atau "L"; kosongkan untuk absent, libur, off, itm, izn, atau no data
                $statusRaw = $prep ? $prep->status : '-';
                if (in_array($statusRaw, ['absent', 'libur', 'off', 'itm', 'izn', '-'])) {
                    $kode = '';
                } else {
                    $kode = $isSG ? 'SG' : 'L';
                }

                // H/A mapping 6 nilai — dari DB codes (lowercase abbreviations)
                $ha = match (true) {
                    $statusRaw === 'hadir'          => 'H',
                    $statusRaw === 'absent'         => 'A',
                    $statusRaw === 'libur',
                    $statusRaw === 'off'            => 'OFF',
                    $statusRaw === 'skt'            => 'S',
                    str_starts_with($statusRaw, 'c') => 'C',
                    $statusRaw === 'imt',           // Izin Masuk Terlambat → tetap hadir
                    $statusRaw === 'ipa'            => 'H',  // Izin Pulang Awal → tetap hadir
                    str_starts_with($statusRaw, 'i') => 'I',  // itm, izn, dll → Izin
                    default                         => $statusRaw === '-' ? '-' : 'I',
                };

                // Upah per hari: hanya H, C, S yang dapat
                // KECUALI: holiday + status H → tidak dapat (sudah di-cover LM)
                $dapatUpah = in_array($ha, ['H', 'C', 'S']);
                $isHoliday = $roster && $roster->is_holiday;
                $upahHarian = ($isHoliday && $ha === 'H') ? 0 : ($dapatUpah ? $upahPerHari : 0);

                $days[$dateStr] = [
                    'kode'     => $kode,
                    'ha'       => $ha,
                    'upah_per_hari' => $upahHarian,
                    'lm'       => $lmDisplay,
                    'lembur'   => $overtimeDisplay,
                    'nominal'  => $totalNominal,
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
            ];
        })->values();

        return [
            'data'         => $data,
            'dates'        => $dates,
            'month_label'  => $label,
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
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Lembur Harian</title>
<style>
@page{size:A3 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:8px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:3px 4px;border:1px solid #d1d5db;font-size:7px;text-align:center}
td{padding:2px 4px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>LAPORAN LEMBUR HARIAN — ' . strtoupper($label) . '</h1>
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

        // Build date header (two rows)
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
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Lembur Bulanan</title>
<style>
@page{size:A3 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:8px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:3px 4px;border:1px solid #d1d5db;font-size:7px;text-align:center}
td{padding:2px 4px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>LAPORAN LEMBUR BULANAN — ' . strtoupper($label) . '</h1>
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

    // ─── Resume (per Department) ──────────────────────────────────

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
        $filename = 'Resume_Overtime_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new ResumeExport($result['data'], $result['dates'], $label),
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
            ->with(['position', 'department', 'groups'])
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
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);
            $upahPerHari = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 25, 2) : 0;
            
            $isSPC = $employee->groups->contains(fn($g) => $g->reference_code === 'KRY-SPC');
            $hourlyRate  = $gaji > 0 ? ($isSPC ? round($gaji / 173, 2) : round(($gaji + $tjMk + $tunjangan) / 173, 2)) : 0;

            $posName = $employee->position->name ?? '-';

            $perDate = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);

                // H/A
                $statusRaw = $prep ? $prep->status : '-';
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

                // Upah per hari
                $dapatUpah = in_array($ha, ['H', 'C', 'S']);
                $isHoliday = $roster && $roster->is_holiday;
                $upahHarian = ($isHoliday && $ha === 'H') ? 0 : ($dapatUpah ? $upahPerHari : 0);

                // Overtime nominal — lm_count sudah include potongan 1 jam + multiplier
                $lmCount = $prep ? (int)$prep->lm_count : 0;
                $overtimeCount = $prep ? (int)$prep->overtime_count : 0;
                $lmNominal = $lmCount > 0 ? round(($lmCount / 60) * $hourlyRate, 2) : 0;
                $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;
                $totalNominal = round($lmNominal + $overtimeNominal, 2);

                $perDate[$dateStr] = [
                    'hari_kerja' => $upahHarian,
                    'overtime'   => $totalNominal,
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
<html lang="id"><head><meta charset="UTF-8"><title>Resume Overtime</title>
<style>
@page{size:A3 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:8px;color:#1f2937}
h1{font-size:14px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:3px 4px;border:1px solid #d1d5db;font-size:7px;text-align:center}
td{padding:2px 4px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}.text-center{text-align:center}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>RESUME OVERTIME — ' . strtoupper($label) . '</h1>
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

    // ─── Combined (Lembur + Uang Makan) ───────────────────────────

    public function combinedDetail(Request $request)
    {
        $result = $this->buildCombinedDetailData($request);
        return response()->json($result);
    }

    public function exportCombinedDetail(Request $request)
    {
        $result = $this->buildCombinedDetailData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : 'Laporan';
        $companyName = $request->input('company_name', 'PT. KEMILAU UNGARAN SUKSES');
        $filename = 'Rincian_Gaji_Overtime_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new LemburUangMakanDetailExport($result['sections'], $result['dates'], $label, $companyName),
            $filename
        );
    }

    public function combinedResume(Request $request)
    {
        $result = $this->buildCombinedResumeData($request);
        return response()->json($result);
    }

    public function exportCombinedResume(Request $request)
    {
        $result = $this->buildCombinedResumeData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : 'Resume';
        $companyName = $request->input('company_name', 'PT. KEMILAU UNGARAN SUKSES');
        $filename = 'Resume_Gaji_Overtime_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new LemburUangMakanResumeExport($result['sections'], $result['dates'], $label, $companyName),
            $filename
        );
    }

    // ─── Combined Detail Pre (from employee_overtime) ─────────────

    public function combinedDetailPre(Request $request)
    {
        $result = $this->buildCombinedDetailPreData($request);
        return response()->json($result);
    }

    public function exportCombinedDetailPre(Request $request)
    {
        $result = $this->buildCombinedDetailPreData($request);
        $periodId = $request->input('period_id');
        $period = PayPeriod::find($periodId);
        $label = $period ? $period->name : ($result['month_label'] ?? 'Laporan');
        $companyName = $request->input('company_name', 'PT. KEMILAU UNGARAN SUKSES');
        $filename = 'Rincian_Gaji_Overtime_Pre_' . str_replace(' ', '_', $label) . '.xlsx';
        return Excel::download(
            new LemburUangMakanDetailExport($result['sections'], $result['dates'], $label, $companyName),
            $filename
        );
    }

    private function buildCombinedDetailPreData(Request $request)
    {
        $periodId   = $request->input('period_id');
        $startInput = $request->input('start_date');
        $endInput   = $request->input('end_date');
        $groups     = $request->input('groups', []);

        // ── Date range ─────────────────────────────────────────────
        $period = null;
        if ($startInput && $endInput) {
            $startDate = Carbon::parse($startInput);
            $endDate   = Carbon::parse($endInput);
            $label     = $startDate->equalTo($endDate)
                ? $startDate->translatedFormat('d M Y')
                : $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y');
            $period = PayPeriod::where('start_date', '<=', $startDate)
                ->where('end_date', '>=', $startDate)->first();
        } elseif ($periodId) {
            $period = PayPeriod::find($periodId);
            if ($period) {
                $startDate = Carbon::parse($period->start_date);
                $endDate   = Carbon::parse($period->end_date);
                $label     = $period->name;
            } else {
                $startDate = Carbon::now()->startOfMonth();
                $endDate   = Carbon::now()->endOfMonth();
                $label     = $startDate->translatedFormat('F Y');
            }
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

        // ── Fetch from employee_overtime (per-date records) ──────
        $overtimeRecords = \App\Modules\Attendance\Models\EmployeeOvertime::where('pay_periode_id', $period?->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('employee.position', 'employee.groups', 'employee.groups.master')
            ->get();

        // Group by employee
        $byEmployee = $overtimeRecords->groupBy('employee_id');

        // ── Pay Records ────────────────────────────────────────────
        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)->get()->groupBy('employee_id');
        }

        // ── Helpers ────────────────────────────────────────────────
        $config = app(ReportConfigService::class)->getConfig('lembur_uang_makan');

        // ── Group employees by section ─────────────────────────────
        $jakartaEmployees  = collect();
        $allInEmployees    = collect();
        $printingEmployees = collect();
        $spcJakartaEmployees = collect();
        $spcUngaranEmployees = collect();

        foreach ($byEmployee as $employeeId => $records) {
            $employee = $records->first()->employee;
            if (!$employee) continue;

            // ── Salary / Tunjangan ────────────────────────────────────
            $empPayRecords = $payRecords->get($employee->id);
            $payRecord = null;
            if ($empPayRecords && $empPayRecords->isNotEmpty()) {
                $payRecord = $empPayRecords->first();
            }
            $gaji      = $payRecord ? (float)($payRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk      = $payRecord ? (float)($payRecord->tj_masa_kerja ?? 0)
                : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($startDate->format('Y-m'));
            }
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0)
                : (float)($employee->activeSalary()?->tunjangan ?? 0);

            $upahPerHari = ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;
            $upahLemburPerJam = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            // Build days map from each per-date record
            $days = [];
            $activeDayCount = 0;
            $totalHariKerja = 0;
            $totalOvertime  = 0;
            $totalUangMakan = 0;
            $totalTerima    = 0;
            $totalInsentif  = 0;

            foreach ($records as $rec) {
                $komponen = $rec->komponen ?? [];
                $status   = $komponen['status'] ?? '-';

                // ── Derive HA dari status ────────────────────────────
                $ha = match (true) {
                    $status === 'hadir'          => 'H',
                    $status === 'absent'         => 'A',
                    $status === 'libur',
                    $status === 'off'            => 'OFF',
                    $status === 'skt'            => 'S',
                    str_starts_with($status, 'c') => 'C',
                    $status === 'imt',
                    $status === 'ipa'            => 'H',
                    str_starts_with($status, 'i') => 'I',
                    default                      => $status === '-' ? '-' : 'I',
                };

                // ── Kode: L / SG ─────────────────────────────────────
                $isSG = $employee->groups->contains('reference_code', 'SG');
                if (in_array($status, ['absent', 'libur', 'off', 'itm', 'izn', '-'])) {
                    $kode = '';
                } else {
                    $kode = $isSG ? 'SG' : 'L';
                }

                // ── Baca langsung dari kolom employee_overtime ───────
                $hours         = (float)$rec->lembur;
                $nominalAmount = (float)$rec->nominal;
                $hasUmCode     = !empty($rec->um_code);

                if ($hasUmCode) {
                    // Uang Makan type: nominal = uang_makan, LM kosong, Lembur = um_code
                    $lmDisplay   = '';
                    $lemburDisplay = $rec->um_code;  // UM / FULL / HALF / 2 / TKN
                    $uangMakanDay = $nominalAmount;
                    $overtimeNominalDay = 0;
                } else {
                    // Lembur type: nominal = overtime, tampilkan hours
                    $lmDisplay   = $hours > 0 ? $hours : '';
                    $lemburDisplay = '';
                    $uangMakanDay = 0;
                    $overtimeNominalDay = $nominalAmount;
                }

                $dateStr = $rec->date instanceof Carbon
                    ? $rec->date->format('Y-m-d')
                    : ($rec->date ?? '');

                $days[$dateStr] = [
                    'kode'             => $kode,
                    'ha'               => $ha,
                    'upah_per_hari'    => in_array($ha, ['A', 'I', 'OFF']) ? 0 : $upahPerHari,
                    'lm'               => $lmDisplay,
                    'lembur'           => $lemburDisplay,
                    'nominal'          => $nominalAmount,
                    'uang_makan'       => $uangMakanDay,
                    'overtime_nominal' => $overtimeNominalDay,
                ];

                // Day-level totals
                $totalUangMakan += $uangMakanDay;
                $totalOvertime  += $overtimeNominalDay;
                if (!in_array($status, ['izn', 'absent', 'off'])) {
                    $activeDayCount++;
                }
                $totalInsentif  += (float)$rec->insentif;
            }

            // ── Hitung total_hari_kerja ──────────────────────────────────
            // Jika hari ini >= end_date periode → formula: max(0, 25 - absent - izin) × (gaji/25)
            // Jika belum → hitung dari hari aktif aktual (current logic)
            $periodEndDate = $period?->end_date;
            if ($periodEndDate && Carbon::today()->gte($periodEndDate)) {
                $absentCount = 0;
                $izinCount   = 0;
                foreach ($days as $day) {
                    $ha = $day['ha'] ?? '';
                    if ($ha === 'A') $absentCount++;
                    if ($ha === 'I') $izinCount++;
                }
                $effectiveDays = max(0, 25 - $absentCount - $izinCount);
                $totalHariKerja = round($effectiveDays * ($gaji / 25), 2);
            } else {
                $effectiveDays = min($activeDayCount, 25);
                $totalHariKerja = round($effectiveDays * ($gaji / 25), 2);
            }

            // ── Cari nilai insentif dari record yg date = end_date periode ──
            $endDateStr = $period?->end_date?->format('Y-m-d');
            $targetRecord = $endDateStr
                ? $records->first(fn($r) => ($r->date instanceof Carbon
                    ? $r->date->format('Y-m-d')
                    : ($r->date ?? '')) === $endDateStr)
                : null;

            // Fallback ke record terakhir (kronologis) jika tidak ada yg cocok
            if (!$targetRecord) {
                $targetRecord = $records->sortByDesc(fn($r) => $r->date instanceof Carbon
                    ? $r->date->format('Y-m-d')
                    : ($r->date ?? ''))->first();
            }
            $endDateInsentif = $targetRecord ? (float)$targetRecord->insentif : 0;

            // ── Tentukan tipe: uang_makan vs lembur (mutually exclusive) ─
            // Uang_makan type: dapat meal allowance, TIDAK dapat overtime money
            // Lembur type: dapat overtime money, TIDAK dapat meal allowance
            $hasUmCode = $records->contains(fn($r) => !empty($r->um_code));
            $isSPR = in_array('GRP-SPR', $employee->groups->pluck('reference_code')->toArray());
            
            if ($hasUmCode && !$isSPR) {
                // Uang_makan type (Jakarta/AllIn/TKN): total_overtime = 0
                $totalOvertime = 0;
            } else {
                // Lembur type (Printing/SPC/SPR): total_uang_makan = 0
                $totalUangMakan = 0;
            }

            $totalTerima = $totalHariKerja + $totalOvertime + $totalUangMakan + $totalInsentif;

            $item = [
                'id'                => $employee->id,
                'name'              => $employee->name ?? '',
                'jabatan'           => $employee->position?->name ?? '',
                'gender'            => $employee->gender === 'Pria' ? 'L' : 'P',
                'nip'               => $employee->nip ?? '',
                'gaji'              => $gaji,
                'tj_mk'             => $tjMk,
                'tunjangan'         => $tunjangan,
                'upah_per_hari'     => $upahPerHari,
                'upah_lembur_per_jam' => $upahLemburPerJam,
                'days'              => $days,
                'insentif'          => $endDateInsentif,
                'total_hari_kerja'  => round($totalHariKerja, 2),
                'total_overtime'    => round($totalOvertime, 2),
                'total_uang_makan'  => round($totalUangMakan, 2),
                'total_insentif'    => round($totalInsentif, 2),
                'total_terima'      => round($totalTerima, 2),
                '_is_spr'           => false,
            ];

            // Classify employee by group
            $empGroups = $employee->groups->pluck('reference_code')->toArray();

            if (TknHelper::matches($employee)) {
                if (in_array('GRP-JKT', $empGroups)) {
                    $jakartaEmployees->push($item);
                } else {
                    $allInEmployees->push($item);
                }
            } elseif (SpcHelper::matches($employee)) {
                if (in_array('GRP-JKT', $empGroups)) {
                    $spcJakartaEmployees->push($item);
                } else {
                    $spcUngaranEmployees->push($item);
                }
            } elseif (in_array('GRP-JKT', $empGroups)) {
                $jakartaEmployees->push($item);
            } elseif (PrintingHelper::matches($employee)) {
                $printingEmployees->push($item);
            } elseif (in_array('GRP-SPR', $empGroups)) {
                foreach ($days as $dateStr => &$day) {
                    $day['uang_makan'] = 0;
                }
                unset($day);
                $item['_is_spr'] = true;
                $item['total_uang_makan'] = 0;
                $allInEmployees->push($item);
            } else {
                $allInEmployees->push($item);
            }
        }

        // ── Sort employees ─────────────────────────────────────────
        $jakartaEmployees    = $jakartaEmployees->sortBy('name')->values();
        $allInEmployees      = $allInEmployees->sortBy('name')->values();
        $printingEmployees   = $printingEmployees->sortBy('name')->values();
        $spcJakartaEmployees = $spcJakartaEmployees->sortBy('name')->values();
        $spcUngaranEmployees = $spcUngaranEmployees->sortBy('name')->values();

        // ── Jakarta: total_hari_kerja = 0 ────────────────────────────
        if ($jakartaEmployees->isNotEmpty()) {
            $jakartaEmployees = $jakartaEmployees->map(function ($emp) {
                $emp['total_hari_kerja'] = 0;
                $emp['total_terima'] = round(($emp['total_overtime'] ?? 0) + ($emp['total_uang_makan'] ?? 0), 2);
                return $emp;
            });
        }

        // ── Build sections (A=uang_makan, B=uang_makan, C=lembur, D=lembur, E=lembur) ──
        $sections = [];

        // ── A. KARYAWAN JAKARTA (uang_makan) ────────────────────────
        if ($jakartaEmployees->isNotEmpty()) {
            $sections[] = [
                'key'    => 'jakarta',
                'label'  => 'A. KARYAWAN JAKARTA',
                'type'   => 'uang_makan',
                'data'   => $jakartaEmployees,
                'totals' => $this->calcSectionTotals($jakartaEmployees),
            ];
        }

        // ── B. KARYAWAN ALL IN (uang_makan) ─────────────────────────
        if ($allInEmployees->isNotEmpty()) {
            $sections[] = [
                'key'    => 'all_in',
                'label'  => 'B. KARYAWAN ALL IN',
                'type'   => 'uang_makan',
                'data'   => $allInEmployees,
                'totals' => $this->calcSectionTotals($allInEmployees),
            ];
        }

        // ── C. KARYAWAN BULANAN PRINTING (lembur) ──────────────────
        if ($printingEmployees->isNotEmpty()) {
            $sections[] = [
                'key'    => 'printing',
                'label'  => 'C. KARYAWAN BULANAN PRINTING',
                'type'   => 'lembur',
                'data'   => $printingEmployees,
                'totals' => $this->calcSectionTotals($printingEmployees),
            ];
        }

        // ── D & E. KARYAWAN SPESIFIK (lembur, conditional) ──────────
        $showSpc = SpcHelper::shouldShow($period?->id);
        if ($showSpc) {
            if ($spcJakartaEmployees->isNotEmpty()) {
                $sections[] = [
                    'key'    => 'spc_jakarta',
                    'label'  => 'D. KARYAWAN SPESIFIK JAKARTA',
                    'type'   => 'lembur',
                    'data'   => $spcJakartaEmployees,
                    'totals' => $this->calcSectionTotals($spcJakartaEmployees),
                ];
            }
            if ($spcUngaranEmployees->isNotEmpty()) {
                $sections[] = [
                    'key'    => 'spc_ungaran',
                    'label'  => 'E. KARYAWAN SPESIFIK UNGARAN',
                    'type'   => 'lembur',
                    'data'   => $spcUngaranEmployees,
                    'totals' => $this->calcSectionTotals($spcUngaranEmployees),
                ];
            }
        }

        // ── Grand totals (hanya dari section yang aktif) ────────────
        $allDataForTotals = $jakartaEmployees
            ->concat($allInEmployees)
            ->concat($printingEmployees);
        if ($showSpc) {
            $allDataForTotals = $allDataForTotals
                ->concat($spcJakartaEmployees)
                ->concat($spcUngaranEmployees);
        }
        $grandTotals = $allDataForTotals->isNotEmpty()
            ? $this->calcSectionTotals($allDataForTotals)
            : null;

        return [
            'sections'     => $sections,
            'dates'        => $dates,
            'month_label'  => $label,
            'grand_totals' => $grandTotals,
        ];
    }

    private function calcSectionTotals($employees): array
    {
        return [
            'total_hari_kerja' => round($employees->sum('total_hari_kerja'), 2),
            'total_overtime'   => round($employees->sum('total_overtime'), 2),
            'total_uang_makan' => round($employees->sum('total_uang_makan') + $employees->sum('total_insentif'), 2),
            'total_terima'     => round($employees->sum('total_terima'), 2),
            'count'            => $employees->count(),
        ];
    }

    // ─── Update Data ──────────────────────────────────────────────

    public function updateData(Request $request)
    {
        $request->validate([
            'period_id' => 'required|integer|exists:pay_periods,id',
        ]);

        $params = [
            'emp_tanpa_sabtu_minggu_holiday'       => $request->input('emp_tanpa_sabtu_minggu_holiday', []),
            'emp_tanpa_allin_sabtu_minggu_holiday'  => $request->input('emp_tanpa_allin_sabtu_minggu_holiday', []),
            'position_rules'                       => $request->input('position_rules', []),
            'technician_rules'                     => $request->input('technician_rules', []),
        ];

        $service = app(LemburUangMakanUpdateService::class);
        $result = $service->update((int) $request->input('period_id'), $params);

        return response()->json($result);
    }

    // ─── Combined Data Builders ───────────────────────────────────

    private function buildCombinedDetailData(Request $request)
    {
        $periodId   = $request->input('period_id');
        $startInput = $request->input('start_date');
        $endInput   = $request->input('end_date');
        $groups     = $request->input('groups', []);

        // ── Date range: period OR custom range ─────────────────────
        $period = null;
        if ($startInput && $endInput) {
            $startDate = Carbon::parse($startInput);
            $endDate   = Carbon::parse($endInput);
            $label     = $startDate->equalTo($endDate)
                ? $startDate->translatedFormat('d M Y')
                : $startDate->translatedFormat('d M') . ' - ' . $endDate->translatedFormat('d M Y');
            $period = PayPeriod::where('start_date', '<=', $startDate)
                ->where('end_date', '>=', $startDate)->first();
        } elseif ($periodId) {
            $period = PayPeriod::find($periodId);
            if ($period) {
                $startDate = Carbon::parse($period->start_date);
                $endDate   = Carbon::parse($period->end_date);
                $label     = $period->name;
            } else {
                $startDate = Carbon::now()->startOfMonth();
                $endDate   = Carbon::now()->endOfMonth();
                $label     = $startDate->translatedFormat('F Y');
            }
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

        // ── Fetch data ─────────────────────────────────────────────
        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->get()->groupBy('employee_id');

        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$startDate, $endDate]))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['position', 'groups', 'groups.master'])
            ->get();

        $rosters = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->with('shift')->get()
            ->groupBy('employee_id');

        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)->get()->groupBy('employee_id');
        }

        // Employee Reserves (insentif)
        $employeeReserves = collect();
        if ($period) {
            $employeeReserves = EmployeeReserve::where('pay_periode_id', $period->id)
                ->get()
                ->keyBy('employee_id');
        }

        // ── Config ─────────────────────────────────────────────────
        $config = app(ReportConfigService::class)->getConfig('lembur_uang_makan');

        // ── Helpers ────────────────────────────────────────────────
        $jakartaHelper  = new JakartaHelper();
        $allInHelper    = new AllInHelper();
        $printingHelper = new PrintingHelper();
        $spcHelper      = new SpcHelper();
        $tknHelper      = new TknHelper();

        $jakartaEmployees  = collect();
        $allInEmployees    = collect();
        $printingEmployees = collect();
        $spcEmployees      = collect();
        $spcJakartaEmployees  = collect();
        $spcUngaranEmployees  = collect();

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
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);

            // ── Classify & process ─────────────────────────────────
            // KRY-TKN: flag teknisi — override formula, tetap di section GRP asli
            if (TknHelper::matches($employee)) {
                $item = $tknHelper->processEmployee($employee, $empPrepares, $empRosters, $payRecord, $dates, $gaji, $tjMk, $tunjangan, $config);

                if (JakartaHelper::matches($employee)) {
                    $jakartaEmployees->push($item);
                } else {
                    $allInEmployees->push($item);
                }
                continue;
            }

            // Saat section D aktif, prioritaskan KRY-SPC di atas Jakarta
            $showSpc = SpcHelper::shouldShow($period?->id);

            if ($showSpc && SpcHelper::matches($employee)) {
                // Filter SPC: employee harus punya minimal 1 group selain KRY-SPC
                // yang ada di selected groups (kalau ada filter group)
                if (!empty($groups)) {
                    $nonSpcGroups = array_values(array_filter($groups, fn($g) => $g !== 'KRY-SPC'));
                    // Jika masih ada group lain yang dipilih selain KRY-SPC,
                    // pastikan employee punya salah satunya
                    if (!empty($nonSpcGroups)) {
                        $hasMatchingGroup = $employee->groups->contains(
                            fn($g) => in_array($g->reference_code, $nonSpcGroups)
                        );
                        if (!$hasMatchingGroup) {
                            continue; // Employee ini ga match group yang dipilih → skip
                        }
                    }
                }
                $item = $spcHelper->processEmployee($employee, $empPrepares, $empRosters, $payRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
                $spcEmployees->push($item);

                // Split: SPC Jakarta (punya GRP-JKT) vs SPC Ungaran
                if (JakartaHelper::matches($employee)) {
                    $spcJakartaEmployees->push($item);
                } else {
                    $spcUngaranEmployees->push($item);
                }
            } elseif (JakartaHelper::matches($employee)) {
                $item = $jakartaHelper->processEmployee($employee, $empPrepares, $empRosters, $payRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
                $jakartaEmployees->push($item);
            } elseif (PrintingHelper::matches($employee)) {
                $item = $printingHelper->processEmployee($employee, $empPrepares, $empRosters, $payRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
                $printingEmployees->push($item);
            } elseif ($employee->groups->contains(fn($g) => $g->reference_code === 'GRP-SPR')) {
                // GRP-SPR (Sopir/Driver): section ALL IN (B)
                // - Hitung LEMBUR (Printing: count-based), bukan Uang Makan rate-based
                // - Hanya hari Minggu & Holiday, Senin-Sabtu default 0
                $item = $printingHelper->processEmployee($employee, $empPrepares, $empRosters, $payRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
                
                // Filter: hanya hari Minggu (0) & Holiday, sisanya 0
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
                        // Minggu/Holiday: ini overtime lembur, bukan uang_makan
                        $day['uang_makan'] = 0;
                    }
                }
                unset($day);
                
                // Recalculate totals
                $totalHariKerja = 0;
                $totalOvertime = 0;
                foreach ($item['days'] as $day) {
                    $totalHariKerja += $day['upah_per_hari'] ?? 0;
                    $totalOvertime += $day['overtime_nominal'] ?? 0;
                }
                $item['total_hari_kerja'] = round($totalHariKerja, 2);
                $item['total_overtime'] = round($totalOvertime, 2);
                $item['total_uang_makan'] = 0;
                $item['total_terima'] = round($totalHariKerja + $totalOvertime, 2);
                $item['_is_spr'] = true;
                $allInEmployees->push($item);
            } else {
                $item = $allInHelper->processEmployee($employee, $empPrepares, $empRosters, $payRecord, $dates, $gaji, $tjMk, $tunjangan, $config);
                $allInEmployees->push($item);
            }
        }

        // ── Sort ───────────────────────────────────────────────────
        $jakartaEmployees  = $jakartaEmployees->sortBy('name')->values();
        $allInEmployees    = $allInEmployees->sortBy('name')->values();
        $printingEmployees = $printingEmployees->sortBy('name')->values();
        $spcEmployees      = $spcEmployees->sortBy('name')->values();
        $spcJakartaEmployees = $spcJakartaEmployees->sortBy('name')->values();
        $spcUngaranEmployees = $spcUngaranEmployees->sortBy('name')->values();

        // ── Period complete check: tanggal 22/23/24 ada data? ─────
        $endMonth = (int) $endDate->format('m');
        $endYear  = (int) $endDate->format('Y');
        $checkDates = array_values(array_filter([
            sprintf('%04d-%02d-22', $endYear, $endMonth),
            sprintf('%04d-%02d-23', $endYear, $endMonth),
            sprintf('%04d-%02d-24', $endYear, $endMonth),
        ], fn($d) => in_array($d, $dates)));

        $isPeriodComplete = !empty($checkDates)
            && AttendancePrepare::whereIn('date', $checkDates)->exists();

        // ── Recalculate total_hari_kerja (formula mode) ────────────
        if ($isPeriodComplete) {
            $recalcFormula = function ($emp) {
                $absent = 0;
                $izin   = 0;
                foreach ($emp['days'] as $day) {
                    $ha = $day['ha'] ?? '';
                    if ($ha === 'A') $absent++;
                    if ($ha === 'I') $izin++;
                }
                $hariKerja = max(0, 25 - $absent - $izin);
                $emp['total_hari_kerja'] = round($emp['upah_per_hari'] * $hariKerja, 2);
                $emp['total_terima']     = round(
                    $emp['total_hari_kerja'] + $emp['total_overtime'] + $emp['total_uang_makan'], 2
                );
                return $emp;
            };

            $jakartaEmployees  = $jakartaEmployees->map($recalcFormula);
            $allInEmployees    = $allInEmployees->map(function ($emp) use ($recalcFormula) {
                // SPR: skip formula override (25 - absent - izin), pake sum per-day langsung
                if ($emp['_is_spr'] ?? false) {
                    return $emp;
                }
                return $recalcFormula($emp);
            });
            $printingEmployees = $printingEmployees->map($recalcFormula);
            $spcEmployees      = $spcEmployees->map($recalcFormula);
            $spcJakartaEmployees = $spcJakartaEmployees->map($recalcFormula);
            $spcUngaranEmployees = $spcUngaranEmployees->map($recalcFormula);
        }

        // Karyawan Jakarta: Total Hari Kerja selalu 0
        $jakartaEmployees = $jakartaEmployees->map(function ($emp) {
            $emp['total_hari_kerja'] = 0;
            $emp['total_terima'] = round($emp['total_overtime'] + $emp['total_uang_makan'], 2);
            return $emp;
        });

        // Karyawan SPC Jakarta (section D): Total Hari Kerja selalu 0
        $spcJakartaEmployees = $spcJakartaEmployees->map(function ($emp) {
            $emp['total_hari_kerja'] = 0;
            $emp['total_terima'] = round($emp['total_overtime'] + $emp['total_uang_makan'], 2);
            return $emp;
        });

        // ── Employee Reserves: tambah insentif ke total_uang_makan ─
        if ($employeeReserves->isNotEmpty()) {
            $addReserve = function ($emp) use ($employeeReserves) {
                $reserve = $employeeReserves->get($emp['id']);
                if ($reserve && !empty($reserve->komponen)) {
                    $insentif = (float) collect($reserve->komponen)->sum('nilai');
                    $emp['total_uang_makan'] = round(($emp['total_uang_makan'] ?? 0) + $insentif, 2);
                    $emp['total_terima'] = round(
                        ($emp['total_hari_kerja'] ?? 0) + ($emp['total_overtime'] ?? 0) + $emp['total_uang_makan'], 2
                    );
                }
                return $emp;
            };

            $jakartaEmployees    = $jakartaEmployees->map($addReserve);
            $allInEmployees      = $allInEmployees->map($addReserve);
            $printingEmployees   = $printingEmployees->map($addReserve);
            $spcEmployees        = $spcEmployees->map($addReserve);
            $spcJakartaEmployees = $spcJakartaEmployees->map($addReserve);
            $spcUngaranEmployees = $spcUngaranEmployees->map($addReserve);
        }

        // ── Assemble sections ──────────────────────────────────────
        $sections = [
            [
                'label'  => JakartaHelper::getLabel(),
                'key'    => JakartaHelper::getKey(),
                'type'   => JakartaHelper::getType(),
                'data'   => $jakartaEmployees,
                'totals' => LemburHelperTrait::calculateSectionTotals($jakartaEmployees),
            ],
            [
                'label'  => AllInHelper::getLabel(),
                'key'    => AllInHelper::getKey(),
                'type'   => AllInHelper::getType(),
                'data'   => $allInEmployees,
                'totals' => LemburHelperTrait::calculateSectionTotals($allInEmployees),
            ],
            [
                'label'  => PrintingHelper::getLabel(),
                'key'    => PrintingHelper::getKey(),
                'type'   => PrintingHelper::getType(),
                'data'   => $printingEmployees,
                'totals' => LemburHelperTrait::calculateSectionTotals($printingEmployees),
            ],
        ];

        // Section D & E: SPC Jakarta & Ungaran (conditional)
        if (SpcHelper::shouldShow($period?->id)) {
            $sections[] = [
                'label'  => 'D. KARYAWAN SPESIFIK JAKARTA',
                'key'    => 'spc_jakarta',
                'type'   => 'lembur',
                'data'   => $spcJakartaEmployees,
                'totals' => LemburHelperTrait::calculateSectionTotals($spcJakartaEmployees),
            ];
            $sections[] = [
                'label'  => 'E. KARYAWAN SPESIFIK UNGARAN',
                'key'    => 'spc_ungaran',
                'type'   => 'lembur',
                'data'   => $spcUngaranEmployees,
                'totals' => LemburHelperTrait::calculateSectionTotals($spcUngaranEmployees),
            ];
        }

        $allData = $jakartaEmployees->concat($allInEmployees)->concat($printingEmployees);
        if (SpcHelper::shouldShow($period?->id)) {
            $allData = $allData->concat($spcJakartaEmployees)->concat($spcUngaranEmployees);
        }
        $grandTotals = LemburHelperTrait::calculateSectionTotals($allData);

        return [
            'sections'     => $sections,
            'dates'        => $dates,
            'month_label'  => $label,
            'grand_totals' => $grandTotals,
        ];
    }

    private function buildCombinedResumeData(Request $request)
    {
        // First get the detail data
        $detailResult = $this->buildCombinedDetailData($request);
        $dates = $detailResult['dates'];

        // Flatten all employees from both sections
        $allEmployees = collect();
        foreach ($detailResult['sections'] as $section) {
            foreach ($section['data'] as $emp) {
                $emp['_section_key'] = $section['key'];
                $allEmployees->push($emp);
            }
        }

        // Group by jabatan within each section
        $sections = [];
        // Rebuild sections with correct per-type totals
        $sectionMeta = [
            'jakarta'  => ['label' => JakartaHelper::getLabel(),         'uang_makan_key' => 'nominal', 'type' => 'uang_makan'],
            'all_in'   => ['label' => AllInHelper::getLabel(),           'uang_makan_key' => 'nominal', 'type' => 'uang_makan'],
            'printing' => ['label' => PrintingHelper::getLabel(),        'uang_makan_key' => 'nominal', 'type' => 'lembur'],
        ];

        // Conditional: SPC hanya jika periode memenuhi syarat
        $period = null;
        if ($periodId = $request->input('period_id')) {
            $period = PayPeriod::find($periodId);
        }
        if (SpcHelper::shouldShow($period?->id)) {
            $sectionMeta['spc_jakarta'] = ['label' => 'D. KARYAWAN SPESIFIK JAKARTA', 'uang_makan_key' => 'nominal', 'type' => 'lembur'];
            $sectionMeta['spc_ungaran'] = ['label' => 'E. KARYAWAN SPESIFIK UNGARAN', 'uang_makan_key' => 'nominal', 'type' => 'lembur'];
        }
        // ── Period complete check for resume ──────────────────────
        $lastDate  = Carbon::parse(end($dates));
        $endMonth  = (int) $lastDate->format('m');
        $endYear   = (int) $lastDate->format('Y');
        $checkDates = array_values(array_filter([
            sprintf('%04d-%02d-22', $endYear, $endMonth),
            sprintf('%04d-%02d-23', $endYear, $endMonth),
            sprintf('%04d-%02d-24', $endYear, $endMonth),
        ], fn($d) => in_array($d, $dates)));

        $isPeriodComplete = !empty($checkDates)
            && AttendancePrepare::whereIn('date', $checkDates)->exists();

        foreach ($sectionMeta as $sectionKey => $meta) {
            $sectionEmps = $allEmployees->where('_section_key', $sectionKey);
            $posGroups = $sectionEmps->groupBy('jabatan');
            $umKey = $meta['uang_makan_key'];
            $isUangMakan = $meta['type'] === 'uang_makan'; // Jakarta & ALL IN = Uang Makan, Printing = Lembur
            $allInIsSpr = $sectionKey === 'all_in'; // section all_in campur (regular ALL IN + SPR)

            $data = [];
            foreach ($posGroups as $posName => $emps) {
                $l = $emps->where('gender', 'L')->count();
                $p = $emps->where('gender', 'P')->count();

                $days = [];
                $totalHariKerja = 0;
                $totalOvertime = 0;
                $totalUangMakan = 0;

                foreach ($dates as $dateStr) {
                    $hariKerja = $emps->sum(fn($e) => $e['days'][$dateStr]['upah_per_hari'] ?? 0);

                    // all_in: SPR → overtime_nominal (lembur), non-SPR → nominal (uang_makan)
                    if ($allInIsSpr) {
                        $overtime  = $emps->sum(fn($e) => ($e['_is_spr'] ?? false) ? ($e['days'][$dateStr]['overtime_nominal'] ?? 0) : 0);
                        $uangMakan = $emps->sum(fn($e) => !($e['_is_spr'] ?? false) ? ($e['days'][$dateStr][$umKey] ?? 0) : 0);
                    } else {
                        $overtime  = $isUangMakan ? 0 : $emps->sum(fn($e) => $e['days'][$dateStr]['overtime_nominal'] ?? 0);
                        $uangMakan = $isUangMakan ? $emps->sum(fn($e) => $e['days'][$dateStr][$umKey] ?? 0) : 0;
                    }

                    $days[$dateStr] = [
                        'hari_kerja' => round($hariKerja, 2),
                        'overtime'   => round($overtime, 2),
                        'uang_makan' => round($uangMakan, 2),
                    ];
                    $totalHariKerja += $hariKerja;
                    $totalOvertime += $overtime;
                    $totalUangMakan += $uangMakan;
                }

                // ── Formula override: upah/hari × (25 - absent - izin) ──
                if ($isPeriodComplete) {
                    $totalHariKerja = 0;
                    foreach ($emps as $emp) {
                        if ($emp['_is_spr'] ?? false) {
                            // SPR: pake total_hari_kerja dari sum per-day, skip 25-formula
                            $totalHariKerja += $emp['total_hari_kerja'] ?? 0;
                            continue;
                        }
                        $absent = 0;
                        $izin   = 0;
                        foreach ($emp['days'] as $day) {
                            $ha = $day['ha'] ?? '';
                            if ($ha === 'A') $absent++;
                            if ($ha === 'I') $izin++;
                        }
                        $hariKerjaEmp = max(0, 25 - $absent - $izin);
                        $totalHariKerja += ($emp['upah_per_hari'] ?? 0) * $hariKerjaEmp;
                    }
                }

                $data[] = [
                    'bagian'            => $posName,
                    'l'                 => $l,
                    'p'                 => $p,
                    'days'              => $days,
                    'total_hari_kerja'  => round($totalHariKerja, 2),
                    'total_overtime'    => round($totalOvertime, 2),
                    'total_uang_makan'  => round($totalUangMakan, 2),
                    'total_terima'      => round($totalHariKerja + $totalOvertime + $totalUangMakan, 2),
                ];
            }

            usort($data, fn($a, $b) => strcmp($a['bagian'], $b['bagian']));

            $sections[] = [
                'label' => $meta['label'],
                'key'   => $sectionKey,
                'data'  => $data,
            ];
        }

        return [
            'sections'     => $sections,
            'dates'        => $dates,
            'period_label' => $detailResult['month_label'],
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function calculateSectionTotals($employees): array
    {
        return [
            'total_hari_kerja' => round($employees->sum('total_hari_kerja'), 2),
            'total_overtime'   => round($employees->sum('total_overtime'), 2),
            'total_uang_makan' => round($employees->sum('total_uang_makan'), 2),
            'total_terima'     => round($employees->sum('total_terima'), 2),
            'count'            => $employees->count(),
        ];
    }

    private function getUangMakanRates(string $groupName, float $gajiPokok): array
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

    // ─── Save Insentif (Detail Pre) ─────────────────────────────────────

    public function saveInsentif(Request $request)
    {
        $request->validate([
            'period_id' => 'required|integer|exists:pay_periods,id',
            'insentif'  => 'required|array',
            'insentif.*.employee_id' => 'required|integer|exists:employees,id',
            'insentif.*.value'       => 'required|numeric|min:0',
        ]);

        $period = \App\Modules\Payroll\Models\PayPeriod::findOrFail($request->period_id);

        $updated = 0;
        foreach ($request->insentif as $item) {
            // Cari record yg date = end_date periode; fallback ke record terakhir
            $overtime = \App\Modules\Attendance\Models\EmployeeOvertime::where('pay_periode_id', $period->id)
                ->where('employee_id', $item['employee_id'])
                ->whereDate('date', $period->end_date)
                ->first();

            if (!$overtime) {
                $overtime = \App\Modules\Attendance\Models\EmployeeOvertime::where('pay_periode_id', $period->id)
                    ->where('employee_id', $item['employee_id'])
                    ->orderBy('date', 'desc')
                    ->first();
            }

            if ($overtime) {
                $overtime->insentif = (float)$item['value'];
                $overtime->save();
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Insentif berhasil disimpan untuk {$updated} karyawan.",
            'updated' => $updated,
        ]);
    }
}
