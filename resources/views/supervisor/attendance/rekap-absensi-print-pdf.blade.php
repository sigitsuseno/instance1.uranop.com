<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi</title>
    <style>
        @page { margin: 8mm 5mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 5.5px; color: #000; }
        .header { margin-bottom: 4px; }
        .header-left .company-name { font-size: 9px; font-weight: bold; }
        .header-left .company-address { font-size: 6px; color: #333; }
        .header-title { text-align: center; margin-bottom: 4px; }
        .header-title h1 { font-size: 10px; font-weight: bold; }
        .info-row { overflow: hidden; margin-bottom: 4px; font-size: 6px; }
        .info-left { float: left; }
        .info-right { float: right; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 0.5px solid #000; padding: 0.5px 1px; font-size: 5px; text-align: center; line-height: 1.1; }
        th { background: #f0f0f0; font-weight: bold; vertical-align: bottom; }
        th.rotated { writing-mode: vertical-rl; text-orientation: mixed; font-size: 4.5px; padding: 1px 0; min-width: 10px; }
        td.sticky-no { text-align: center; font-weight: bold; font-size: 5.5px; min-width: 14px; }
        td.sticky-name { text-align: left; white-space: nowrap; font-size: 5.5px; min-width: 65px; }
        td.status-cell { font-size: 4.5px; min-width: 8px; padding: 0.5px 0; }
        td.status-h { background: #d4edda; color: #155724; font-weight: bold; }
        td.status-s { background: #fff3cd; color: #856404; }
        td.status-i { background: #e8daef; color: #6c3483; }
        td.status-c { background: #d6eaf8; color: #1a5276; }
        td.status-l { background: #fdebd0; color: #935116; }
        td.status-o { background: #eaeded; color: #7f8c8d; }
        td.status-empty { color: #ccc; }
        td.lembur-cell { color: #c2410c; font-weight: bold; font-size: 4.5px; min-width: 8px; padding: 0.5px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'PT. KEMILAU UNGARAN SUKSES' }}</div>
            <div class="company-address">{{ $company?->address ?? '' }}</div>
        </div>
    </div>

    <div class="header-title">
        <h1>REKAP ABSENSI KARYAWAN</h1>
    </div>

    <div class="info-row">
        <div class="info-left">
            Periode : {{ \Carbon\Carbon::parse($period['start'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d M Y') }}
            &nbsp;|&nbsp; Departemen : {{ $departmentName }}
        </div>
        <div class="info-right">
            Total : {{ count($employees) }} karyawan
        </div>
        <div style="clear:both;"></div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="min-width:14px;">No</th>
                <th rowspan="2" style="min-width:65px;">Nama</th>
                @foreach($dates as $date)
                <th colspan="2" style="font-size:5px;">{{ $date->format('d') }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($dates as $date)
                <th class="rotated">{{ $date->translatedFormat('D') }}</th>
                <th class="rotated">L</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $emp)
            <tr>
                <td class="sticky-no">{{ $emp['no'] }}</td>
                <td class="sticky-name">{{ $emp['employee_name'] }}</td>
                @foreach($dates as $date)
                @php $d = $emp['days'][$date->toDateString()] ?? null; @endphp
                <td class="status-cell status-{{ strtolower($d['status'] ?? 'empty') }}">
                    {{ $d['status'] ?? '' }}
                </td>
                <td class="lembur-cell">
                    {{ ($d['lembur'] ?? 0) > 0 ? $d['lembur'] : '' }}
                </td>
                @endforeach
            </tr>
            @empty
            <tr><td colspan="100">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
