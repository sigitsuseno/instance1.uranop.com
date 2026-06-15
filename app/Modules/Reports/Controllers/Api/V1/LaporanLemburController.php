<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Reports\Exports\LemburHarianExport;
use App\Modules\Reports\Exports\LemburBulananExport;
use App\Modules\Reports\Exports\ResumeExport;
use App\Modules\Reports\Exports\LemburUangMakanDetailExport;
use App\Modules\Reports\Exports\LemburUangMakanResumeExport;
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
            $hourlyRate  = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

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
            // find nearest period for pay records
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

        $allInEmployees = collect();
        $printingEmployees = collect();

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

            $upahPerHari = ($gaji + $tjMk) > 0 ? round(($gaji + $tjMk) / 25, 2) : 0;
            $hourlyRate  = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;
            $upahLemburPerJam = $hourlyRate;

            // Uang Makan group & rates
            $umGroup = $employee->groups->first(fn($g) =>
                $g->master && strtoupper($g->master->group_label ?? '') === 'UANG MAKAN'
            );
            $umGroupName = strtoupper($umGroup?->master?->name ?? '');
            $umRates = $this->getUangMakanRates($umGroupName, $gaji);

            // Section determination: GRP-PS1 / GRP-SS → PRINTING
            $isPrinting = $employee->groups->contains(fn($g) =>
                in_array($g->reference_code, ['GRP-PS1', 'GRP-SS'])
            );
            $isSG = $employee->groups->contains('reference_code', 'SG');

            $days = [];
            $totalHariKerja = 0;
            $totalOvertime = 0;
            $totalUangMakan = 0;

            foreach ($dates as $dateStr) {
                $prep   = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);

                $statusRaw  = $prep ? $prep->status : '-';
                $isHoliday  = $roster && $roster->is_holiday;
                $dayOfWeek  = $parsedDate->dayOfWeek;

                // ── common H/A ──────────────────────────────────────
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
                $upahHarian = ($isHoliday && $ha === 'H') ? 0 : ($dapatUpah ? $upahPerHari : 0);

                // ── lembur raw ──────────────────────────────────────
                $lmRaw       = $prep ? (int)$prep->lm : 0;
                $overtimeRaw = $prep ? (int)$prep->overtime : 0;
                $lmCount      = $prep ? (int)$prep->lm_count : 0;
                $overtimeCount = $prep ? (int)$prep->overtime_count : 0;

                $lmDisplay      = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
                $overtimeDisplay = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;
                $lemburTotal    = $lmDisplay + $overtimeDisplay;

                $lmNominal       = $lmCount > 0 ? round(($lmCount / 60) * $hourlyRate, 2) : 0;
                $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;
                $totalOvertimeNominal = round($lmNominal + $overtimeNominal, 2);

                if ($isPrinting) {
                    // ═══════════════════════════════════════════════
                    //  SECTION B — BULANAN PRINTING (Lembur logic)
                    // ═══════════════════════════════════════════════
                    if (in_array($statusRaw, ['absent', 'libur', 'off', 'itm', 'izn', '-'])) {
                        $kode = '';
                    } else {
                        $kode = $isSG ? 'SG' : 'L';
                    }

                    // Uang Makan (same as before)
                    $uangMakanNominal = 0;
                    if ($lemburTotal > 0) {
                        $isMingguHoliday = ($dayOfWeek == 0 || $isHoliday);
                        if ($isMingguHoliday) {
                            if ($lemburTotal >= 8) {
                                $uangMakanNominal = $umRates['minggu_full'];
                            } elseif ($lemburTotal >= 4) {
                                $uangMakanNominal = $umRates['minggu_half'];
                            }
                        } elseif ($dayOfWeek == 6) {
                            if ($lemburTotal >= 4) {
                                $uangMakanNominal = $umRates['sabtu_full'];
                            } elseif ($lemburTotal >= 2) {
                                $uangMakanNominal = $umRates['sabtu_dua'];
                            }
                        } else {
                            if ($lemburTotal >= 2) {
                                $uangMakanNominal = 15000;
                            }
                        }
                    }

                    $totalHariKerja += $upahHarian;
                    $totalOvertime += $totalOvertimeNominal;
                    $totalUangMakan += $uangMakanNominal;

                    $days[$dateStr] = [
                        'kode'             => $kode,
                        'durasi'           => $lemburTotal > 0 ? $lemburTotal : 0,
                        'upah_per_hari'    => $upahHarian,
                        'tarif_lembur'     => $upahLemburPerJam,
                        'uang_makan'       => $uangMakanNominal,
                        'overtime_nominal' => $totalOvertimeNominal,
                        'konfirmasi'       => ($kode && $lemburTotal > 0) ? 'L' : '',
                    ];
                } else {
                    // ═══════════════════════════════════════════════
                    //  SECTION A — ALL IN (Uang Makan logic)
                    // ═══════════════════════════════════════════════
                    $kode = '';
                    $nominal = 0;
                    $lm = 0;

                    if ($lemburTotal > 0) {
                        $isMingguHoliday = ($dayOfWeek == 0 || $isHoliday);
                        if ($isMingguHoliday) {
                            $kode = 'MGG';
                            if ($lemburTotal >= 8) {
                                $nominal = $umRates['minggu_full'];
                                $lm = 8;
                            } elseif ($lemburTotal >= 4) {
                                $nominal = $umRates['minggu_half'];
                                $lm = 4;
                            }
                        } elseif ($dayOfWeek == 6) {
                            $kode = 'SBT';
                            if ($lemburTotal >= 4) {
                                $nominal = $umRates['sabtu_full'];
                                $lm = $lemburTotal;
                            } elseif ($lemburTotal >= 2) {
                                $nominal = $umRates['sabtu_dua'];
                                $lm = $lemburTotal;
                            }
                        } else {
                            // Weekday — only if >= 3 jam
                            if ($lemburTotal >= 3) {
                                $kode = 'UM';
                                $nominal = $umRates['weekday'];
                                $lm = $lemburTotal;
                            }
                        }
                    }

                    $totalHariKerja += $upahHarian;
                    $totalOvertime += $totalOvertimeNominal;
                    $totalUangMakan += $nominal;

                    $days[$dateStr] = [
                        'kode'          => $kode,
                        'ha'            => $ha,
                        'upah_per_hari' => $upahHarian,
                        'lm'            => $lm > 0 ? $lm : 0,
                        'lembur'        => $overtimeDisplay,
                        'nominal'       => $nominal,
                    ];
                }
            }

            $gender = $employee->gender ?? '';

            $item = [
                'id'                   => $employee->id,
                'name'                 => $employee->name,
                'jabatan'              => $employee->position->name ?? '-',
                'gender'               => $gender,
                'tj_mk'                => $tjMk,
                'tunjangan'            => $tunjangan,
                'upah_per_hari'        => $upahPerHari,
                'upah_lembur_per_jam'  => $upahLemburPerJam,
                'days'                 => $days,
                'total_hari_kerja'     => round($totalHariKerja, 2),
                'total_overtime'       => round($totalOvertime, 2),
                'total_uang_makan'     => round($totalUangMakan, 2),
                'total_terima'         => round($totalHariKerja + $totalOvertime + $totalUangMakan, 2),
            ];

            if ($isPrinting) {
                $printingEmployees->push($item);
            } else {
                $allInEmployees->push($item);
            }
        }

        $allInEmployees = $allInEmployees->sortBy('name')->values();
        $printingEmployees = $printingEmployees->sortBy('name')->values();

        $sections = [
            [
                'label'  => 'A. KARYAWAN ALL IN',
                'key'    => 'all_in',
                'type'   => 'uang_makan',
                'data'   => $allInEmployees,
                'totals' => $this->calculateSectionTotals($allInEmployees),
            ],
            [
                'label'  => 'B. KARYAWAN BULANAN PRINTING',
                'key'    => 'printing',
                'type'   => 'lembur',
                'data'   => $printingEmployees,
                'totals' => $this->calculateSectionTotals($printingEmployees),
            ],
        ];

        $allData = $allInEmployees->concat($printingEmployees);
        $grandTotals = $this->calculateSectionTotals($allData);

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
        $sectionMeta = [
            'all_in'   => ['label' => 'A. ALL IN',        'uang_makan_key' => 'nominal'],
            'printing' => ['label' => 'B. BULANAN PRINTING', 'uang_makan_key' => 'uang_makan'],
        ];
        foreach ($sectionMeta as $sectionKey => $meta) {
            $sectionEmps = $allEmployees->where('_section_key', $sectionKey);
            $posGroups = $sectionEmps->groupBy('jabatan');
            $umKey = $meta['uang_makan_key'];

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
                    $overtime  = $emps->sum(fn($e) => $e['days'][$dateStr]['overtime_nominal'] ?? 0);
                    $uangMakan = $emps->sum(fn($e) => $e['days'][$dateStr][$umKey] ?? 0);
                    $days[$dateStr] = [
                        'hari_kerja' => round($hariKerja, 2),
                        'overtime'   => round($overtime, 2),
                        'uang_makan' => round($uangMakan, 2),
                    ];
                    $totalHariKerja += $hariKerja;
                    $totalOvertime += $overtime;
                    $totalUangMakan += $uangMakan;
                }

                $data[] = [
                    'bagian'            => $posName,
                    'l'                 => $l,
                    'p'                 => $p,
                    'days'              => $days,
                    'total_hari_kerja'  => round($totalHariKerja, 2),
                    'total_overtime'    => round($totalOvertime, 2),
                    'total_uang_makan'  => round($totalUangMakan, 2),
                    'total_terima'      => round($totalHariKerja + $totalOvertime, 2),
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
        $rateWeekday = $gajiPokok > 0 ? round($gajiPokok / 25) : 0;

        if (str_contains($groupName, 'KABAG')) {
            return [
                'weekday'      => $rateWeekday,
                'sabtu_dua'    => 55000,
                'sabtu_full'   => 110000,
                'minggu_half'  => 110000,
                'minggu_full'  => 220000,
            ];
        } elseif (str_contains($groupName, 'KEPALA SHIFT') || str_contains($groupName, 'KASHIFT')) {
            return [
                'weekday'      => $rateWeekday,
                'sabtu_dua'    => 52500,
                'sabtu_full'   => 105000,
                'minggu_half'  => 105000,
                'minggu_full'  => 210000,
            ];
        } elseif (str_contains($groupName, 'ALL IN') || str_contains($groupName, 'ALL-IN')) {
            return [
                'weekday'      => $rateWeekday,
                'sabtu_dua'    => 50000,
                'sabtu_full'   => 100000,
                'minggu_half'  => 100000,
                'minggu_full'  => 200000,
            ];
        } else {
            return [
                'weekday'      => $rateWeekday,
                'sabtu_dua'    => 0,
                'sabtu_full'   => 0,
                'minggu_half'  => 0,
                'minggu_full'  => 0,
            ];
        }
    }
}
