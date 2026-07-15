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

        /* ═══════ EMPLOYEE INFO ═══════ */
        .emp-section {
            margin: 9px 0 5px;
        }
        .emp-section .intro {
            font-size: 10pt;
            margin-bottom: 4px;
        }
        .emp-row {
            display: flex;
            font-size: 10pt;
            margin-bottom: 3px;
        }
        .emp-row .lbl {
            width: 130px;
            flex-shrink: 0;
        }
        .emp-row .val {
            border-bottom: 1px dotted #000;
            flex: 1;
            padding: 0 6px;
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
            border-bottom: 1px dotted #000;
            display: inline-block;
            min-width: 140px;
            margin-top: -1px;
            margin-bottom: 4px;
        }

        /* ═══════ DATE + SISA CUTI ═══════ */
        .date-section {
            margin: 10px 0 4px;
            font-size: 10pt;
        }
        .date-section .line {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 3px;
        }
        .date-section .val {
            border-bottom: 1px dotted #000;
            padding: 0 10px;
            font-weight: bold;
            min-width: 70px;
            text-align: center;
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
        <!-- Row 2: TGL EFEKTIF, date, NO DOKUMEN, doc# (PT cell di atas sudah merge 3 kolom via rowspan) -->
        <td>TGL&nbsp;EFEKTIF</td>
        <td>{{ $tglEfektif }}</td>
        <td>NO&nbsp;DOKUMEN</td>
        <td colspan="2" style="font-size:9.5pt;">{{ $noDokumen }}</td>
    </tr>
</table>

<!-- ═══════════════ EMPLOYEE INFO ═══════════════ -->
<div class="emp-section">
    <div class="intro">Dengan ini saya,</div>
    <div class="emp-row">
        <span class="lbl">Nama Lengkap</span>
        <span class="val">: {{ strtoupper($request->employee->name ?? '') }}</span>
    </div>
    <div class="emp-row">
        <span class="lbl">Bagian / Jabatan</span>
        <span class="val">: {{ strtoupper(trim(($request->employee->department->name ?? '') . ' / ' . ($request->employee->position->name ?? ''), ' /')) }}</span>
    </div>
    <div class="emp-row">
        <span class="lbl">Tanggal Masuk</span>
        <span class="val">: {{ $request->employee->join_date ? \Carbon\Carbon::parse($request->employee->join_date)->format('d/m/Y') : '_________________' }}</span>
    </div>
</div>

<!-- ═══════════════ LEAVE TYPES (3 columns) ═══════════════ -->
<div class="cuti-title">Mengajukan Permohonan Cuti :</div>
<div class="cuti-grid">
    @php
        $selName = strtoupper($request->leaveType->name ?? '');
        function chkSel($label, $selName) {
            $a = strtoupper(str_replace(['CUTI ','IZIN '], '', $label));
            $b = strtoupper(str_replace(['CUTI ','IZIN '], '', $selName));
            return $a === $b || str_contains($selName, $label) || str_contains($label, $b);
        }
    @endphp

    <!-- Col 1 -->
    <div class="cuti-col">
        @foreach (['TAHUNAN','MENIKAH','MENIKAHKAN ANAK','KHITAN/ BAPTIS','MELAHIRKAN/ KEGUGURAN'] as $l)
            <div class="cuti-item {{ chkSel($l, $selName) ? 'selected' : '' }}">
                <div class="box"></div><span>{{ $l }}</span>
            </div>
        @endforeach
    </div>

    <!-- Col 2 -->
    <div class="cuti-col">
        @foreach (['KEMATIAN KELUARGA (KANDUNG)','KEMATIAN ANGGOTA SERUMAH','SAKIT','HAID','CUTI IBADAH'] as $l)
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
    <div class="line">
        <span>Terhitung mulai tanggal</span>
        <span class="val">{{ \Carbon\Carbon::parse($request->start_date)->format('d/m/Y') }}</span>
        <span style="margin-left: 24px;">Sisa cuti tahunan</span>
        <span class="val">: {{ (int)$sisaCuti }}</span>
    </div>
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

<script>
    window.onload = function() { window.print(); }
</script>
</body>
</html>
