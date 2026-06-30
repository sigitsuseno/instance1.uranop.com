<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi - {{ $period['start'] }} s/d {{ $period['end'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; padding: 20px; }
        .header { text-align: center; margin-bottom: 12px; }
        .header h2 { font-size: 16px; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #666; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 10px; }
        .stats { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
        .stat-card { border: 1px solid #ddd; border-radius: 6px; padding: 6px 12px; text-align: center; min-width: 70px; }
        .stat-card .num { font-size: 16px; font-weight: bold; }
        .stat-card .lbl { font-size: 9px; color: #888; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; border: 1px solid #ddd; padding: 6px 4px; text-align: center; font-size: 10px; font-weight: 600; }
        td { border: 1px solid #ddd; padding: 4px; text-align: center; font-size: 10px; }
        td.left { text-align: left; }
        .text-green { color: #16a34a; font-weight: bold; }
        .text-red { color: #dc2626; font-weight: bold; }
        .text-orange { color: #ea580c; }
        .text-blue { color: #2563eb; }
        .text-purple { color: #7c3aed; }
        .text-teal { color: #0d9488; }
        @media print {
            body { padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Laporan Absensi Karyawan</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($period['start'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d M Y') }}</p>
    </div>

    <div class="meta">
        <span><strong>Departemen:</strong> {{ $departmentName }}</span>
        <span><strong>Total Karyawan:</strong> {{ $stats['total_employees'] }}</span>
    </div>

    <div class="stats">
        <div class="stat-card">
            <div class="num text-green">{{ $stats['present'] }}</div>
            <div class="lbl">Hadir</div>
        </div>
        <div class="stat-card">
            <div class="num text-orange">{{ $stats['total_overtime_hours'] }}</div>
            <div class="lbl">Lembur (jam)</div>
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
            <div class="num text-teal">{{ $stats['sakit'] }}</div>
            <div class="lbl">Sakit</div>
        </div>
        <div class="stat-card">
            <div class="num text-red">{{ $stats['absent'] }}</div>
            <div class="lbl">Absen</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>NIP</th>
                <th>Nama</th>
                <th>Departemen</th>
                <th>Jabatan</th>
                <th>Hadir</th>
                <th>Lembur</th>
                <th>Cuti</th>
                <th>Izin</th>
                <th>Sakit</th>
                <th>Absen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $i => $emp)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $emp['employee_code'] }}</td>
                <td class="left">{{ $emp['employee_name'] }}</td>
                <td class="left">{{ $emp['department'] }}</td>
                <td class="left">{{ $emp['position'] }}</td>
                <td class="text-green">{{ $emp['hadir'] }}</td>
                <td>{{ $emp['lembur'] }} jam</td>
                <td>{{ $emp['cuti'] }}</td>
                <td>{{ $emp['izin'] }}</td>
                <td>{{ $emp['sakit'] }}</td>
                <td class="text-red">{{ $emp['absen'] }}</td>
            </tr>
            @empty
            <tr><td colspan="11">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

    <script>window.print();</script>
</body>
</html>
