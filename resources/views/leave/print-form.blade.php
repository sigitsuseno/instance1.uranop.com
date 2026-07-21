<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Cuti - {{ $request->employee->name ?? '' }}</title>
    <style>
        @page { size: A4; margin: 9mm 11mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.35;
        }

        /* ═══════ OUTER BORDER — seluruh form ═══════ */
        .form-wrapper {
            border: 1.5px solid #000;
            padding: 10px;
        }

        /* ═══════ TOP HEADER TABLE ═══════ */
        .hdr-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 10px;
        }
        .hdr-table td {
            border: 1.2px solid #000;
            padding: 3px 5px;
            font-weight: bold;
            font-size: 9pt;
            text-transform: uppercase;
            text-align: center;
            vertical-align: middle;
        }
        /* Row 1 */
        .hdr-table .pt-cell {
            text-align: left;
            font-size: 10pt;
            padding-left: 12px;
            width: 38%;
            line-height: 1.4;
        }
        .hdr-table .pt-cell .logo-inline {
            display: inline-block;
            width: 24px; height: 24px;
            background: radial-gradient(circle at 35% 35%, #fcd34d, #f59e0b 55%, #b45309);
            border-radius: 50%;
            vertical-align: middle;
            margin-right: 6px;
        }
        .hdr-table .form-box {
            font-size: 10pt;
            line-height: 1.3;
        }
        .hdr-table .form-box .f1 { font-size: 10pt; }
        .hdr-table .form-box .f2 { font-size: 12pt; }

        /* Row 2 */
        .hdr-table .empty-cell { background: #fff; }

        /* ═══════ EMPLOYEE INFO — bordered table ═══════ */
        .emp-section {
            margin: 9px 0 5px;
        }
        .emp-section .intro {
            font-size: 10pt;
            margin-bottom: 4px;
        }
        .emp-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.2px solid #000;
        }
        .emp-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 10pt;
            vertical-align: middle;
        }
        .emp-table .lbl {
            width: 135px;
            font-weight: normal;
            background: #fafafa;
        }
        .emp-table .val {
            font-weight: bold;
        }

        /* ═══════ LEAVE TYPE CHECKBOXES (3 cols) ═══════ */
        .cuti-title {
            font-size: 10.5pt;
            margin: 12px 0 5px;
        }
        .cuti-grid {
            display: flex;
            gap: 10px;
            border: 1.2px solid #000;
            padding: 6px 8px;
        }
        .cuti-col { flex: 1; }
        .cuti-item {
            display: flex;
            align-items: flex-start;
            gap: 4px;
            margin-bottom: 3px;
            font-size: 8.5pt;
            text-transform: uppercase;
            line-height: 1.25;
        }
        .cuti-item .box {
            width: 10px; height: 10px;
            border: 1.5px solid #000;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .cuti-item.selected .box { background: #000; }
        .cuti-sub {
            font-size: 8pt;
            text-transform: none;
            margin-left: 14px;
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 140px;
            margin-top: -1px;
            margin-bottom: 4px;
        }

        /* ═══════ DATE + SISA CUTI — bordered ═══════ */
        .date-section {
            margin: 10px 0 4px;
            font-size: 10pt;
        }
        .date-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.2px solid #000;
        }
        .date-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 10pt;
            vertical-align: middle;
        }
        .date-table .val {
            font-weight: bold;
            text-align: center;
            min-width: 80px;
        }
        .disclaimer {
            font-size: 9pt;
            margin: 10px 0 14px;
        }

        /* ═══════ SIGNATURE TABLE (3 col, bordered) ═══════ */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 10px;
        }
        .sig-table td {
            border: 1.2px solid #000;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            padding: 5px 6px;
            width: 33.33%;
        }
        .sig-table .sig-space {
            height: 52px;
            font-weight: normal;
            text-transform: none;
            vertical-align: bottom;
            padding-bottom: 6px;
            font-size: 8.5pt;
        }

        /* ═══════ FOOTER (yellow box) ═══════ */
        .footer-note {
            margin-top: 6px;
            background: #fef08a;
            border: 1px solid #e5e07b;
            padding: 5px 10px;
            font-size: 9pt;
            font-style: italic;
        }
        .footer-note .b { font-weight: bold; font-style: italic; }
        .footer-note .red { color: #dc2626; font-weight: bold; font-style: italic; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="form-wrapper">

<!-- ═══════════════ HEADER TABLE ═══════════════ -->
<table class="hdr-table">
    <tr>
        <!-- Row 1: PT (left, rowspan 2) | FORMULIR box (right) -->
        <td colspan="3" rowspan="2" class="pt-cell" style="text-align:center;">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" style="display:block;width:40px;height:40px;border-radius:50%;margin:0 auto 4px;" alt="">
            @else
                <span class="logo-inline" style="display:block;margin:0 auto 3px;"></span>
            @endif
            <span style="font-size:10pt;">{{ $company ? strtoupper($company->name) : 'PT KEMILAU UNGARAN SUKSES' }}</span>
        </td>
        <td colspan="5" class="form-box">
            <div class="f1">FORMULIR</div>
            <div class="f2">PERMOHONAN CUTI</div>
        </td>
    </tr>
    <tr>
        <!-- Row 2: TGL EFEKTIF, date, NO DOKUMEN, doc# -->
        <td>TGL&nbsp;EFEKTIF</td>
        <td>{{ $tglEfektif }}</td>
        <td>NO&nbsp;DOKUMEN</td>
        <td colspan="2" style="font-size:9.5pt;">{{ $noDokumen }}</td>
    </tr>
</table>

<!-- ═══════════════ EMPLOYEE INFO ═══════════════ -->
<div class="emp-section">
    <div class="intro">Dengan ini saya,</div>

    @php
        // Ambil tanggal_masuk dari note JSON, fallback ke join_date employee
        $tanggalMasuk = null;
        if (!empty($request->note['tanggal_masuk'])) {
            $tanggalMasuk = $request->note['tanggal_masuk'];
        } elseif ($request->employee->join_date) {
            $tanggalMasuk = $request->employee->join_date;
        }
    @endphp

    <table class="emp-table">
        <tr>
            <td class="lbl">Nama Lengkap</td>
            <td class="val">: {{ strtoupper($request->employee->name ?? '') }}</td>
        </tr>
        <tr>
            <td class="lbl">Bagian / Jabatan</td>
            <td class="val">: {{ strtoupper(trim(($request->employee->department->name ?? '') . ' / ' . ($request->employee->position->name ?? ''), ' /')) }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Masuk</td>
            <td class="val">: {{ $tanggalMasuk ? \Carbon\Carbon::parse($tanggalMasuk)->format('d/m/Y') : '_________________' }}</td>
        </tr>
    </table>
</div>

<!-- ═══════════════ LEAVE TYPES (3 columns) ═══════════════ -->
<div class="cuti-title">Mengajukan Permohonan Cuti :</div>
<div class="cuti-grid">
    @php
        $selName = strtoupper($request->leaveType->name ?? '');
        function chkSel($label, $selName) {
            $aL = strtoupper(str_replace(['CUTI ','IZIN '], '', $label));
            $bL = strtoupper(str_replace(['CUTI ','IZIN '], '', $selName));
            if ($aL === $bL) return true;
            if (str_contains($selName, $label)) return true;
            if (str_contains($label, $bL)) return true;
            // Keyword-based: label gabungan (e.g. MENIKAH/MENIKAHKAN ANAK)
            if (str_contains($label, 'MENIKAH') && str_contains($selName, 'MENIKAH')) return true;
            // Keyword-based: KEMATIAN ≈ KELUARGA MENINGGAL
            if ((str_contains($label, 'KEMATIAN') || str_contains($label, 'MENINGGAL'))
                && (str_contains($selName, 'KEMATIAN') || str_contains($selName, 'MENINGGAL'))) return true;
            return false;
        }
    @endphp

    <!-- Col 1 -->
    <div class="cuti-col">
        @foreach (['TAHUNAN','MENIKAH/MENIKAHKAN ANAK','KHITAN/ BAPTIS','MELAHIRKAN/ KEGUGURAN'] as $l)
            <div class="cuti-item {{ chkSel($l, $selName) ? 'selected' : '' }}">
                <div class="box"></div><span>{{ $l }}</span>
            </div>
        @endforeach
    </div>

    <!-- Col 2 -->
    <div class="cuti-col">
        @foreach (['KEMATIAN (KELUARGA / ANGGOTA SERUMAH / SAUDARA)','SAKIT','HAID','CUTI IBADAH'] as $l)
            <div class="cuti-item {{ chkSel($l, $selName) ? 'selected' : '' }}">
                <div class="box"></div><span>{{ $l }}</span>
            </div>
        @endforeach
    </div>

    <!-- Col 3 (izin + jam fields) -->
    <div class="cuti-col">
        @php $izinItems = [
            ['l' => 'IZIN TIDAK MASUK', 'j' => false],
            ['l' => 'IZIN MASUK SIANG', 'j' => 'Jam masuk'],
            ['l' => 'IZIN 1/2 HK/Pulang', 'j' => 'Jam masuk/Pulang'],
            ['l' => 'IZIN TERLAMBAT', 'j' => 'Jam masuk'],
        ]; @endphp
        @foreach ($izinItems as $it)
            <div class="cuti-item {{ chkSel($it['l'], $selName) ? 'selected' : '' }}">
                <div class="box"></div><span>{{ $it['l'] }}</span>
            </div>
            @if ($it['j'])<div class="cuti-sub">{{ $it['j'] }} .......................</div>@endif
        @endforeach
    </div>
</div>

<!-- ═══════════════ DATE + SISA CUTI ═══════════════ -->
<div class="date-section">
    <table class="date-table">
        <tr>
            <td style="width:35%;">Terhitung mulai tanggal</td>
            <td class="val">{{ \Carbon\Carbon::parse($request->start_date)->format('d/m/Y') }}</td>
            <td style="width:35%;">Sisa cuti tahunan</td>
            <td class="val">: {{ (int)$sisaCuti }}</td>
        </tr>
    </table>
</div>

<div class="disclaimer">
    Hak ini diberikan hanya untuk karyawan tersebut diatas
</div>

<!-- ═══════════════ SIGNATURES ═══════════════ -->
<table class="sig-table">
    <tr>
        <td>PEMOHON</td>
        <td>SUPERVISOR / MANAGER</td>
        <td>HRD</td>
    </tr>
    <tr>
        <td class="sig-space">{{ strtoupper($request->employee->name ?? '') }}</td>
        <td class="sig-space"></td>
        <td class="sig-space"></td>
    </tr>
</table>

<!-- ═══════════════ FOOTER NOTE ═══════════════ -->
@php $deadline = \Carbon\Carbon::parse($request->start_date)->subDay()->format('d/m/Y'); @endphp
<div class="footer-note">
    <span class="b">Note :</span> Maksimal paling lambat diserahkan kembali ke <span class="b">HRD</span> sebelum tanggal: <span class="red">{{ $deadline }}</span>
</div>

</div><!-- /form-wrapper -->

<script>
    window.onload = function() { window.print(); }
</script>
</body>
</html>
