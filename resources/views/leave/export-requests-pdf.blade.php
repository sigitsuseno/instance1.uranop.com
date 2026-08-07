<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Pengajuan Cuti</title>
    <style>
        @page { margin: 10mm 8mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; }
        .header { margin-bottom: 6px; }
        .header-left .company-name { font-size: 13px; font-weight: bold; }
        .header-left .company-address { font-size: 8px; color: #333; }
        .header-title { text-align: center; margin-bottom: 6px; }
        .header-title h1 { font-size: 13px; font-weight: bold; text-transform: uppercase; }
        .header-title .period { font-size: 9px; font-weight: bold; margin-top: 2px; }
        .info-row { margin-bottom: 6px; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        th, td { border: 0.5px solid #000; padding: 3px 4px; font-size: 8px; line-height: 1.2; }
        th { background: #f0f0f0; font-weight: bold; text-align: center; }
        td { text-align: left; }
        td.no { text-align: center; }
        td.nip { text-align: center; font-family: 'Courier', monospace; }
        td.duration { text-align: center; }
        td.status { text-align: center; font-weight: bold; }
        td.reason { max-width: 160px; }
        .footer { margin-top: 8px; font-size: 8px; color: #333; text-align: right; }
    </style>
</head>
<body>
    @php
        $statusLabel = function ($status) {
            $map = [
                'pending' => 'Pending',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                'cancelled' => 'Dibatalkan',
            ];
            return $map[$status] ?? $status;
        };
    @endphp

    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company?->name ?? 'PT. KEMILAU UNGARAN SUKSES' }}</div>
            <div class="company-address">{{ $company?->address ?? '' }}</div>
        </div>
    </div>

    <div class="header-title">
        <h1>REKAP PENGAJUAN CUTI</h1>
        @if($periodName)
        <div class="period">Periode: {{ $periodName }}</div>
        @endif
    </div>

    <div class="info-row">
        Total: {{ count($requests) }} pengajuan
        &nbsp;|&nbsp; Dicetak: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:20px;">No</th>
                <th style="width:55px;">NIP</th>
                <th style="width:110px;">Nama Karyawan</th>
                <th style="width:90px;">Departemen</th>
                <th style="width:70px;">Tipe Cuti</th>
                <th style="width:65px;">Tgl Mulai</th>
                <th style="width:65px;">Tgl Selesai</th>
                <th style="width:40px;">Durasi (Hari)</th>
                <th style="width:60px;">Status</th>
                <th>Alasan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $index => $row)
            <tr>
                <td class="no">{{ $index + 1 }}</td>
                <td class="nip">{{ $row['employee']['nip'] ?? $row['nip'] ?? '-' }}</td>
                <td>{{ $row['employee']['name'] ?? $row['employee_name'] ?? '-' }}</td>
                <td>{{ $row['employee']['department']['name'] ?? $row['department'] ?? '-' }}</td>
                <td>{{ $row['leave_type']['name'] ?? $row['leave_type'] ?? '-' }}</td>
                <td class="no">{{ \Carbon\Carbon::parse($row['start_date'])->format('d/m/Y') }}</td>
                <td class="no">{{ \Carbon\Carbon::parse($row['end_date'])->format('d/m/Y') }}</td>
                <td class="duration">{{ $row['days_requested'] ?? 0 }}</td>
                <td class="status">{{ $statusLabel($row['status'] ?? '-') }}</td>
                <td class="reason">{{ $row['reason'] ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align:center; padding:12px;">Tidak ada data</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dihasilkan oleh sistem HRIS
    </div>
</body>
</html>
