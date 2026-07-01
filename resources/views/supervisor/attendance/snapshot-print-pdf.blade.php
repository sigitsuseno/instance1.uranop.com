<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Attendance Snapshot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; padding: 20px 25px; }

        /* ===== HEADER ===== */
        .header { margin-bottom: 10px; }
        .header-left { float: left; width: 50%; }
        .header-left .company-name { font-size: 11px; font-weight: bold; margin-bottom: 2px; }
        .header-left .company-address { font-size: 8px; color: #333; line-height: 1.3; }
        .header-title { text-align: center; margin-bottom: 12px; }
        .header-title h1 { font-size: 12px; font-weight: bold; }

        /* ===== PERIOD & TOTAL ===== */
        .info-row { overflow: hidden; margin-bottom: 8px; }
        .info-left { float: left; font-size: 9px; }
        .info-right { float: right; font-size: 9px; }

        /* ===== TABLE ===== */
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th, td { border: 1px solid #000; padding: 3px 4px; font-size: 8px; }
        th { background: #fff; font-weight: bold; text-align: center; vertical-align: middle; }
        th.group-header { font-size: 9px; }
        th.sub-header { font-size: 8px; }

        td { vertical-align: top; }
        td.num { text-align: right; padding-right: 6px; }
        td.name-col { text-align: left; }
        td.name-col .emp-name { font-size: 8px; }
        td.name-col .emp-code { font-size: 7px; color: #555; }

        td.text-green { color: #16a34a; }
        td.text-red { color: #dc2626; }
    </style>
</head>
<body>

    <!-- COMPANY HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'PT. KEMILAU UNGARAN SUKSES' }}</div>
            <div class="company-address">{{ $company?->address ?? 'Jl. Ngobo/Jl. PTPN IX No.1 Gudang Dolog BGR Karangjati, Bergas Kab. Semarang' }}</div>
        </div>
        <div style="clear:both;"></div>
    </div>

    <!-- TITLE -->
    <div class="header-title">
        <h1>LAPORAN ATTENDANCE SNAPSHOT</h1>
    </div>

    <!-- PERIOD & TOTAL -->
    <div class="info-row">
        <div class="info-left">
            Periode : {{ \Carbon\Carbon::parse($period['start'])->format('d F Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d F Y') }}
        </div>
        <div class="info-right">
            Total Karyawan : {{ $stats['total_employees'] }}
        </div>
        <div style="clear:both;"></div>
    </div>

    <!-- TABLE -->
    <table>
        <thead>
            <tr>
                <th rowspan="2" width="4%">No</th>
                <th rowspan="2" width="18%">Karyawan</th>
                <th colspan="5" class="group-header">Kehadiran</th>
                <th rowspan="2" width="7%">Terlambat</th>
                <th colspan="2" class="group-header">Lembur</th>
            </tr>
            <tr>
                <th class="sub-header" width="6%">Hadir</th>
                <th class="sub-header" width="6%">Absent</th>
                <th class="sub-header" width="6%">Cuti</th>
                <th class="sub-header" width="6%">Izin</th>
                <th class="sub-header" width="6%">Sakit</th>
                <th class="sub-header" width="7%">Aktual</th>
                <th class="sub-header" width="7%">Hitung</th>
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
                <td class="num text-green">{{ $emp['present_days'] }}</td>
                <td class="num text-red">{{ $emp['absent_days'] }}</td>
                <td class="num">{{ $emp['leave_days'] }}</td>
                <td class="num">{{ $emp['permit_days'] }}</td>
                <td class="num">{{ $emp['sick_days'] }}</td>
                <td class="num">{{ $emp['late_days'] }}</td>
                <td class="num">{{ $emp['overtime_hours'] }}</td>
                <td class="num">{{ $emp['calculated_overtime'] }}</td>
            </tr>
            @empty
            <tr><td colspan="10" style="text-align:center;">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
