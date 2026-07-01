<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Snapshot Absensi - {{ $period['start'] }} s/d {{ $period['end'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #333; padding: 16px; }
        .header { text-align: center; margin-bottom: 10px; }
        .header h2 { font-size: 15px; margin-bottom: 3px; }
        .header p { font-size: 10px; color: #666; }
        .stats { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
        .stat-card { border: 1px solid #ddd; border-radius: 4px; padding: 4px 10px; text-align: center; min-width: 60px; }
        .stat-card .num { font-size: 14px; font-weight: bold; }
        .stat-card .lbl { font-size: 8px; color: #888; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; border: 1px solid #ddd; padding: 5px 3px; text-align: center; font-size: 9px; font-weight: 600; }
        td { border: 1px solid #ddd; padding: 3px; text-align: center; font-size: 9px; }
        td.left { text-align: left; }
        .text-green { color: #16a34a; font-weight: bold; }
        .text-red { color: #dc2626; font-weight: bold; }
        .text-orange { color: #ea580c; }
        .text-blue { color: #2563eb; }
        .text-purple { color: #7c3aed; }
        .text-indigo { color: #4f46e5; }
        .row-total { background: #f0f0f0; font-weight: bold; }
        @media print {
            body { padding: 8px; }
            @page { size: landscape; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Snapshot Absensi Karyawan</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($period['start'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d M Y') }}</p>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="num">{{ $stats['total_employees'] }}</div>
            <div class="lbl">Karyawan</div>
        </div>
        <div class="stat-card">
            <div class="num text-green">{{ $stats['present'] }}</div>
            <div class="lbl">Hadir</div>
        </div>
        <div class="stat-card">
            <div class="num text-red">{{ $stats['absent'] }}</div>
            <div class="lbl">Absen</div>
        </div>
        <div class="stat-card">
            <div class="num text-blue">{{ $stats['leave'] }}</div>
            <div class="lbl">Cuti</div>
        </div>
        <div class="stat-card">
            <div class="num text-purple">{{ $stats['permit'] }}</div>
            <div class="lbl">Izin</div>
        </div>
        <div class="stat-card">
            <div class="num text-indigo">{{ $stats['sick'] ?? 0 }}</div>
            <div class="lbl">Sakit</div>
        </div>
        <div class="stat-card">
            <div class="num text-orange">{{ $stats['total_overtime_hours'] }}</div>
            <div class="lbl">Lembur</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>NIP</th>
                <th>Nama</th>
                <th>Status</th>
                <th>Hadir</th>
                <th>Absen</th>
                <th>Cuti</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Off</th>
                <th>Holiday</th>
                <th>Terlambat</th>
                <th>Pulang Cepat</th>
                <th>Lembur Aktual</th>
                <th>Lembur Hitung</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $i => $emp)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $emp['employee_code'] }}</td>
                <td class="left">{{ $emp['employee_name'] }}</td>
                <td>{{ $emp['employment_status'] === 'contract' ? 'PKWT' : 'PKWTT' }}</td>
                <td class="text-green">{{ $emp['present_days'] }}</td>
                <td class="text-red">{{ $emp['absent_days'] }}</td>
                <td>{{ $emp['leave_days'] }}</td>
                <td>{{ $emp['permit_days'] }}</td>
                <td>{{ $emp['sick_days'] }}</td>
                <td>{{ $emp['off_days'] }}</td>
                <td>{{ $emp['holiday_days'] }}</td>
                <td>{{ $emp['late_days'] }}x <small>({{ $emp['total_late_minutes'] }}m)</small></td>
                <td>{{ $emp['total_early_leave_minutes'] }}m</td>
                <td>{{ $emp['overtime_hours'] }}j</td>
                <td>{{ $emp['calculated_overtime'] }}j</td>
            </tr>
            @empty
            <tr><td colspan="15">Tidak ada data</td></tr>
            @endforelse
            <tr class="row-total">
                <td colspan="4" class="left"><strong>TOTAL</strong></td>
                <td class="text-green">{{ $stats['present'] }}</td>
                <td class="text-red">{{ $stats['absent'] }}</td>
                <td>{{ $stats['leave'] }}</td>
                <td>{{ $stats['permit'] }}</td>
                <td>{{ $stats['sick'] ?? 0 }}</td>
                <td></td>
                <td></td>
                <td>{{ $stats['late_days'] }}</td>
                <td></td>
                <td>{{ $stats['total_actual_overtime'] }}j</td>
                <td>{{ $stats['total_calculated_overtime'] }}j</td>
            </tr>
        </tbody>
    </table>

    <script>window.print();</script>
</body>
</html>
