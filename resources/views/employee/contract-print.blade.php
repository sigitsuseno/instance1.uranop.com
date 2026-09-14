@php
    /*
     * Print template: PERJANJIAN KERJA (UNTUK WAKTU TERTENTU)
     * Mengikuti layout dokumen "docs/kontrak kerja.pdf" (Legal 8.5" x 14", 1 halaman).
     */

    $c = $company;
    $b = $branch ?? null;

    // ── Identitas PIHAK PERTAMA (penandatangan a/n perusahaan) ──
    // Dipakai sebagai fallback bila branches.nama_pimpinan belum diisi.
    $pihakPertama = [
        'nama'    => 'SATYAHADI MURDITOMO, ST',
        'jabatan' => 'Factory Manager',
    ];

    $companyName    = strtoupper($c->name ?? 'PT KEMILAU UNGARAN SUKSES');
    $companyTagline = 'EMBROIDERY & PRINTING FACTORY';
    // Kop surat: nama dari perusahaan, alamat & telepon dari cabang (fallback ke perusahaan).
    $kopAddress     = trim((string) ($b->address ?? '')) ?: trim((string) ($c->address ?? ''));
    $companyAddress = $kopAddress !== '' ? $kopAddress : 'Jl. Ngobo/PTPN IX No 1 Gudang Dolog BGR Karangjati 50552';
    $kopPhone       = trim((string) ($b->phone ?? '')) ?: trim((string) ($c->phone ?? ''));
    $companyPhone   = $kopPhone !== '' ? $kopPhone : '0298- 525052, 522686';
    $companyCity    = 'Ungaran - Semarang';
    $companyDomicile = 'Jl.PTPN IX / Jl.Ngobo No.1 Gudang Dolog BGR Karangjati Ungaran Semarang';

    // ── Logo & tanda tangan pimpinan ──
    $logoUrl = ($c && $c->logo_path) ? asset('storage/' . $c->logo_path) : null;
    $ttdUrl  = ($b && $b->ttd_pimpinan) ? asset('storage/' . $b->ttd_pimpinan) : null;

    // Nama di blok tanda tangan PIHAK PERTAMA diambil dari cabang.
    $pimpinanName = trim((string) ($b->nama_pimpinan ?? '')) ?: $pihakPertama['nama'];

    // ── Periode kontrak ──
    $start = $contract->start_date;
    $end   = $contract->end_date;

    $durasiBulan = $contract->duration_months;
    if (! $durasiBulan && $start && $end) {
        $durasiBulan = $start->diffInMonths($end);
    }

    $tanggalLahir = $employee->date_of_birth
        ? trim(($employee->place_of_birth ? $employee->place_of_birth . ' ' : '') . $employee->date_of_birth->locale('id')->translatedFormat('d F Y'))
        : ($employee->place_of_birth ?: '-');

    $bagian = $employee->position->name ?? $employee->department->name ?? '-';

    $gajiPokok = (float) ($employee->base_salary ?? 0);

    $tanggalSurat = $start ? $start->locale('id')->translatedFormat('d F Y') : '-';

    // ── Bulan Romawi untuk nomor dokumen (bulan saat dokumen dicetak) ──
    $bulanRomawiList = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    $bulanRomawi     = $bulanRomawiList[(int) date('n')];

    // ── Bank pembayaran gaji (dinamis dari employees.bank_name) ──
    // "UOB" -> "UOB Bank"; bila nama bank sudah memuat kata "Bank", tidak digandakan.
    $bankName  = trim((string) $employee->bank_name);
    $bankLabel = $bankName === ''
        ? '........................... Bank'
        : (str_contains(strtolower($bankName), 'bank') ? $bankName : $bankName . ' Bank');

    // ── Tabel jam kerja (statis, sesuai peraturan perusahaan) ──
    $jamKerjaSeninJumat = [
        ['shift' => 'I',   'jadwal' => '06.50 - 14.50', 'ist_normal' => '11.00 - 12.00', 'ist_jumat' => '11.30 - 13.00', 'lembur' => '16.00 - 16.30'],
        ['shift' => null,  'jadwal' => '08.00 - 16.00', 'ist_normal' => '12.00 - 13.00', 'ist_jumat' => '11.30 - 13.00', 'lembur' => '16.00 - 16.30'],
        ['shift' => null,  'jadwal' => '09.00 - 17.00', 'ist_normal' => '12.00 - 13.00', 'ist_jumat' => '11.30 - 13.00', 'lembur' => '17.00 - 17.30'],
        ['shift' => null,  'jadwal' => '10.00 - 18.00', 'ist_normal' => '12.00 - 13.00', 'ist_jumat' => '11.30 - 13.00', 'lembur' => '18.00 - 18.30'],
        ['shift' => 'II',  'jadwal' => '13.50 - 21.50', 'ist_normal' => '17.50 - 18.50', 'ist_jumat' => '', 'lembur' => '21.50 - 22.20'],
        ['shift' => null,  'jadwal' => '12.50 - 20.50', 'ist_normal' => '16.50 - 17.50', 'ist_jumat' => '', 'lembur' => '20.50 - 21.20'],
        ['shift' => null,  'jadwal' => '11.50 - 19.50', 'ist_normal' => '15.50 - 16.50', 'ist_jumat' => '', 'lembur' => '19.50 - 20.20'],
        ['shift' => null,  'jadwal' => '14.50 - 22.50', 'ist_normal' => '18.00 - 19.00', 'ist_jumat' => '', 'lembur' => 'Tidak lembur'],
        ['shift' => 'III', 'jadwal' => '19.00 - 03.00', 'ist_normal' => '00.00 - 01.00', 'ist_jumat' => '', 'lembur' => '03.00 - 03.30'],
        ['shift' => null,  'jadwal' => '22.50 - 06.50', 'ist_normal' => '03.00 - 04.00', 'ist_jumat' => '', 'lembur' => 'Tidak lembur'],
    ];

    $jamKerjaSabtu = [
        ['shift' => 'I',  'jadwal' => '06.50 - 12.20', 'ist_normal' => '10.30 - 11.00', 'lembur' => '12.20 - 12.50'],
        ['shift' => null, 'jadwal' => '08.00 - 14.00', 'ist_normal' => '12.00 - 13.00', 'lembur' => '14.00 - 14.30'],
        ['shift' => 'II', 'jadwal' => '09.20 - 14.50', 'ist_normal' => '12.20 - 12.50', 'lembur' => '14.20 - 14.50'],
        ['shift' => null, 'jadwal' => '10.20 - 15.50', 'ist_normal' => '13.20 - 13.50', 'lembur' => '15.20 - 15.50'],
        ['shift' => null, 'jadwal' => '11.20 - 16.50', 'ist_normal' => '14.20 - 14.50', 'lembur' => '16.20 - 16.50'],
        ['shift' => null, 'jadwal' => '12.20 - 17.50', 'ist_normal' => '15.00 - 15.30', 'lembur' => 'Tidak lembur'],
        ['shift' => null, 'jadwal' => '16.20 - 21.50', 'ist_normal' => '18.00 - 18.30', 'lembur' => '21.20-22.20'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Perjanjian Kerja - {{ $employee->name }}</title>
    <style>
        /* Kertas Legal (216mm x 356mm) — area cetak 196mm x 340mm. */
        @page { size: 216mm 356mm; margin: 8mm 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.8pt;
            line-height: 1.115;
            color: #000;
        }

        /* ═══════ HEADER PERUSAHAAN ═══════ */
        /* Logo di kiri, teks kop tetap center: kolom kiri & kanan disamakan lebarnya. */
        table.kop { width: 100%; border-collapse: collapse; }
        table.kop > tbody > tr > td { vertical-align: middle; padding: 0; }
        table.kop .kop-side { width: 72px; }
        .kop-logo { display: block; width: 62px; height: 62px; object-fit: contain; }
        .kop-divider { border-top: 1.6px solid #000; margin: 4px 0 0; }

        .company-header { text-align: center; line-height: 1.2; }
        .company-header .name { font-size: 12pt; font-weight: bold; letter-spacing: .4px; }
        .company-header .tagline { font-size: 9.5pt; font-weight: bold; letter-spacing: 1px; }
        .company-header .address { font-size: 9pt; }
        .company-header .phone { font-size: 9pt; }
        .company-header .city { font-size: 9pt; }

        /* ═══════ JUDUL ═══════ */
        .doc-title { text-align: center; margin-top: 6px; }
        .doc-title .t1 { font-size: 11.5pt; font-weight: bold; text-decoration: underline; }
        .doc-title .t2 { font-size: 9.5pt; font-weight: bold; }
        .doc-title .t3 { font-size: 9pt; margin-top: 2px; }
        /* Nomor kontrak dikosongkan (diisi tangan) — sisakan ruang ±40px. */
        .no-blank { display: inline-block; width: 40px; }

        /* ═══════ BLOK IDENTITAS ═══════ */
        .party { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .party td { padding: 0 0 0 0; vertical-align: top; font-size: 8.8pt; }
        .party .no { width: 16px; }
        .party .lbl { width: 110px; }
        .party .sep { width: 8px; }
        .party .indent { padding-left: 24px; }
        .closing { text-align: justify; }

        /* ═══════ PASAL ═══════ */
        .pasal-title { text-align: center; font-weight: bold; font-size: 9.5pt; margin: 4px 0 1px; }
        .clause { text-align: justify; margin-bottom: 1px; }
        .clause ol, .clause ul { padding-left: 15px; }
        .clause li { margin-bottom: 0; text-align: justify; }
        .nested { padding-left: 22px; }
        .inline-value { font-weight: bold; }
        .dotted-line { display: inline-block; min-width: 150px; border-bottom: 1px dotted #000; }

        /* ═══════ TABEL JAM KERJA ═══════ */
        .jam-wrap { width: 100%; border-collapse: collapse; margin: 2px 0 3px; }
        .jam-wrap > tbody > tr > td { vertical-align: top; padding: 0; }
        .jam-wrap .gap { width: 12px; }
        table.jam { border-collapse: collapse; width: 100%; }
        table.jam th, table.jam td {
            border: .6px solid #000;
            padding: 0.5px 2px;
            font-size: 7.4pt;
            line-height: 1.1;
            text-align: center;
            vertical-align: middle;
        }
        table.jam th { font-weight: bold; background: #f2f2f2; }
        table.jam td.shift { width: 24px; font-weight: bold; }

        /* ═══════ TANDA TANGAN ═══════ */
        .sign-date { margin-top: 8px; }
        .sign-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .sign-table td { width: 50%; text-align: center; vertical-align: top; font-size: 8.8pt; }
        .sign-table .space { height: 58px; }
        .sign-table .name { font-weight: bold; text-decoration: underline; }
        .sign-table .ttd-img { display: block; height: 50px; max-width: 150px; margin: 4px auto 0; object-fit: contain; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<!-- ═══════════════ HEADER (logo kiri, kop tengah) ═══════════════ -->
<table class="kop">
    <tr>
        <td class="kop-side">
            @if ($logoUrl)
            <div style="position: relative; width: 72px; height: 62px; display: flex; align-items: center; justify-content: center;">
                <img src="{{ $logoUrl }}" style="position: absolute; top: 0; left: 30px; max-width: 100%; max-height: 100%;" alt="">
            </div>
            @endif
        </td>
        <td>
            <div class="company-header" >
                <div class="name">{{ $companyName }}</div>
                <div class="tagline">{{ $companyTagline }}</div>
                <div class="address" style="padding: 0 60px;">{{ $companyAddress }}</div>
                <div class="phone">Telp. : {{ $companyPhone }}</div>
                <div class="city">{{ $companyCity }}</div>
            </div>
        </td>
        
        <td class="kop-side"></td>
    </tr>
</table>
<div class="kop-divider"></div>

<!-- ═══════════════ JUDUL ═══════════════ -->
<div class="doc-title">
    <div class="t1">PERJANJIAN KERJA</div>
    <div class="t2">(UNTUK WAKTU TERTENTU)</div>
    <div class="t3">NO.<span class="no-blank"></span> /SMG/KUS/{{ $bulanRomawi }}/{{ date('Y') }}</div>
</div>

<!-- ═══════════════ PIHAK PERTAMA & KEDUA ═══════════════ -->
<div style="margin-top:6px;">Yang bertanda tangan di bawah ini :</div>

<table class="party">
    <tr>
        <td class="no">I</td>
        <td class="lbl">Nama</td>
        <td class="sep">:</td>
        <td>{{ $pihakPertama['nama'] }}</td>
    </tr>
    <tr>
        <td class="no"></td>
        <td class="lbl">Jabatan</td>
        <td class="sep">:</td>
        <td>{{ $pihakPertama['jabatan'] }}</td>
    </tr>
</table>

<div class="closing" style="margin-top:3px;">
    Dalam hal ini bertindak untuk dan atas nama {{ $companyName }}
</div>
<div class="closing">
    Berkedudukan di {{ $companyDomicile }}, untuk selanjutnya disebut <strong>PIHAK PERTAMA</strong>
</div>

<table class="party">
    <tr>
        <td class="no">II</td>
        <td class="lbl">Nama Lengkap</td>
        <td class="sep">:</td>
        <td>{{ strtoupper($employee->name) }}</td>
    </tr>
    <tr>
        <td class="no"></td>
        <td class="lbl">Tempat/Tgl Lahir</td>
        <td class="sep">:</td>
        <td>{{ $tanggalLahir }}</td>
    </tr>
    <tr>
        <td class="no"></td>
        <td class="lbl">NIK</td>
        <td class="sep">:</td>
        <td>{{ $employee->nik ?: '-' }}</td>
    </tr>
    <tr>
        <td class="no"></td>
        <td class="lbl">Alamat sekarang</td>
        <td class="sep">:</td>
        <td>{{ $employee->address ?: '-' }}</td>
    </tr>
</table>

<div class="closing" style="margin-top:3px;">
    Dalam perjanjian kerja ini sebagai karyawan, bertindak untuk dan atas nama sendiri, Untuk selanjutnya disebut <strong>PIHAK KEDUA</strong>.
    Pada hari ini tanggal <span class="inline-value">{{ $tanggalSurat }}</span> bertempat di Perusahaan {{ $companyName }}
    {{ $companyDomicile }}, PIHAK PERTAMA dan PIHAK KEDUA bersama-sama telah sepakat mengadakan Perjanjian Kerja Waktu Tertentu
    dengan syarat-syarat dan ketentuan sebagai berikut :
</div>

<!-- ═══════════════ PASAL 1 ═══════════════ -->
<div class="pasal-title">Pasal 1</div>
<div class="clause">
    PIHAK PERTAMA menerima PIHAK KEDUA untuk bekerja di {{ $companyName }} Untuk waktu selama
    <span class="inline-value">{{ $durasiBulan ?: '-' }} Bulan</span> terhitung tanggal
    <span class="inline-value">&nbsp;{{ $start ? $start->locale('id')->translatedFormat('d F Y') : '................................' }}&nbsp;</span>
    dan berakhir pada
    <span class="inline-value">&nbsp;{{ $end ? $end->locale('id')->translatedFormat('d F Y') : '................................' }}&nbsp;</span>
</div>
<div class="clause">
    <ol>
        <li>
            PIHAK PERTAMA akan memperkerjakan PIHAK KEDUA sebagai karyawan yang akan ditempatkan pada bagian
            <span class="inline-value">{{ strtoupper($bagian) }}</span>
        </li>
        <li>
            Bila dipandang perlu atas pertimbangan dan kepentingan PIHAK PERTAMA, PIHAK KEDUA bersedia ditempatkan dan dipindahkan pada
            pekerjaan atau jabatan lain dan atau ditempat lain yang ditetapkan oleh PIHAK PERTAMA.
        </li>
        <li>
            PIHAK KEDUA bersedia melaksanakan pekerjaan yang ditugaskan oleh PIHAK PERTAMA dengan sebaik-baiknya dan penuh tanggung jawab
            dengan berpedoman pada peraturan-peraturan yang berlaku di lingkungan Perusahaan.
        </li>
    </ol>
</div>

<!-- ═══════════════ PASAL 2 ═══════════════ -->
<div class="pasal-title">Pasal 2</div>
<div class="clause">
    <ol>
        <li>
            PIHAK PERTAMA menetapkan pemberian gaji Pokok kepada PIHAK KEDUA sebesar :
            <div class="nested">- Gaji Pokok&nbsp;&nbsp;&nbsp;&nbsp;: Rp. {{ number_format($gajiPokok, 0, ',', '.') }},-</div>
            <div class="nested" style="margin-top:1px;">
                Pembayaran dilakukan melalui {{ $bankLabel }}, setiap tanggal 1 awal bulan.
            </div>
        </li>
        <li>
            PIHAK PERTAMA memberikan fasilitas BPJS KESEHATAN, dengan Faskes yang ditunjuk sendiri oleh PIHAK KEDUA apabila
            PIHAK KEDUA BELUM memiliki fasilitas BPJS KESEHATAN, PIHAK PERTAMA memberikan fasilitas pengobatan kepada PIHAK KEDUA
            di klinik yang telah bekerjasama dengan Perusahaan.
            <div class="nested" style="margin-top:1px;">
                Adapun pengobatan yang ditanggung oleh PIHAK PERTAMA meliputi :&nbsp;&nbsp;<span class="inline-value">Pengobatan Umum</span>
            </div>
            <div style="margin-top:1px;">
                Biaya Pengobatan akan ditanggung sesuai dengan ketentuan perusahaan oleh PIHAK PERTAMA. Apabila PIHAK KEDUA berobat ke klinik
                yang tidak bekerjasama dengan Perusahaan maka biaya pengobatan tidak akan ditanggung oleh PIHAK PERTAMA.
            </div>
        </li>
    </ol>
</div>

<!-- ═══════════════ PASAL 3 ═══════════════ -->
<div class="pasal-title">PASAL 3</div>
<div class="clause">
    Waktu Kerja adalah 7 (Tujuh) jam kerja sehari (Senin s/d Jumat) dan 5 (Lima) jam kerja (Sabtu) sehari dalam seminggu dengan ketentuan sebagai berikut :
</div>

<table class="jam-wrap">
    <tr>
        <td colspan="5" style="text-align:center;font-weight:bold;font-size:9.5pt;padding-bottom:1px;">JAM KERJA</td>
        <td class="gap"></td>
        <td colspan="4"></td>
    </tr>
    <tr>
        <td style="border: 1px solid #000;text-align:center;font-weight:bold;font-size:9pt;">SENIN - JUMAT</td>
        <td class="gap"></td>
        <td style="border: 1px solid #000;text-align:center;font-weight:bold;font-size:9pt;">SABTU</td>
    </tr>
    <tr>
        <td>
            <table class="jam">
                <thead>
                    <tr>
                        <th rowspan="2">Jadwal</th>
                        <th rowspan="2">Jam Kerja</th>
                        <th colspan="2">Jam Istirahat</th>
                        <th rowspan="2">Istirahat Lembur</th>
                    </tr>
                    <tr>
                        <th>Normal</th>
                        <th>Jumat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jamKerjaSeninJumat as $row)
                        <tr>
                            <td class="shift">{{ $row['shift'] }}</td>
                            <td>{{ $row['jadwal'] }}</td>
                            <td>{{ $row['ist_normal'] }}</td>
                            <td>{!! $row['ist_jumat'] ?: '&nbsp;' !!}</td>
                            <td>{!! $row['lembur'] ?: '&nbsp;' !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
        <td class="gap"></td>
        <td>
            <table class="jam">
                <thead>
                    <tr>
                        <th rowspan="2">Jadwal</th>
                        <th rowspan="2">Jam Kerja</th>
                        <th rowspan="2">Jam Istirahat Normal</th>
                        <th rowspan="2">Istirahat Lembur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jamKerjaSabtu as $row)
                        <tr>
                            <td class="shift">{{ $row['shift'] }}</td>
                            <td>{{ $row['jadwal'] }}</td>
                            <td>{{ $row['ist_normal'] }}</td>
                            <td>{!! $row['lembur'] ?: '&nbsp;' !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="clause">
    Atau waktu kerja lain yang ditentukan kemudian sesuai dengan kebutuhan PIHAK PERTAMA dan berdasarkan penempatan PIHAK KEDUA oleh PIHAK PERTAMA.
</div>

<!-- ═══════════════ PASAL 4 ═══════════════ -->
<div class="pasal-title">PASAL 4</div>
<div class="clause">
    PIHAK KEDUA telah mempunyai masa kerja 12 bulan berturut-turut, maka PIHAK PERTAMA akan memberikan hak cuti tahunan sebanyak 12 hari kerja.
</div>

<!-- ═══════════════ PASAL 5 ═══════════════ -->
<div class="pasal-title">PASAL 5</div>
<div class="clause">
    Kompensasi bagi karyawan PKWT yang menyelesaikan masa kontrak. Uang kompensasi sebagaimana dimaksud diberikan kepada Pekerja/Buruh
    yang mempunyai masa kerja paling sedikit 1 (satu) bulan secara terus menerus. Dengan perhitungan : Masa Kerja (Bulan) / 12 X Upah Sebulan
</div>

<!-- ═══════════════ PASAL 6 ═══════════════ -->
<div class="pasal-title">PASAL 6</div>
<div class="clause">
    <ol>
        <li>
            Ikatan berdasarkan persetujuan kerja ini dapat diputuskan oleh karyawan atau Perusahaan dengan tenggang waktu pemberitahuan satu bulan
            sebelumnya dan keinginan untuk itu harus diberitahukan secara tertulis oleh pihak yang berkepentingan kepada pihak lainnya.
        </li>
        <li>Jika masa berlaku persetujuan kerja ini berakhir, karyawan tidak akan menuntut apapun dari Perusahaan.</li>
        <li>
            Persetujuan kerja ini dapat dihentikan tanpa tenggang waktu jika terdapat alasan sah yang berikut :
            <div class="nested">a. Kelakuan atau tindakan karyawan yang bertentangan dengan kewajiban yang berdasarkan persetujuan kerja.</div>
            <div class="nested">b. Penyakit yang diderita karyawan dan yang disembunyikan pada saat persetujuan kerja dimulai.</div>
        </li>
        <li>
            Pemutusan hubungan kerja terhadap Pekerja/Buruh karena alasan yang bersifat mendesak yang diatur dalam pasal 6 ayat 3 pada peraturan perusahaan,
            maka pekerja/buruh berhak atas :
            <div class="nested">a. Uang penggantian hak sesuai ketentuan pasal 40 ayat 1</div>
            <div class="nested">b. Uang pisah yang besarnya diatur dalam peraturan perusahaan</div>
            <div class="nested">c. Uang kompensasi untuk PKWT</div>
        </li>
        <li>
            Persetujuan kerja ini ditandatangani oleh kedua belah pihak sebagai bukti persetujuan tanpa paksaan dari pihak manapun
            dan dalam keadaan sehat jasmani dan rohani.
        </li>
    </ol>
</div>

<!-- ═══════════════ TANDA TANGAN ═══════════════ -->
<div class="sign-date">Ditandatangani di Ungaran, pada tanggal : <span class="inline-value">{{ $tanggalSurat }}</span></div>

<table class="sign-table">
    <tr>
        <td><strong>PIHAK PERTAMA</strong></td>
        <td><strong>PIHAK KEDUA</strong></td>
    </tr>
    <tr>
        <td>a/n Perusahaan</td>
        <td></td>
    </tr>
    <tr>
        <td class="space">
            @if ($ttdUrl)
            <div style="position: relative; width: 100%; height: 50px;">
                <img src="{{ $ttdUrl }}" alt="" style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); max-height: 80px; object-fit: contain;">
            </div>
            @endif
        </td>
        <td class="space"></td>
    </tr>
    <tr>
        <td class="name">{{ $pimpinanName }}</td>
        <td class="name">{{ strtoupper($employee->name) }}</td>
    </tr>
</table>

<script>
    window.onload = function () { window.print(); };
</script>
</body>
</html>
