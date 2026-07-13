<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Salary Breakdown</title>
    <style>
        @page {
            margin: 10mm 8mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.5px;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 6px;
        }
        .header .company {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .header .title {
            font-size: 10px;
            font-weight: bold;
        }
        .info-row {
            font-size: 8px;
            margin-bottom: 4px;
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
        }
        th {
            background-color: #B4C6E7;
            font-weight: bold;
            font-size: 6.5px;
            text-align: center;
            vertical-align: middle;
            padding: 3px 1px;
            border: 0.5px solid #000;
        }
        td {
            padding: 2px 2px;
            border: 0.5px solid #000;
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .text-left   { text-align: left; }
        .bg-zebra    { background-color: #F9FAFB; }
    </style>
</head>
<body>

<div class="header">
    <div class="company">{{ $companyName }}</div>
    <div class="title">LAPORAN SALARY BREAKDOWN — {{ $periodLabel }}</div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:18px;">No</th>
            <th style="width:28px;">ID No</th>
            <th style="width:105px;">Nama</th>
            <th style="width:85px;">Bagian / Jabatan</th>
            <th style="width:18px;">L/P</th>
            <th style="width:42px;">MASA KERJA</th>
            <th style="width:40px;">Join Date</th>
            <th style="width:28px;">Status</th>
            <th style="width:50px;">Gaji Pokok</th>
            <th style="width:48px;">Premi</th>
            <th style="width:35px;">Tj. MK</th>
            <th style="width:20px;">HK</th>
            <th style="width:22px;">L/M</th>
            <th style="width:30px;">Lbr Hitung</th>
            <th style="width:45px;">P. Lembur</th>
            <th style="width:48px;">GAJI</th>
            <th style="width:48px;">Tunjangan</th>
            <th style="width:42px;">Premi Hadir</th>
            <th style="width:40px;">BPJS TK</th>
            <th style="width:40px;">BPJS KS</th>
            <th style="width:40px;">BPJS Pens.</th>
            <th style="width:30px;">PPh</th>
            <th style="width:32px;">Kasbon</th>
            <th style="width:28px;">PBLT</th>
            <th style="width:48px;">THP</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $idx => $row)
            <tr class="{{ $idx % 2 === 1 ? 'bg-zebra' : '' }}">
                <td class="text-center">{{ $row['no'] }}</td>
                <td class="text-center">{{ $row['employee_code'] }}</td>
                <td class="text-left">{{ $row['name'] }}</td>
                <td class="text-left">{{ $row['bagian_jabatan'] }}</td>
                <td class="text-center">{{ $row['gender'] }}</td>
                <td class="text-center">{{ $row['masa_kerja'] }} bln</td>
                <td class="text-center">{{ $row['join_date'] }}</td>
                <td class="text-center">{{ $row['ptkp'] }}</td>
                <td class="text-right">{{ number_format($row['gaji_pokok'], 0, ',', '.') }}</td>
                <td class="text-right">{{ $row['premi'] > 0 ? number_format($row['premi'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['tj_masa_kerja'] > 0 ? number_format($row['tj_masa_kerja'], 0, ',', '.') : '-' }}</td>
                <td class="text-center">{{ $row['hari_kerja'] }}</td>
                <td class="text-center">{{ $row['lm'] > 0 ? number_format($row['lm'], 1, ',', '.') : '-' }}</td>
                <td class="text-center">{{ $row['lembur_count'] > 0 ? number_format($row['lembur_count'], 1, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['upah_lembur'] > 0 ? number_format($row['upah_lembur'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ number_format($row['gaji'], 0, ',', '.') }}</td>
                <td class="text-right">{{ $row['tunjangan'] > 0 ? number_format($row['tunjangan'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['premi_hadir'] > 0 ? number_format($row['premi_hadir'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['bpjs_tk'] > 0 ? number_format($row['bpjs_tk'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['bpjs_ks'] > 0 ? number_format($row['bpjs_ks'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['bpjs_pen'] > 0 ? number_format($row['bpjs_pen'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['pph'] > 0 ? number_format($row['pph'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['cashbon'] > 0 ? number_format($row['cashbon'], 0, ',', '.') : '-' }}</td>
                <td class="text-right">{{ $row['pblt'] > 0 ? number_format($row['pblt'], 0, ',', '.') : '-' }}</td>
                <td class="text-right"><strong>{{ number_format($row['gaji_bersih'], 0, ',', '.') }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
