<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Lembur Staf</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; padding: 20px 25px; }
        .header { margin-bottom: 10px; }
        .header-left { margin-bottom: 4px; }
        .header-left .company-name { font-size: 11px; font-weight: bold; }
        .header-left .company-address { font-size: 8px; color: #333; line-height: 1.3; }
        .header-title { text-align: center; margin-bottom: 8px; }
        .header-title h1 { font-size: 12px; font-weight: bold; }
        .info-row { overflow: hidden; margin-bottom: 8px; }
        .info-left { float: left; font-size: 9px; }
        .info-right { float: right; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #000; padding: 3px 4px; font-size: 8px; }
        th { background: #fff; font-weight: bold; text-align: center; }
        td.num { text-align: right; padding-right: 6px; }
        td.name-col { text-align: left; }
        td.name-col .emp-name { font-size: 8px; }
        td.name-col .emp-code { font-size: 7px; color: #555; }
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
        <h1>LAPORAN LEMBUR STAF - {{ $tabLabel }}</h1>
    </div>

    <div class="info-row">
        <div class="info-left">
            Periode : {{ \Carbon\Carbon::parse($period['start'])->format('d F Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d F Y') }}
        </div>
        <div class="info-right">
            Total Karyawan : {{ $stats['total_employees'] }}
        </div>
        <div style="clear:both;"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="4%">No</th>
                <th width="20%">Karyawan</th>
                <th width="12%">Departemen</th>
                <th width="6%">Hadir</th>
                <th width="7%">Lembur</th>
                <th width="6%">Cuti</th>
                <th width="6%">Izin</th>
                <th width="6%">Sakit</th>
                <th width="6%">Absen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $i => $emp)
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td class="name-col">
                    <div class="emp-name">{{ $emp['employee_name'] }}</div>
                    <div class="emp-code">{{ $emp['employee_code'] }}</div>
                </td>
                <td>{{ $emp['department'] }}</td>
                <td class="num">{{ $emp['hadir'] }}</td>
                <td class="num">{{ $emp['lembur'] }} jam</td>
                <td class="num">{{ $emp['cuti'] }}</td>
                <td class="num">{{ $emp['izin'] }}</td>
                <td class="num">{{ $emp['sakit'] }}</td>
                <td class="num">{{ $emp['absen'] }}</td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
