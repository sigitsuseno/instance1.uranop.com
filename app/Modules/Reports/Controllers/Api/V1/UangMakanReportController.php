<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Reports\Exports\UangMakanExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class UangMakanReportController extends Controller
{
    public function index(Request $request)
    {
        $result = $this->buildReportData($request);
        if (isset($result['error'])) {
            return response()->json($result['error'], $result['code'] ?? 400);
        }
        return response()->json($result);
    }

    public function export(Request $request)
    {
        $result = $this->buildReportData($request);
        if (isset($result['error'])) {
            return response()->json($result['error'], $result['code'] ?? 400);
        }

        $periodName = $result['period']['start'] . '_' . $result['period']['end'];
        $filename = 'Uang_Makan_' . $periodName . '.xlsx';

        return Excel::download(new UangMakanExport($result, $result['period']), $filename);
    }

    public function print(Request $request)
    {
        $result = $this->buildReportData($request);
        if (isset($result['error'])) {
            return response($result['error']['message'] ?? 'Error', 400);
        }

        $data = $result['data'];
        $resumeData = $result['resumeData'];
        $period = $result['period'];
        $dates = $result['dates'];

        $html = $this->renderPrintHtml($data, $resumeData, $period, $dates);

        return response($html);
    }

    // ──────────────────────────────────────────────────────────────
    //  Core data builder
    // ──────────────────────────────────────────────────────────────

    private function buildReportData(Request $request): array
    {
        $periodId = $request->input('period_id');
        $search = $request->input('search');
        $groups = $request->input('groups', []);

        if (!$periodId) {
            return ['error' => ['success' => false, 'message' => 'Pay Period ID is required.'], 'code' => 400];
        }

        $period = PayPeriod::find($periodId);
        if (!$period) {
            return ['error' => ['success' => false, 'message' => 'Pay Period not found.'], 'code' => 404];
        }

        $startDate = $period->start_date;
        $endDate = $period->end_date;

        $employeesQuery = \App\Modules\Employee\Models\Employee::query()
            ->whereHas('shiftRosters', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date', [$startDate, $endDate]);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                         ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->when(!empty($groups), function ($q) use ($groups) {
                $q->whereHas('groups', function ($gq) use ($groups) {
                    $gq->whereIn('reference_code', $groups);
                });
            })
            ->with([
                'department:id,name',
                'position:id,name',
                'groups' => function ($q) {
                    $q->whereHas('master', function ($mq) {
                        $mq->where('group_label', 'Uang Makan');
                    })->with('master');
                },
            ])
            ->get();

        $employeeIds = $employeesQuery->pluck('id')->toArray();
        $attPrepares = AttendancePrepare::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Dates array
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = [
                'date' => $current->toDateString(),
                'day_name' => $current->translatedFormat('l'),
                'day_short' => $current->translatedFormat('D'),
                'serial' => $current->format('Ymd'),
            ];
            $current->addDay();
        }

        $data = [];
        $resumeGrouped = [];

        foreach ($employeesQuery as $i => $employee) {
            $logs = $attPrepares->get($employee->id)?->keyBy(fn($l) => Carbon::parse($l->date)->toDateString()) ?? collect();

            $groupMaster = $employee->groups->first()?->master;
            $groupName = strtoupper($groupMaster?->name ?? '');

            $rateWeekday = 15000;

            if (str_contains($groupName, 'KABAG')) {
                $rateSabtuDua = 55000;
                $rateSabtuFull = 110000;
                $rateMingguSetengah = 110000;
                $rateMingguFull = 220000;
            } elseif (str_contains($groupName, 'KEPALA SHIFT') || str_contains($groupName, 'KASHIFT')) {
                $rateSabtuDua = 52522;
                $rateSabtuFull = 105000;
                $rateMingguSetengah = 105000;
                $rateMingguFull = 210000;
            } elseif (str_contains($groupName, 'ALL IN') || str_contains($groupName, 'ALL-IN')) {
                $rateSabtuDua = 50000;
                $rateSabtuFull = 100000;
                $rateMingguSetengah = 100000;
                $rateMingguFull = 200000;
            } else {
                $rateSabtuDua = 0;
                $rateSabtuFull = 0;
                $rateMingguSetengah = 0;
                $rateMingguFull = 0;
            }

            $count_um_weekday = 0;
            $count_sabtu_dua = 0;
            $count_sabtu_full = 0;
            $count_minggu_setengah = 0;
            $count_minggu_full = 0;

            $nominal_um_weekday = 0;
            $nominal_sabtu = 0;
            $nominal_minggu = 0;

            $days = [];

            foreach ($dates as $dateObj) {
                $dateStr = $dateObj['date'];
                $log = $logs->get($dateStr);
                $statusStr = $log ? $log->status : '-';
                $isHoliday = ($statusStr === 'libur');
                $dayOfWeek = Carbon::parse($dateStr)->dayOfWeek;

                // Hitung lembur dari durasi check_in → check_out
                $lembur = 0;
                if ($log && $log->check_in && $log->check_out) {
                    $checkIn = Carbon::parse($log->check_in);
                    $checkOut = Carbon::parse($log->check_out);
                    $totalHours = $checkOut->diffInMinutes($checkIn) / 60;

                    if ($dayOfWeek == 0 || $isHoliday) {
                        // Minggu / Libur: kurangi 0 jam, maks 8
                        $lembur = min($totalHours, 8);
                    } else {
                        // Weekday / Sabtu: kurangi 8 jam, maks 8
                        $lembur = min(max($totalHours - 8, 0), 8);
                    }
                }

                if ($lembur > 0) {
                    if ($dayOfWeek == 0 || $isHoliday) {
                        if ($lembur >= 8) {
                            $count_minggu_full++;
                            $nominal_minggu += $rateMingguFull;
                        } elseif ($lembur >= 4) {
                            $count_minggu_setengah++;
                            $nominal_minggu += $rateMingguSetengah;
                        }
                    } elseif ($dayOfWeek == 6) {
                        if ($lembur >= 4) {
                            $count_sabtu_full++;
                            $nominal_sabtu += $rateSabtuFull;
                        } elseif ($lembur >= 2) {
                            $count_sabtu_dua++;
                            $nominal_sabtu += $rateSabtuDua;
                        }
                    } else {
                        if ($lembur >= 3) {
                            $count_um_weekday++;
                            $nominal_um_weekday += $rateWeekday;
                        }
                    }
                }

                $days[$dateStr] = [
                    'status' => $this->mapStatus($statusStr),
                    'lembur' => $lembur > 0 ? $lembur : '-',
                ];
            }

            $total = $nominal_um_weekday + $nominal_sabtu + $nominal_minggu;

            $item = [
                'no' => $i + 1,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'title' => $groupName,
                'count_um' => $count_um_weekday,
                'count_sabtu_dua' => $count_sabtu_dua,
                'count_sabtu_full' => $count_sabtu_full,
                'count_minggu_setengah' => $count_minggu_setengah,
                'count_minggu_full' => $count_minggu_full,
                'nominal_um' => $nominal_um_weekday,
                'nominal_sabtu' => $nominal_sabtu,
                'nominal_minggu' => $nominal_minggu,
                'nominal_insentif' => 0,
                'nominal_pblt' => 0,
                'nominal_revisi' => 0,
                'total' => $total,
                'days' => $days,
            ];

            $data[] = $item;

            // Resume ALL IN
            if (str_contains($groupName, 'ALL IN') || str_contains($groupName, 'ALL-IN')) {
                $pos = $employee->position?->name ?? 'Tanpa Posisi';
                if (!isset($resumeGrouped[$pos])) {
                    $resumeGrouped[$pos] = [
                        'bagian' => $pos,
                        'uang_makan' => 0,
                        'lembur_sabtu' => 0,
                        'lembur_minggu' => 0,
                        'insentif' => 0,
                        'pblt' => 0,
                        'revisi' => 0,
                        'total' => 0,
                    ];
                }
                $resumeGrouped[$pos]['uang_makan'] += $nominal_um_weekday;
                $resumeGrouped[$pos]['lembur_sabtu'] += $nominal_sabtu;
                $resumeGrouped[$pos]['lembur_minggu'] += $nominal_minggu;
                $resumeGrouped[$pos]['total'] += $total;
            }
        }

        return [
            'success' => true,
            'data' => $data,
            'resumeData' => array_values($resumeGrouped),
            'dates' => $dates,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'period_id' => $periodId,
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Print HTML renderer
    // ──────────────────────────────────────────────────────────────

    private function renderPrintHtml($data, $resumeData, $period, $dates): string
    {
        $periodLabel = ($period['start'] ?? '') . ' s/d ' . ($period['end'] ?? '');

        // Build Perhitungan table rows
        $perhitunganRows = '';
        $i = 0;
        foreach ($data as $emp) {
            $i++;
            $perhitunganRows .= "<tr>
                <td>{$i}</td>
                <td>{$emp['employee_name']}</td>
                <td>{$emp['title']}</td>
                <td>" . ($emp['count_um'] ?: '-') . "</td>
                <td>" . ($emp['count_sabtu_dua'] ?: '-') . "</td>
                <td>" . ($emp['count_sabtu_full'] ?: '-') . "</td>
                <td>" . ($emp['count_minggu_setengah'] ?: '-') . "</td>
                <td>" . ($emp['count_minggu_full'] ?: '-') . "</td>
                <td class='text-right'>" . number_format($emp['nominal_um'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($emp['nominal_sabtu'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($emp['nominal_minggu'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($emp['nominal_insentif'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($emp['nominal_pblt'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($emp['nominal_revisi'], 0, ',', '.') . "</td>
                <td class='text-right font-bold'>" . number_format($emp['total'], 0, ',', '.') . "</td>
            </tr>";
        }

        // Resume table rows
        $resumeRows = '';
        $j = 0;
        foreach ($resumeData as $item) {
            $j++;
            $resumeRows .= "<tr>
                <td>{$j}</td>
                <td>{$item['bagian']}</td>
                <td class='text-right'>" . number_format($item['uang_makan'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($item['lembur_sabtu'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($item['lembur_minggu'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($item['insentif'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($item['pblt'], 0, ',', '.') . "</td>
                <td class='text-right'>" . number_format($item['revisi'], 0, ',', '.') . "</td>
                <td class='text-right font-bold'>" . number_format($item['total'], 0, ',', '.') . "</td>
            </tr>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Uang Makan — Print</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; text-align: center; margin-bottom: 2px; }
        .periode { text-align: center; color: #6b7280; margin-bottom: 16px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; page-break-inside: avoid; }
        th { background: #e8eaed; font-weight: 600; padding: 6px 8px; border: 1px solid #d1d5db; font-size: 10px; text-align: center; }
        td { padding: 5px 8px; border: 1px solid #e5e7eb; }
        tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .section-title { font-size: 13px; font-weight: 700; margin: 16px 0 8px; padding: 6px 10px; background: #f3f4f6; border-radius: 4px; }
        .total-row { background: #f3f4f6 !important; font-weight: 700; }
        @media print {
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <h1>LAPORAN UANG MAKAN</h1>
    <p class="periode">Periode: {$periodLabel}</p>

    <div class="section-title">Perhitungan Uang Makan</div>
    <table>
        <thead>
            <tr>
                <th>No</th><th>Nama</th><th>Grup UM</th><th>UM</th>
                <th>Sabtu Dua</th><th>Sabtu Full</th>
                <th>Minggu 1/2</th><th>Minggu L</th>
                <th>Uang Makan</th><th>Lembur Sabtu</th><th>Lembur Minggu</th>
                <th>Insentif</th><th>PBLT</th><th>Revisi</th><th>Total</th>
            </tr>
        </thead>
        <tbody>{$perhitunganRows}</tbody>
    </table>

    <div class="section-title">Resume ALL IN</div>
    <table>
        <thead>
            <tr>
                <th>No</th><th>Bagian</th>
                <th>Uang Makan</th><th>Lembur Sabtu</th><th>Lembur Minggu</th>
                <th>Insentif</th><th>PBLT</th><th>Revisi</th><th>Total</th>
            </tr>
        </thead>
        <tbody>{$resumeRows}</tbody>
    </table>

    <div class="no-print" style="text-align:center; margin-top:20px;">
        <button onclick="window.print()" style="padding:10px 24px; font-size:14px; cursor:pointer; background:#4f46e5; color:white; border:none; border-radius:6px;">
            🖨️ Print
        </button>
    </div>
</body>
</html>
HTML;
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    private function mapStatus($statusStr)
    {
        return match (strtolower($statusStr)) {
            'hadir', 'terlambat' => 'H',
            'cuti' => 'CUTI',
            'izin' => 'I',
            'absent', 'alpa' => 'A',
            'libur' => 'LIBUR',
            'off' => 'OFF',
            'sakit' => 'SAKIT',
            default => '-'
        };
    }
}
