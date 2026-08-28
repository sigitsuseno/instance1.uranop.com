<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Detail Absensi - {{ $employee['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #000; padding: 15px 20px; }

        /* ── HEADER PERUSAHAAN ── */
        .company-header { text-align: center; margin-bottom: 4px; }
        .company-header .company-name { font-size: 13px; font-weight: bold; letter-spacing: 0.3px; }
        .company-header .company-addr { font-size: 9px; margin-top: 1px; }
        .divider { border: none; border-top: 1px solid #000; margin: 4px 0; }
        .divider-thin { border: none; border-top: 0.5px solid #000; margin: 0; }

        /* ── JUDUL LAPORAN ── */
        .report-title { text-align: center; font-size: 12px; font-weight: bold; margin: 6px 0 4px 0; letter-spacing: 0.3px; }

        /* ── INFO KARYAWAN ── */
        .employee-info { margin-bottom: 8px; }
        .employee-info table { border-collapse: collapse; }
        .employee-info td { padding: 1px 0; font-size: 9px; }
        .employee-info td.label { width: 95px; font-weight: normal; }
        .employee-info td.separator { width: 5px; text-align: center; }
        .employee-info td.value { font-weight: normal; }

        /* ── TABEL UTAMA ── */
        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .main-table th { border: 1px solid #000; padding: 3px 2px; font-size: 8px; font-weight: bold; text-align: center; background: #fff; }
        .main-table td { border: 1px solid #000; padding: 2px 3px; font-size: 8px; }
        .main-table td.center { text-align: center; }
        .main-table td.left { text-align: left; }
        .main-table td.right { text-align: right; }

        /* ── RINGKASAN ── */
        .summary-title { font-size: 9px; font-weight: bold; margin-bottom: 3px; }
        .summary-text { font-size: 9px; line-height: 1.5; }

        /* ── FOOTER ── */
        .footer { margin-top: 16px; text-align: right; font-size: 9px; }
        .footer .date { margin-bottom: 14px; }
        .footer .printed-by { margin-bottom: 14px; }
        .footer .role { }

        /* ── PRINT ── */
        @page { margin: 15mm 12mm 15mm 12mm; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>

    {{-- HEADER PERUSAHAAN --}}
    <div class="company-header">
        <div class="company-name">PT. KEMILAU UNGARAN SUKSES</div>
        <div class="company-addr">Jl. Ngobo/Jl. PTPN IX No.1 Gudang Dolog BGR Karangjati, Bergas Kab, Semarang</div>
    </div>
    <hr class="divider">
    <hr class="divider-thin">

    {{-- JUDUL LAPORAN --}}
    <div class="report-title">LAPORAN DETAIL ABSENSI KARYAWAN</div>
    <hr class="divider-thin">
    <hr class="divider">

    {{-- INFO KARYAWAN --}}
    <div class="employee-info">
        <table>
            <tr>
                <td class="label">Nama Karyawan</td>
                <td class="separator">:</td>
                <td class="value">{{ $employee['name'] }}</td>
            </tr>
            <tr>
                <td class="label">NIK</td>
                <td class="separator">:</td>
                <td class="value">{{ $employee['code'] }}</td>
            </tr>
            <tr>
                <td class="label">Departemen</td>
                <td class="separator">:</td>
                <td class="value">{{ $employee['department'] }}</td>
            </tr>
            <tr>
                <td class="label">Jabatan</td>
                <td class="separator">:</td>
                <td class="value">{{ $employee['position'] }}</td>
            </tr>
            <tr>
                <td class="label">Periode</td>
                <td class="separator">:</td>
                <td class="value">{{ \Carbon\Carbon::parse($period['start'])->format('d F Y') }} - {{ \Carbon\Carbon::parse($period['end'])->format('d F Y') }}</td>
            </tr>
        </table>
    </div>

    {{-- TABEL DETAIL ABSENSI --}}
    <table class="main-table">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:11%">Tanggal</th>
                <th style="width:6%">Hari</th>
                <th style="width:9%">Masuk</th>
                <th style="width:9%">Pulang</th>
                <th style="width:9%">Lembur</th>
                <th style="width:18%">Pengali</th>
                <th style="width:9%">Total</th>
                <th style="width:9%">Status</th>
                <th style="width:16%">Shift</th>
            </tr>
        </thead>
        <tbody>
            @php
                $no = 1;
                $totalLemburMentah = 0;     // menit
                $totalLemburCalc = 0;
                $totalHadir = 0;
                $totalCuti = 0;
                $totalIzin = 0;
                $totalSakit = 0;
                $totalAbsen = 0;

                // Collect daily data for all days in period
                $allDays = [];
                $currentDate = \Carbon\Carbon::parse($period['start']);
                $lastDate = \Carbon\Carbon::parse($period['end']);
                $logsByDate = [];
                foreach ($dailyData as $day) {
                    $logsByDate[$day['date']] = $day;
                }
                while ($currentDate <= $lastDate) {
                    $dateStr = $currentDate->toDateString();
                    $day = $logsByDate[$dateStr] ?? null;
                    // Konsep baru: lembur Mon-Sab, lm holiday
                    $isLmDay = $day['is_lm_day'] ?? (($day['work_pattern_type'] ?? null) === 'SHIFT' ? !empty($day['is_holiday']) : (!empty($day['is_holiday']) || !empty($day['is_sun'])));
                    $rawMin = $isLmDay ? (($day['lm'] ?? 0) ?: ($day['lembur'] ?? 0)) : (($day['lembur'] ?? 0) ?: ($day['lm'] ?? 0));
                    $allDays[] = [
                        'date' => $dateStr,
                        'day' => $currentDate->translatedFormat('D'),
                        'date_display' => $currentDate->format('d/m/Y'),
                        'check_in' => $day['check_in'] ?? null,
                        'check_out' => $day['check_out'] ?? null,
                        'lembur_min' => $rawMin,
                        'lembur_calc' => $day['lembur_total_calc'] ?? (($day['lembur_calc'] ?? 0)+($day['lm_calc'] ?? 0)),
                        'status' => $day['status'] ?? 'pending',
                        'shift_start' => $day['shift_start'] ?? null,
                        'shift_end' => $day['shift_end'] ?? null,
                        'is_fixed' => $day['is_fixed'] ?? false,
                        'is_sat' => $day['is_sat'] ?? false,
                        'is_holiday' => $day['is_holiday'] ?? false,
                        'is_sun' => $day['is_sun'] ?? false,
                        'work_pattern_type' => $day['work_pattern_type'] ?? null,
                        'is_lm_day' => $isLmDay,
                        'lm' => $day['lm'] ?? 0,
                        'lembur_raw' => $day['lembur'] ?? 0,
                    ];
                    $currentDate->addDay();
                }
            @endphp

            @foreach($allDays as $day)
                @php
                    $lemburMin = $day['lembur_min'];
                    $lemburH = $lemburMin / 60;
                    $lemburDisplay = $lemburMin > 0 ? round($lemburMin / 60, 1) . ' jam' : '-';
                    $totalCalcDisplay = $day['lembur_calc'] > 0 ? number_format($day['lembur_calc'], 1, ',', '.') . ' j' : '-';

                    // Multiplier detail per konsep baru
                    $pengaliLines = [];
                    if ($lemburMin > 0) {
                        $isLmDay = $day['is_lm_day'] ?? false;
                        $wpType = $day['work_pattern_type'] ?? null;
                        if ($isLmDay) {
                            if ($wpType === 'SHIFT') {
                                $sisa = max(0, $lemburH - 1);
                                if ($lemburH > 1) {
                                    $pengaliLines[] = '(' . number_format($lemburH, 2, ',', '.') . ' - 1) = ' . number_format($sisa, 2, ',', '.') . ' jam';
                                }
                                if (!empty($day['is_sat'])) {
                                    // Progressive Sabtu
                                    $remaining = $sisa;
                                    for ($i=1; $i<=ceil($remaining); $i++) {
                                        $seg = min(1, max(0, $remaining-($i-1)));
                                        $mult = $i<=5 ? 2 : ($i===6 ? 3 : 4);
                                        $pengaliLines[] = 'Jam ke-'.$i.': '.number_format($seg,2,',','.').' x '.$mult.' = '.number_format($seg*$mult,2,',','.');
                                    }
                                } else {
                                    $pengaliLines[] = number_format($sisa, 2, ',', '.') . ' x 2 = ' . number_format($sisa * 2, 2, ',', '.');
                                }
                            } else {
                                // non-SHIFT LM: potong 60 menit
                                $effectiveM = max(0, $lemburMin - 60);
                                $effectiveH = $effectiveM / 60;
                                if ($effectiveH <= 0) {
                                    $pengaliLines[] = number_format($lemburH,2,',','.').' -1j istirahat = 0 jam';
                                } else {
                                    if ($lemburMin > 60) $pengaliLines[] = '('.number_format($lemburH,2,',','.').' -1j) = '.number_format($effectiveH,2,',','.').' jam';
                                    $pengaliLines[] = number_format($effectiveH,2,',','.').' x 2 = '.number_format($effectiveH*2,2,',','.');
                                }
                            }
                        } else {
                            $first = min($lemburH, 1);
                            $pengaliLines[] = number_format($first, 2, ',', '.') . ' x 1.5 = ' . number_format($first * 1.5, 2, ',', '.');
                            if ($lemburH > 1) {
                                $rest = $lemburH - 1;
                                $pengaliLines[] = number_format($rest, 2, ',', '.') . ' x 2.0 = ' . number_format($rest * 2, 2, ',', '.');
                            }
                        }
                    }
                    $pengaliDisplay = !empty($pengaliLines) ? implode('<br>', $pengaliLines) : '-';

                    // Shift display
                    $shiftDisplay = ($day['shift_start'] && $day['shift_end'])
                        ? $day['shift_start'] . ' - ' . $day['shift_end']
                        : '-';

                    // Status label
                    $statusLabels = [
                        'present' => 'Hadir', 'absent' => 'Absen', 'leave' => 'Cuti',
                        'permit' => 'Izin', 'holiday' => 'Libur', 'off' => 'Off', 'pending' => '-'
                    ];
                    $statusDisplay = $statusLabels[$day['status']] ?? $day['status'];

                    // Accumulate
                    $totalLemburMentah += $lemburMin;
                    $totalLemburCalc += $day['lembur_calc'];
                    if ($day['status'] === 'present') $totalHadir++;
                    if ($day['status'] === 'leave') $totalCuti++;
                    if ($day['status'] === 'permit') $totalIzin++;
                    if ($day['status'] === 'sakit') $totalSakit++;
                    if ($day['status'] === 'absent') $totalAbsen++;
                @endphp
                <tr>
                    <td class="center">{{ $no++ }}</td>
                    <td class="left">{{ $day['date_display'] }}</td>
                    <td class="left">{{ $day['day'] }}</td>
                    <td class="center">{{ $day['check_in'] ?? '-' }}</td>
                    <td class="center">{{ $day['check_out'] ?? '-' }}</td>
                    <td class="left">{{ $lemburDisplay }}</td>
                    <td class="left">{!! $pengaliDisplay !!}</td>
                    <td class="left">{{ $totalCalcDisplay }}</td>
                    <td class="left">{{ $statusDisplay }}</td>
                    <td class="center">{{ $shiftDisplay }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- RINGKASAN KEHADIRAN --}}
    <div class="summary-title">Ringkasan Kehadiran:</div>
    <div class="summary-text">
        Hadir : {{ $totalHadir }} Hari &nbsp;&nbsp;
        Lembur : {{ round($totalLemburMentah / 60, 1) }} Jam &nbsp;&nbsp;
        Lembur Hitung : {{ number_format($totalLemburCalc, 1, ',', '.') }} Jam &nbsp;&nbsp;
        Cuti : {{ $totalCuti }} Hari &nbsp;&nbsp;
        Izin : {{ $totalIzin }} Hari &nbsp;&nbsp;
        Sakit : {{ $totalSakit }} Hari &nbsp;&nbsp;
        Absen : {{ $totalAbsen }} Hari
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div class="date">Semarang, {{ \Carbon\Carbon::now()->format('d/m/Y') }}</div>
        <div class="printed-by">Dicetak oleh,</div>
        <div class="role">( HR Branch )</div>
    </div>

</body>
</html>
