<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Reports\Exports\UangMakanHarianExport;
use App\Modules\Reports\Exports\UangMakanBulananExport;
use App\Modules\Reports\Exports\UangMakanResumeExport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UangMakanReportController extends Controller
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
        $filename = 'Uang_Makan_Harian_' . $date . '.xlsx';
        return Excel::download(new UangMakanHarianExport($data->values()->toArray(), $date), $filename);
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
            new UangMakanResumeExport($result['data'], $result['dates'], $label),
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

    // ─── Data Builders ────────────────────────────────────────────

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
            ->with(['position', 'groups.master'])
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
            if ($tjMk == 0) {
                $tjMk = $employee->tunjangan_masa_kerja($parsedDate->format('Y-m'));
            }
            $tunjangan = $payRecord ? (float)($payRecord->tunjangan ?? 0) : (float)($employee->activeSalary()?->tunjangan ?? 0);
            $hourlyRate = $gaji > 0 ? round(($gaji + $tjMk + $tunjangan) / 173, 2) : 0;

            // ── Uang Makan calculation ──
            $groupName = $this->getGroupName($employee);
            $rates = $this->getGroupRates($groupName);

            $lembur = 0;
            $statusRaw = $prepare ? $prepare->status : '-';
            $isHoliday = ($statusRaw === 'libur');
            $dayOfWeek = $parsedDate->dayOfWeek;

            if ($prepare && $prepare->check_in && $prepare->check_out) {
                $checkIn = Carbon::parse($prepare->check_in);
                $checkOut = Carbon::parse($prepare->check_out);
                $totalHours = $checkOut->diffInMinutes($checkIn) / 60;

                if ($dayOfWeek == 0 || $isHoliday) {
                    $lembur = min($totalHours, 8);
                } else {
                    $lembur = min(max($totalHours - 8, 0), 8);
                }
            }

            $dayInfo = $this->buildDayInfo($lembur, $dayOfWeek, $isHoliday, $rates, $statusRaw, $prepare);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'jabatan' => $employee->position->name ?? '-',
                'gender' => $employee->gender ?? '',
                'tj_mk' => $tjMk,
                'tunjangan' => $tunjangan,
                'upah_lembur_per_jam' => $hourlyRate,
                'kode' => $dayInfo['kode'],
                'ha' => $dayInfo['ha'],
                'upah_per_hari' => $dayInfo['upah_per_hari'],
                'lembur_minggu' => $dayInfo['lm'],
                'lembur' => $dayInfo['lembur'],
                'nominal' => $dayInfo['nominal'],
                'group_name' => $groupName,
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
            $rates = $this->getGroupRates($groupName);

            $days = [];
            foreach ($dates as $dateStr) {
                $prep = $empPrepares->get($dateStr);
                $roster = $empRosters->get($dateStr);
                $parsedDate = Carbon::parse($dateStr);

                $lembur = 0;
                $statusRaw = $prep ? $prep->status : '-';
                $isHoliday = ($statusRaw === 'libur');
                $dayOfWeek = $parsedDate->dayOfWeek;

                if ($prep && $prep->check_in && $prep->check_out) {
                    $checkIn = Carbon::parse($prep->check_in);
                    $checkOut = Carbon::parse($prep->check_out);
                    $totalHours = $checkOut->diffInMinutes($checkIn) / 60;

                    if ($dayOfWeek == 0 || $isHoliday) {
                        $lembur = min($totalHours, 8);
                    } else {
                        $lembur = min(max($totalHours - 8, 0), 8);
                    }
                }

                $dayInfo = $this->buildDayInfo($lembur, $dayOfWeek, $isHoliday, $rates, $statusRaw, $prep);

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
        $result = $this->buildBulananData($request);
        $data = $result['data'];
        $dates = $result['dates'];

        $resumeByPosition = [];

        foreach ($data as $emp) {
            $groupName = $emp['group_name'] ?? '';
            if (!str_contains(strtoupper($groupName), 'ALL IN') && !str_contains(strtoupper($groupName), 'ALL-IN')) {
                continue;
            }

            $pos = $emp['jabatan'] ?? 'Tanpa Posisi';
            if (!isset($resumeByPosition[$pos])) {
                $resumeByPosition[$pos] = [
                    'bagian' => $pos,
                    'uang_makan' => 0,
                    'lembur_sabtu' => 0,
                    'lembur_minggu' => 0,
                    'total' => 0,
                ];
            }

            foreach ($dates as $dateStr) {
                $d = $emp['days'][$dateStr] ?? null;
                if (!$d) continue;

                $parsedDate = Carbon::parse($dateStr);
                $dayOfWeek = $parsedDate->dayOfWeek;

                if ($dayOfWeek == 0) {
                    $resumeByPosition[$pos]['lembur_minggu'] += ($d['nominal'] ?? 0);
                } elseif ($dayOfWeek == 6) {
                    $resumeByPosition[$pos]['lembur_sabtu'] += ($d['nominal'] ?? 0);
                } else {
                    $resumeByPosition[$pos]['uang_makan'] += ($d['nominal'] ?? 0);
                }
                $resumeByPosition[$pos]['total'] += ($d['nominal'] ?? 0);
            }
        }

        return [
            'data' => array_values($resumeByPosition),
            'dates' => $dates,
        ];
    }

    // ─── Shared Helpers ──────────────────────────────────────────

    private function getGroupName($employee): string
    {
        $groupMaster = $employee->groups->first()?->master;
        return strtoupper($groupMaster?->name ?? '');
    }

    private function getGroupRates(string $groupName): array
    {
        $rateWeekday = 15000;

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

    /**
     * Build per-day info for Uang Makan.
     *
     * Returns:
     *   kode   — 'L' if lembur>0 else ''
     *   ha     — status code (H/A/C/S/I/OFF)
     *   upah_per_hari — rate rupiah
     *   lm     — 'DUA'/'FULL'/'HALF' for Minggu/Libur, '' otherwise
     *   lembur — 'UM'/'DUA'/'FULL' for Weekday/Sabtu, '' otherwise
     *   nominal — rate rupiah (same as upah_per_hari)
     */
    private function buildDayInfo(float $lembur, int $dayOfWeek, bool $isHoliday, array $rates, string $statusRaw, $prepare): array
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

        if ($lembur > 0) {
            $kode = 'L';

            if ($dayOfWeek == 0 || $isHoliday) {
                // Minggu / Libur
                if ($lembur >= 8) {
                    $upah = $rates['minggu_full'];
                    $lm = 'FULL';
                } elseif ($lembur >= 4) {
                    $upah = $rates['minggu_half'];
                    $lm = 'HALF';
                }
            } elseif ($dayOfWeek == 6) {
                // Sabtu
                if ($lembur >= 4) {
                    $upah = $rates['sabtu_full'];
                    $lemburStr = 'FULL';
                } elseif ($lembur >= 2) {
                    $upah = $rates['sabtu_dua'];
                    $lemburStr = 'DUA';
                }
            } else {
                // Weekday
                if ($lembur >= 3) {
                    $upah = $rates['weekday'];
                    $lemburStr = 'UM';
                }
            }
        }

        $nominal = $upah;

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

    private function renderHarianPrintHtml($data, $date)
    {
        $formatted = Carbon::parse($date)->translatedFormat('l, d F Y');
        $rows = '';
        $i = 0;
        foreach ($data as $item) {
            $i++;
            $gender = $item['gender'] === 'male' ? 'L' : ($item['gender'] === 'female' ? 'P' : ($item['gender'] ?? ''));
            $upahHari = $item['upah_per_hari'] ? number_format($item['upah_per_hari'], 0, ',', '.') : '-';
            $nominal = $item['nominal'] ? number_format($item['nominal'], 0, ',', '.') : '-';
            $rows .= "<tr>
                <td>{$i}</td><td>{$item['name']}</td><td>{$item['jabatan']}</td><td>{$gender}</td>
                <td class='text-right'>".number_format($item['tj_mk'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['tunjangan'],0,',','.')."</td>
                <td class='text-right'>".number_format($item['upah_lembur_per_jam'],0,',','.')."</td>
                <td>{$item['kode']}</td><td>{$item['ha']}</td>
                <td class='text-right'>{$upahHari}</td>
                <td>".($item['lembur_minggu']?:'-')."</td><td>".($item['lembur']?:'-')."</td>
                <td class='text-right'>{$nominal}</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Laporan Uang Makan Harian</title>
<style>
@page{size:A4 landscape;margin:8mm}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:10px;color:#1f2937}
h1{font-size:15px;text-align:center;margin-bottom:2px}.periode{text-align:center;color:#6b7280;margin-bottom:12px;font-size:11px}
table{width:100%;border-collapse:collapse;margin-bottom:16px}
th{background:#e8eaed;font-weight:600;padding:5px 6px;border:1px solid #d1d5db;font-size:9px;text-align:center}
td{padding:4px 6px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>LAPORAN UANG MAKAN HARIAN</h1>
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
        $data = $result['data'];
        $rows = '';
        $i = 0;
        $totalUang = 0; $totalSabtu = 0; $totalMinggu = 0; $grandTotal = 0;
        foreach ($data as $item) {
            $i++;
            $totalUang += ($item['uang_makan'] ?? 0);
            $totalSabtu += ($item['lembur_sabtu'] ?? 0);
            $totalMinggu += ($item['lembur_minggu'] ?? 0);
            $grandTotal += ($item['total'] ?? 0);
            $rows .= "<tr>
                <td>{$i}</td>
                <td>{$item['bagian']}</td>
                <td class='text-right'>".number_format($item['uang_makan']??0,0,',','.')."</td>
                <td class='text-right'>".number_format($item['lembur_sabtu']??0,0,',','.')."</td>
                <td class='text-right'>".number_format($item['lembur_minggu']??0,0,',','.')."</td>
                <td class='text-right font-bold'>".number_format($item['total']??0,0,',','.')."</td>
            </tr>";
        }
        $rows .= "<tr class='total-row'>
            <td colspan='2' class='text-center'>TOTAL</td>
            <td class='text-right'>".number_format($totalUang,0,',','.')."</td>
            <td class='text-right'>".number_format($totalSabtu,0,',','.')."</td>
            <td class='text-right'>".number_format($totalMinggu,0,',','.')."</td>
            <td class='text-right font-bold'>".number_format($grandTotal,0,',','.')."</td>
        </tr>";

        return <<<HTML
<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Resume Uang Makan</title>
<style>
@page{size:A4 landscape;margin:10mm}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:11px;color:#1f2937}
h1{font-size:16px;text-align:center;margin-bottom:2px}.periode{text-align:center;color:#6b7280;margin-bottom:16px;font-size:12px}
table{width:100%;border-collapse:collapse;margin-bottom:20px}
th{background:#e8eaed;font-weight:600;padding:6px 8px;border:1px solid #d1d5db;font-size:10px;text-align:center}
td{padding:5px 8px;border:1px solid #e5e7eb}tr:nth-child(even){background:#f9fafb}
.text-right{text-align:right}.font-bold{font-weight:700}
.total-row{background:#f3f4f6!important;font-weight:700}
@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style></head><body>
<h1>RESUME UANG MAKAN</h1>
<p class="periode">Periode: {$label}</p>
<table><thead><tr>
<th>No</th><th>Bagian</th><th>Uang Makan</th><th>Lembur Sabtu</th><th>Lembur Minggu</th><th>Total</th>
</tr></thead><tbody>{$rows}</tbody></table>
<div style="text-align:center;margin-top:16px"><button onclick="window.print()" style="padding:10px 24px;font-size:14px;cursor:pointer;background:#4f46e5;color:white;border:none;border-radius:6px">🖨️ Print</button></div>
</body></html>
HTML;
    }
}
