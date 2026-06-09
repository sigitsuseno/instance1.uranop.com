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

    // ─── Monthly ──────────────────────────────────────────────────

    public function bulanan(Request $request)
    {
        $data = $this->buildBulananData($request);
        return response()->json(['data' => $data]);
    }

    public function exportBulanan(Request $request)
    {
        $data = $this->buildBulananData($request);
        $year = $request->input('year', date('Y'));
        $filename = 'Laporan_Lembur_Bulanan_' . $year . '.xlsx';
        return Excel::download(new LemburBulananExport($data->values()->toArray(), $year), $filename);
    }

    public function printBulanan(Request $request)
    {
        $data = $this->buildBulananData($request);
        $year = $request->input('year', date('Y'));
        $html = $this->renderBulananPrintHtml($data, $year);
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
            ->with(['position'])
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
            $totalMenit = $lmCount + $overtimeCount;
            $uangLembur = $hourlyRate > 0 ? round($hourlyRate * ($totalMenit / 60), 2) : 0;

            $shiftKode = $roster && $roster->shift ? $roster->shift->external_code : '';
            $status = $prepare ? $prepare->status : '-';

            return [
                'id' => $employee->id, 'name' => $employee->name,
                'jabatan' => $employee->position->name ?? '-',
                'gender' => $employee->gender ?? '',
                'tj_mk' => $tjMk, 'tunjangan' => $tunjangan,
                'upah_per_hari' => $upahPerHari, 'upah_lembur_per_jam' => $hourlyRate,
                'shift_kode' => $shiftKode, 'status' => $status,
                'lembur_minggu' => $lmRaw > 0 ? round($lmRaw / 60, 2) : 0,
                'lembur' => $overtimeRaw > 0 ? round($overtimeRaw / 60, 2) : 0,
                'nominal' => $uangLembur,
                'group_name' => $employee->groups->pluck('reference_code')->first() ?? '',
            ];
        });
    }

    private function buildBulananData(Request $request)
    {
        $year = (int)$request->input('year', date('Y'));
        $groups = $request->input('groups', []);

        $prepares = AttendancePrepare::whereYear('date', $year)->get()->groupBy('employee_id');

        $employees = Employee::query()
            ->whereHas('shiftRosters', fn($q) => $q->whereYear('date', $year))
            ->when(!empty($groups), fn($q) => $q->whereHas('groups', fn($gq) => $gq->whereIn('reference_code', $groups)))
            ->get();

        $periodIds = PayPeriod::whereYear('end_date', $year)->pluck('id');
        $payRecords = PayRecord::whereIn('pay_period_id', $periodIds)->get()->groupBy('employee_id');

        return $employees->map(function ($employee) use ($prepares, $payRecords) {
            $empPrepares = $prepares->get($employee->id, collect());
            $empPayRecords = $payRecords->get($employee->id, collect());
            $latestPayRecord = $empPayRecords->sortByDesc('created_at')->first();

            $gaji = $latestPayRecord ? (float)($latestPayRecord->gaji_pokok ?? 0) : $employee->baseSalary();
            $premi = $latestPayRecord ? (float)($latestPayRecord->premi ?? 0) : (float)($employee->activeSalary()?->premi ?? 0);
            $tjMk = $latestPayRecord ? (float)($latestPayRecord->tj_masa_kerja ?? 0) : (float)($employee->salaryComponents()->latest('effective_date')->first()?->tunjangan_masa_kerja ?? 0);
            // Fallback dinamis: hitung dari join_date kalau static value masih 0
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($year . '-12');
            }
            $tunjangan = $latestPayRecord ? (float)($latestPayRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);
            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            $months = [];
            $preparesByMonth = $empPrepares->groupBy(fn($p) => Carbon::parse($p->date)->month);

            for ($m = 1; $m <= 12; $m++) {
                $mp = $preparesByMonth->get($m, collect());
                $lmTotal = $mp->sum('lm');
                $overtimeTotal = $mp->sum('overtime');
                $lmCountTotal = $mp->sum('lm_count');
                $overtimeCountTotal = $mp->sum('overtime_count');
                $totalMinutes = $lmCountTotal + $overtimeCountTotal;
                $overtimePay = $hourlyRate > 0 ? round($hourlyRate * ($totalMinutes / 60), 2) : 0;

                $months[$m] = [
                    'lm' => $lmTotal, 'overtime' => $overtimeTotal,
                    'calculated' => round(($lmCountTotal + $overtimeCountTotal) / 60, 2),
                    'hourlyRate' => $hourlyRate, 'overtimePay' => $overtimePay,
                ];
            }

            return [
                'id' => $employee->id, 'name' => $employee->name,
                'gaji_pokok' => $gaji, 'premi' => $premi, 'tj_mk' => $tjMk, 'tunjangan' => $tunjangan,
                'months' => $months,
                'group_name' => $employee->groups->pluck('reference_code')->first() ?? '',
            ];
        });
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
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['name']}</td><td>{$item['jabatan']}</td><td>{$gender}</td>
                <td class='text-right'>".number_format($item['tj_mk'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tunjangan'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['upah_per_hari'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['upah_lembur_per_jam'],0,',','.')."</td>
                <td>{$item['shift_kode']}</td><td>{$item['status']}</td>
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
<th>Tj.MK</th><th>Tunjangan</th><th>Upah/Hari</th><th>Upah Lbr/Jam</th>
<th>Kode</th><th>H/A</th><th>L/M</th><th>Lembur</th><th>Nominal</th>
</tr></thead><tbody>{$rows}</tbody></table>
<div style="text-align:center;margin-top:16px"><button onclick="window.print()" style="padding:10px 24px;font-size:14px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨️ Print</button></div>
</body></html>
HTML;
    }

    private function renderBulananPrintHtml($data, $year)
    {
        $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $monthsHeader = '<th>' . implode('</th><th>', $months) . '</th>';
        $rows = '';
        $i = 0;
        foreach ($data as $item) {
            $i++;
            $cells = '';
            for ($m = 1; $m <= 12; $m++) {
                $md = $item['months'][$m] ?? null;
                if ($md) {
                    $cells .= "<td style='font-size:9px'>Upah/jam: ".number_format($md['hourlyRate'],0,',','.')."<br>Lembur: {$md['lm']} / {$md['calculated']}<br>Uang Lbr: ".number_format($md['overtimePay'],0,',','.')."</td>";
                } else {
                    $cells .= "<td class='text-center'>-</td>";
                }
            }
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['name']}</td>
                <td class='text-right'>".number_format($item['gaji_pokok'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['premi'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tj_mk'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tunjangan'],0,',','.')."</td>
                {$cells}
            </tr>";
        }

        return '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Lembur Bulanan</title>
<style>
@page{size:A4 landscape;margin:6mm}body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:9px;color:#1f2937}
h1{font-size:15px;text-align:center;margin-bottom:2px}
table{width:100%;border-collapse:collapse}th{background:#e8eaed;font-weight:600;padding:4px 5px;border:1px solid #d1d5db;font-size:8px;text-align:center}
td{padding:3px 5px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}.text-center{text-align:center}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>LAPORAN LEMBUR BULANAN — TAHUN ' . $year . '</h1>
<table><thead><tr>
<th>No</th><th>Nama</th><th>Gaji Pokok</th><th>Premi</th><th>Tj. MK</th><th>Tunjangan</th>
' . $monthsHeader . '</tr></thead><tbody>' . $rows . '</tbody></table>
<div style="text-align:center;margin-top:12px"><button onclick="window.print()" style="padding:8px 20px;font-size:13px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨️ Print</button></div>
</body></html>';
    }
}
