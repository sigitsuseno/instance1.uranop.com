<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Detail Absensi - {{ $employee['name'] }}</title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            color: #000;
            line-height: 1;
            padding: 1cm;
        }

        .header {
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .company-address {
            font-size: 10px;
            color: #333;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0 15px 0;
            text-transform: uppercase;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 3px 0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            font-size: 11px;
        }

        .info-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .info-table .label {
            font-weight: normal;
            width: 100px;
        }

        .info-table .colon {
            width: 15px;
            text-align: center;
        }

        .info-table .value {
            font-weight: bold;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #000;
        }

        .attendance-table th,
        .attendance-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            text-align: center;
            font-size: 10px;
        }

        .attendance-table th {
            background-color: #fff;
            font-weight: bold;
        }

        .attendance-table td {
            vertical-align: middle;
        }

        .attendance-table td.text-left {
            text-align: left;
        }

        .stats-summary {
            margin-top: 15px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }

        .stats-title {
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .stats-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 11px;
        }

        .stats-item {
            display: flex;
            gap: 5px;
        }

        .stats-label {
            font-weight: bold;
        }

        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
        }

        .signature-box {
            text-align: center;
            width: 180px;
            font-size: 11px;
        }

        .signature-space {
            height: 50px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .status-hadir {
            color: #16a34a;
        }

        .status-absen {
            color: #dc2626;
        }

        .status-cuti {
            color: #2563eb;
        }

        .status-izin {
            color: #ca8a04;
        }

        .status-sakit {
            color: #9333ea;
        }

        @media print {
            .print-btn {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <button class="print-btn" onclick="window.print()">Print / Cetak</button>

    @php
        if (!function_exists('formatDecimal')) {
            function formatDecimal($num)
            {
                if (!$num || $num == 0) {
                    return '0';
                }
                return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
            }
        }

        if (!function_exists('getMultiplierDetails')) {
            function getMultiplierDetails($minutes, $isFixed = false, $isSat = false, $isHoliday = false)
            {
                if (!$minutes || $minutes == 0) {
                    return [];
                }
                $hours = $minutes / 60;
                $details = [];

                if ($isFixed && $isHoliday) {
                    $remaining = max(0, $hours - 1);
                    if ($hours > 1) {
                        $details[] = '(' . formatDecimal($hours) . ' - 1) = ' . formatDecimal($remaining) . ' jam';
                    }
                    if ($isSat) {
                        // Sabtu + Holiday: jam 1-5 x2, jam 6 x3, jam 7+ x1
                        for ($i = 1; $i <= ceil($remaining); $i++) {
                            $seg = min(1, max(0, $remaining - ($i - 1)));
                            if ($i <= 5) {
                                $details[] =
                                    'Jam ke-' . $i . ': ' . formatDecimal($seg) . ' x 2 = ' . formatDecimal($seg * 2);
                            } elseif ($i === 6) {
                                $details[] =
                                    'Jam ke-' . $i . ': ' . formatDecimal($seg) . ' x 3 = ' . formatDecimal($seg * 3);
                            } else {
                                $details[] =
                                    'Jam ke-' . $i . ': ' . formatDecimal($seg) . ' x 4 = ' . formatDecimal($seg * 4);
                            }
                        }
                    } else {
                        // Holiday non-Sabtu: (jam - 1) x 2
                        $details[] = formatDecimal($remaining) . ' x 2 = ' . formatDecimal($remaining * 2);
                    }
                } else {
                    // Default + Sabtu biasa: jam ke-1 x1.5, jam ke-2+ x2
                    $firstHour = min($hours, 1);
                    $details[] = formatDecimal($firstHour) . ' x 1.5 = ' . formatDecimal($firstHour * 1.5);

                    if ($hours > 1) {
                        $remainingHours = $hours - 1;
                        $details[] = formatDecimal($remainingHours) . ' x 2.0 = ' . formatDecimal($remainingHours * 2);
                    }
                }

                return $details;
            }
        }
    @endphp

    <div class="header">
        <div class="company-name">PT. KEMILAU UNGARAN SUKSES</div>
        <div class="company-address">Jl. Ngobo/Jl. PTPN IX No.1 Gudang Dolog BGR Karangjati, Bergas Kab. Semarang</div>
    </div>

    <div class="report-title">LAPORAN DETAIL ABSENSI KARYAWAN</div>

    <table class="info-table">
        <tr>
            <td class="label">Nama Karyawan</td>
            <td class="colon">:</td>
            <td class="value">{{ $employee['name'] }}</td>
        </tr>
        <tr>
            <td class="label">NIK</td>
            <td class="colon">:</td>
            <td class="value">{{ $employee['code'] }}</td>
        </tr>
        <tr>
            <td class="label">Departemen</td>
            <td class="colon">:</td>
            <td class="value">{{ $employee['department'] }}</td>
        </tr>
        <tr>
            <td class="label">Jabatan</td>
            <td class="colon">:</td>
            <td class="value">{{ $employee['position'] }}</td>
        </tr>
        <tr>
            <td class="label">Periode</td>
            <td class="colon">:</td>
            <td class="value">{{ \Carbon\Carbon::parse($period['start'])->translatedFormat('d F Y') }} -
                {{ \Carbon\Carbon::parse($period['end'])->translatedFormat('d F Y') }}</td>
        </tr>
    </table>

    <table class="attendance-table">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th style="width: 80px;">Tanggal</th>
                <th style="width: 40px;">Hari</th>
                <th style="width: 50px;">Masuk</th>
                <th style="width: 50px;">Pulang</th>
                <th style="width: 45px;">Lembur</th>
                <th style="width: 80px;">Pengali</th>
                <th style="width: 45px;">Total</th>
                <th style="width: 70px;">Status</th>
                <th>Shift</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dailyData as $index => $day)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($day['date'])->translatedFormat('D') }}</td>
                    <td>{{ $day['check_in'] ?? '-' }}</td>
                    <td>{{ $day['check_out'] ?? '-' }}</td>
                    <td style="font-weight: bold;">{{ $day['overtime_display'] }}</td>
                    <td style="font-size: 9px; text-align: left;">
                        @foreach (getMultiplierDetails($day['overtime_minutes'], $day['is_fixed'] ?? false, $day['is_sat'] ?? false, $day['is_holiday'] ?? false) as $detail)
                            <div style="white-space: nowrap;">{{ $detail }}</div>
                        @endforeach
                    </td>
                    <td style="font-weight: bold;">
                        {{ $day['overtime_converted_hours'] > 0 ? formatDecimal($day['overtime_converted_hours']) . ' j' : '-' }}
                    </td>
                    <td class="status-{{ $day['status'] }}">{{ $day['status_label'] }}</td>
                    <td>{{ $day['shift_start'] ? $day['shift_start'] . ' - ' . $day['shift_end'] : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">Tidak ada data absensi untuk periode
                        ini</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="stats-summary">
        <div class="stats-title">Ringkasan Kehadiran:</div>
        <div class="stats-row">
            <div class="stats-item">
                <span class="stats-label">Hadir</span>
                <span>:</span>
                <span class="status-hadir">{{ $summary['hadir'] }} Hari</span>
            </div>
            <div class="stats-item">
                <span class="stats-label">Lembur</span>
                <span>:</span>
                <span>{{ $summary['lembur_minutes'] }} Jam</span>
            </div>
            <div class="stats-item">
                <span class="stats-label">Lembur Hitung</span>
                <span>:</span>
                <span>{{ $summary['lembur'] }} Jam</span>
            </div>
            <div class="stats-item">
                <span class="stats-label">Cuti</span>
                <span>:</span>
                <span class="status-cuti">{{ $summary['cuti'] }} Hari</span>
            </div>
            <div class="stats-item">
                <span class="stats-label">Izin</span>
                <span>:</span>
                <span class="status-izin">{{ $summary['izin'] }} Hari</span>
            </div>
            <div class="stats-item">
                <span class="stats-label">Sakit</span>
                <span>:</span>
                <span class="status-sakit">{{ $summary['sakit'] }} Hari</span>
            </div>
            <div class="stats-item">
                <span class="stats-label">Absen</span>
                <span>:</span>
                <span class="status-absen">{{ $summary['absen'] }} Hari</span>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="signature-box">
            <div>Semarang, {{ date('d/m/Y') }}</div>
            <div>Dicetak oleh,</div>
            <div class="signature-space"></div>
            <div>( {{ Auth::user()->name ?? '-' }} )</div>
        </div>
    </div>
</body>

</html>
