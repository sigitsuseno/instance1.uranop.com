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
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class LaporanLemburController extends Controller
{
    // ─── Daily ────────────────────────────────────────────────────

    public function harian(Request $request)
    {
        $data = $this->buildHarianData($request);
        return response()->json(['data' => $data]);
    }

    public function exportHarian(Request $request)
    {
        $data = $this->buildHarianData($request);
        $date = $request->input('date', date('Y-m-d'));
        $filename = 'Laporan_Lembur_Harian_' . $date . '.xlsx';
        return Excel::download(new LemburHarianExport($data->values()->toArray(), $date), $filename);
    }

    public function printHarian(Request $request)
    {
        $data = $this->buildHarianData($request);
        $date = $request->input('date', date('Y-m-d'));
        $html = $this->renderHarianPrintHtml($data, $date);
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
        $date = $request->input('date', date('Y-m-d'));
        $groups = $request->input('groups', []);

        $parsedDate = Carbon::parse($date);

        $prepares = AttendancePrepare::whereDate('date', $parsedDate)
            ->get()->keyBy('employee_id');

        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereDate('date', $parsedDate))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->with(['position', 'groups'])
            ->get();

        $period = PayPeriod::where('start_date', '<=', $parsedDate)
            ->where('end_date', '>=', $parsedDate)->first();

        $payRecords = collect();
        if ($period) {
            $payRecords = PayRecord::where('pay_period_id', $period->id)->get()->groupBy('employee_id');
        }

        $rosters = EmployeeShiftRoster::whereDate('date', $parsedDate)
            ->with('shift')->get()->keyBy('employee_id');

        return $employees->map(function ($employee) use ($prepares, $payRecords, $period, $parsedDate, $rosters) {
            $prepare = $prepares->get($employee->id);
            $roster = $rosters->get($employee->id);
            $empPayRecords = $payRecords->get($employee->id);
            $payRecord = null;

            if ($empPayRecords && $empPayRecords->isNotEmpty()) {
                if ($period && $period->is_split) {
                    $day = (int) $parsedDate->day;
                    $cutOff = (int) ($period->cut_off_date ?? 25);
                    $segment = ($day >= $cutOff && $day <= 31) ? 'A' : 'B';
                    $payRecord = $empPayRecords->where('segment', $segment)->first() ?? $empPayRecords->first();
                } else {
                    $payRecord = $empPayRecords->first();
                }
            }

            $gaji = $payRecord ? (float)($payRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $tjMk = $payRecord ? (float)($payRecord->tj_masa_kerja ?? 0) : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            // Fallback dinamis: hitung dari join_date kalau static value masih 0
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($parsedDate->format('Y-m'));
            }
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);

            $upahPerHari = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 25, 2) : 0;
            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            // Display: raw values (non-count) — buat tampilan L/M & Lembur
            $lmRaw = $prepare ? (int)$prepare->lm : 0;
            $overtimeRaw = $prepare ? (int)$prepare->overtime : 0;

            // Calculation: count values (setelah multiplier) — buat hitung uang
            $lmCount = $prepare ? (int)$prepare->lm_count : 0;
            $overtimeCount = $prepare ? (int)$prepare->overtime_count : 0;

            // LM: jam pertama gratis (istirahat), sisanya dibayar
            $lmCountHours = $lmCount / 60;
            $lmNominal = $lmCountHours > 1 ? round(($lmCountHours - 1) * $hourlyRate, 2) : 0;
            // Lembur biasa: semua jam dibayar
            $overtimeNominal = $overtimeCount > 0 ? round(($overtimeCount / 60) * $hourlyRate, 2) : 0;
            $totalNominal = round($lmNominal + $overtimeNominal, 2);

            // Display hours
            $lmDisplay = $lmRaw > 0 ? round($lmRaw / 60, 2) : 0;
            $overtimeDisplay = $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0;

            // Kode: SG jika employee group SG, else "L"
            // Ditampilkan kecuali status: absent, libur, off, itm, izn, atau no data
            $statusRaw = $prepare ? $prepare->status : '-';
            $isSG = $employee->groups->contains('reference_code', 'SG');
            if (in_array($statusRaw, ['absent', 'libur', 'off', 'itm', 'izn', '-'])) {
                $kode = '';
            } else {
                $kode = $isSG ? 'SG' : 'L';
            }

            // H/A: mapping from DB codes (lowercase abbreviations)
            // DB codes: hadir, absent, libur, off, skt, ct/cm/ckm/cth/ctm/..., itm/imt/ipa/izn/...
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
            $dapatUpah = in_array($ha, ['H', 'C', 'S']);
            $upahHarian = $dapatUpah ? $upahPerHari : 0;

            return [
                'id' => $employee->id, 'name' => $employee->name,
                'jabatan' => $employee->position->name ?? '-',
                'gender' => $employee->gender ?? '',
                'tj_mk' => $tjMk, 'tunjangan' => $tunjangan,
                'upah_lembur_per_jam' => $hourlyRate,
                'kode' => $kode, 'ha' => $ha,
                'upah_per_hari' => $upahHarian,
                'lembur_minggu' => $lmDisplay,
                'lembur' => $overtimeDisplay,
                'nominal' => $totalNominal,
                'group_name' => $employee->groups->pluck('reference_code')->first() ?? '',
            ];
        });
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

                // LM: jam pertama gratis, sisanya dibayar
                $lmCountHours = $lmCount / 60;
                $lmNominal = $lmCountHours > 1 ? round(($lmCountHours - 1) * $hourlyRate, 2) : 0;
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
                $dapatUpah = in_array($ha, ['H', 'C', 'S']);
                $upahHarian = $dapatUpah ? $upahPerHari : 0;

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

    private function renderHarianPrintHtml($data, $date)
    {
        $formatted = Carbon::parse($date)->translatedFormat('l, d F Y');
        $rows = '';
        $i = 0;
        foreach ($data as $item) {
            $i++;
            $gender = $item['gender'] === 'male' ? 'L' : ($item['gender'] === 'female' ? 'P' : ($item['gender'] ?? ''));
            $upahHari = $item['upah_per_hari'] ? number_format($item['upah_per_hari'], 0, ',', '.') : '-';
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['name']}</td><td>{$item['jabatan']}</td><td>{$gender}</td>
                <td class='text-right'>".number_format($item['tj_mk'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tunjangan'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['upah_lembur_per_jam'],0,',','.')."</td>
                <td>{$item['kode']}</td><td>{$item['ha']}</td>
                <td class='text-right'>{$upahHari}</td>
                <td>".($item['lembur_minggu']?:'-')."</td><td>".($item['lembur']?:'-')."</td>
                <td class='text-right'>".number_format($item['nominal'],0,',','.')."</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Lembur Harian</title>
<style>
@page{size:A4 landscape;margin:8mm}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:10px;color:#1f2937}
h1{font-size:15px;text-align:center;margin-bottom:2px}.periode{text-align:center;color:#6b7280;margin-bottom:12px;font-size:11px}
table{width:100%;border-collapse:collapse;margin-bottom:16px}
th{background:#e8eaed;font-weight:600;padding:5px 6px;border:1px solid #d1d5db;font-size:9px;text-align:center}
td{padding:4px 6px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right} .no-print{display:none}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.no-print{display:none}}
</style></head><body>
<h1>LAPORAN LEMBUR HARIAN</h1>
<p class="periode">Tanggal: {$formatted}</p>
<table><thead><tr>
<th>No</th><th>Nama</th><th>Bagian/Jabatan</th><th>L/P</th>
<th>Tj.MK</th><th>Tunjangan</th><th>Upah Lbr/Jam</th>
<th>Kode</th><th>H/A</th><th>Upah/Hari</th><th>L/M</th><th>Lembur</th><th>Nominal</th>
</tr></thead><tbody>{$rows}</tbody></table>
<div style="text-align:center;margin-top:16px"><button onclick="window.print()" style="padding:10px 24px;font-size:14px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨️ Print</button></div>
</body></html>
HTML;
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
}
