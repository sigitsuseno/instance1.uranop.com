<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Absensi - {{ $employee['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; padding: 20px; }
        .header { margin-bottom: 12px; }
        .header h2 { font-size: 16px; margin-bottom: 2px; }
        .header .info { font-size: 11px; color: #555; }
        .header .period { font-size: 10px; color: #888; margin-top: 2px; }
        .summary { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
        .stat-card { border: 1px solid #ddd; border-radius: 6px; padding: 6px 10px; text-align: center; min-width: 65px; }
        .stat-card .num { font-size: 14px; font-weight: bold; }
        .stat-card .lbl { font-size: 8px; color: #888; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; border: 1px solid #ccc; padding: 5px 3px; text-align: center; font-size: 9px; font-weight: 600; white-space: nowrap; }
        td { border: 1px solid #ddd; padding: 3px; text-align: center; font-size: 9px; }
        td.left { text-align: left; }
        .weekend { background: #fef2f2; }
        .text-green { color: #16a34a; font-weight: bold; }
        .text-red { color: #dc2626; font-weight: bold; }
        .text-orange { color: #ea580c; }
        .text-blue { color: #2563eb; }
        .text-purple { color: #7c3aed; }
        .text-teal { color: #0d9488; }
        .text-indigo { color: #4f46e5; font-weight: bold; }
        .text-gray { color: #9ca3af; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 8px; font-weight: 600; }
        .badge-present { background: #dcfce7; color: #16a34a; }
        .badge-absent { background: #fee2e2; color: #dc2626; }
        .badge-leave { background: #dbeafe; color: #2563eb; }
        .badge-izin { background: #f3e8ff; color: #7c3aed; }
        .badge-sakit { background: #ccfbf1; color: #0d9488; }
        .badge-holiday { background: #f3f4f6; color: #6b7280; }
        .badge-off { background: #fff7ed; color: #ea580c; }
        .badge-pending { background: #fef9c3; color: #a16207; }
        .badge-permit { background: #f3e8ff; color: #7c3aed; }
        .multiplier { font-size: 7.5px; color: #6b7280; line-height: 1.3; }
        @media print { body { padding: 8px; } }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $employee['name'] }}</h2>
        <div class="info">{{ $employee['code'] }} | {{ $employee['department'] }} | {{ $employee['position'] }}</div>
        <div class="period">Periode: {{ \Carbon\Carbon::parse($period['start'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d M Y') }}</div>
    </div>

    <div class="summary">
        <div class="stat-card">
            <div class="num text-green">{{ $summary['hadir'] }}</div>
            <div class="lbl">Hadir</div>
        </div>
        <div class="stat-card">
            <div class="num text-orange">{{ number_format($summary['lembur'] ?? 0, 1, ',', '.') }} j</div>
            <div class="lbl">Lembur Mentah</div>
        </div>
        <div class="stat-card">
            <div class="num text-indigo">{{ number_format($summary['lembur_calc'] ?? 0, 1, ',', '.') }} j</div>
            <div class="lbl">Lembur+Lm Calc</div>
        </div>
        <div class="stat-card">
            <div class="num text-blue">{{ $summary['cuti'] }}</div>
            <div class="lbl">Cuti</div>
        </div>
        <div class="stat-card">
            <div class="num text-purple">{{ $summary['izin'] }}</div>
            <div class="lbl">Izin</div>
        </div>
        <div class="stat-card">
            <div class="num text-teal">{{ $summary['sakit'] }}</div>
            <div class="lbl">Sakit</div>
        </div>
        <div class="stat-card">
            <div class="num text-red">{{ $summary['absen'] }}</div>
            <div class="lbl">Absen</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Tanggal</th>
                <th>H</th>
                <th>Jdwl In</th>
                <th>Jdwl Out</th>
                <th>In</th>
                <th>Out</th>
                <th>Lembur</th>
                <th>Multiplier</th>
                <th>Total Lembur</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyData as $i => $day)
            @php
                $lemburMin = $day['lembur'] ?? 0;
                $lemburH = $lemburMin / 60;
                $isFixed = $day['is_fixed'] ?? false;
                $isSat = $day['is_sat'] ?? false;
                $isHoliday = $day['is_holiday'] ?? false;
                $totalCalc = $day['lembur_total_calc'] ?? 0;
                
                // Compute multiplier details
                $multiplierLines = [];
                if ($lemburMin > 0) {
                    if ($isFixed && $isHoliday) {
                        $remaining = max(0, $lemburH - 1);
                        if ($lemburH > 0) {
                            $multiplierLines[] = '(' . number_format($lemburH, 2, ',', '.') . ' - 1) × 2 = ' . number_format($remaining * 2, 2, ',', '.');
                        }
                    } else {
                        $first = min($lemburH, 1);
                        if ($first > 0) {
                            $multiplierLines[] = number_format($first, 2, ',', '.') . ' × 1,5 = ' . number_format($first * 1.5, 2, ',', '.');
                        }
                        if ($lemburH > 1) {
                            $rest = $lemburH - 1;
                            $multiplierLines[] = number_format($rest, 2, ',', '.') . ' × 2,0 = ' . number_format($rest * 2, 2, ',', '.');
                        }
                    }
                }
            @endphp
            <tr class="{{ $day['is_weekend'] ? 'weekend' : '' }}">
                <td>{{ $i + 1 }}</td>
                <td class="left">{{ $day['date_display'] }}</td>
                <td>{{ $day['day'] }}</td>
                <td>{{ $day['shift_start'] ?? '--:--' }}</td>
                <td>{{ $day['shift_end'] ?? '--:--' }}</td>
                <td class="text-indigo">{{ $day['check_in'] ?? '--:--' }}</td>
                <td class="text-indigo">{{ $day['check_out'] ?? '--:--' }}</td>
                <td>
                    @if($lemburMin > 0)
                        <span class="text-orange">{{ number_format($lemburH, 1, ',', '.') }} j</span>
                    @else
                        <span class="text-gray">-</span>
                    @endif
                </td>
                <td>
                    @if($lemburMin > 0)
                        <div class="multiplier">
                            @foreach($multiplierLines as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray">-</span>
                    @endif
                </td>
                <td>
                    @if($totalCalc > 0)
                        <span class="text-indigo">{{ number_format($totalCalc, 1, ',', '.') }} j</span>
                    @else
                        <span class="text-gray">-</span>
                    @endif
                </td>
                <td>
                    @php $s = $day['status'] ?? 'pending'; @endphp
                    <span class="badge badge-{{ $s }}">{{ $day['status_label'] ?? ucfirst($s) }}</span>
                </td>
            </tr>
            @empty
            <tr><td colspan="11">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.print();</script>
</body>
</html>
