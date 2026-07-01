<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Lembur - {{ $employee['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; padding: 16px 20px; }
        .header { margin-bottom: 10px; }
        .header-left .company-name { font-size: 11px; font-weight: bold; }
        .header-left .company-address { font-size: 8px; color: #333; }
        .header-title { text-align: center; margin-bottom: 8px; }
        .header-title h1 { font-size: 12px; font-weight: bold; }
        .employee-info { margin-bottom: 8px; }
        .employee-info table { width: 100%; border-collapse: collapse; }
        .employee-info td { padding: 2px 6px; font-size: 8px; border: 1px solid #000; }
        .employee-info td.label { font-weight: bold; width: 120px; }
        .summary-box { margin-bottom: 8px; }
        .summary-box table { width: 100%; border-collapse: collapse; }
        .summary-box th, .summary-box td { border: 1px solid #000; padding: 3px 6px; font-size: 8px; text-align: center; }
        .summary-box th { background: #e8e8e8; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead { display: table-header-group; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 3px 4px; font-size: 7.5px; }
        .data-table th { background: #e8e8e8; text-align: center; font-weight: bold; }
        .data-table td { text-align: center; }
        .data-table td.left { text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'PT. KEMILAU UNGARAN SUKSES' }}</div>
            <div class="company-address">{{ $company?->address ?? 'Jl. Ngobo/Jl. PTPN IX No.1 Gudang Dolog BGR Karangjati, Bergas Kab. Semarang' }}</div>
        </div>
    </div>

    <div class="header-title">
        <h1>DETAIL LEMBUR STAF</h1>
    </div>

    <div class="employee-info">
        <table>
            <tr>
                <td class="label">Nama</td><td>{{ $employee['name'] }}</td>
                <td class="label">NIP</td><td>{{ $employee['code'] }}</td>
            </tr>
            <tr>
                <td class="label">Departemen</td><td>{{ $employee['department'] }}</td>
                <td class="label">Jabatan</td><td>{{ $employee['position'] }}</td>
            </tr>
            <tr>
                <td class="label">Periode</td><td colspan="3">{{ \Carbon\Carbon::parse($period['start'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d M Y') }}</td>
            </tr>
        </table>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <th>Hadir</th><th>Lembur (Jam)</th><th>Cuti</th><th>Izin</th><th>Sakit</th><th>Absen</th>
            </tr>
            <tr>
                <td>{{ $summary['hadir'] }}</td>
                <td>{{ $summary['lembur'] }}</td>
                <td>{{ $summary['cuti'] }}</td>
                <td>{{ $summary['izin'] }}</td>
                <td>{{ $summary['sakit'] }}</td>
                <td>{{ $summary['absen'] }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Status</th>
                <th>Lembur</th>
                <th>Lembur Hitung</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyData as $day)
            <tr>
                <td>{{ $day['date_display'] }}</td>
                <td>{{ $day['day'] }}</td>
                <td>{{ $day['check_in'] ?? '-' }}</td>
                <td>{{ $day['check_out'] ?? '-' }}</td>
                <td>{{ $day['status_label'] }}</td>
                <td>{{ $day['lembur_display'] }}</td>
                <td>{{ $day['lembur_calc'] > 0 ? $day['lembur_calc'] . ' jam' : '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="7">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
