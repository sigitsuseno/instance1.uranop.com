<?php
/**
 * Progress List Rebuild Aplikasi HRIS (Uranop)
 * Output: DOCX dengan tabel progres berdasarkan git commit
 *
 * @author Sigit Suseno
 * @date   2026-07-29
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;

\PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Calibri');
$phpWord->setDefaultFontSize(10);

// ─── Styles ─────────────────────────────────────────────────────────────────
$phpWord->addTitleStyle(1, ['name' => 'Calibri', 'size' => 16, 'bold' => true, 'color' => '1F4E79'], ['spaceAfter' => 120]);

$phpWord->addParagraphStyle('pCenter', ['align' => 'center', 'spaceAfter' => 60]);
$phpWord->addParagraphStyle('pHeader', ['align' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0]);
$phpWord->addParagraphStyle('pCell', ['align' => 'left', 'spaceAfter' => 0, 'spaceBefore' => 0]);
$phpWord->addParagraphStyle('pCellCenter', ['align' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0]);

// Table style
$phpWord->addTableStyle('tblProgress', [
    'borderSize' => 6,
    'borderColor' => '999999',
    'cellMargin' => 50,
], [
    'align' => 'center',
]);

// ─── DATA TIMELINE ──────────────────────────────────────────────────────────
$timelineData = [
    // MEI
    ['1', 'Inisialisasi Proyek', '27 Mei', 'Laravel 13 + Vue 3 SPA HRIS, Sanctum auth, modular structure, 30+ halaman frontend', 'Fondasi'],
    ['2', 'Fase 2 — Organisasi', '28 Mei', 'Perusahaan, Cabang, Departemen (hierarki), Posisi (grade, reports-to)', 'Master Data'],
    ['3', 'Fase 2.1 — Konfigurasi Gaji', '28 Mei', 'Salary grade, komponen gaji, BPJS configs, PPh configs, PTKP rates, TER rates', 'Payroll'],
    ['4', 'Fase 2 FN — Finalisasi', '28 Mei', 'Finalisasi struktur organisasi & relasi antar tabel master', 'Perbaikan'],
    ['5', 'Fase 3 — Karyawan', '29 Mei', 'Employee CRUD (NIK, NPWP, BPJS, bank, status pegawai, salary cache)', 'Master Data'],
    ['6', 'Kontrak Karyawan', '30 Mei', 'Riwayat kontrak (start/end, is_latest), Kompensasi (premi, tunjangan)', 'Master Data'],
    ['7', 'Data Pendukung Karyawan', '31 Mei', 'Keluarga (tanggungan), dokumen (upload), gaji, komponen gaji per karyawan', 'Master Data'],

    // JUNI
    ['8', 'Fase 4 — Schedule', '1 Jun', 'Work patterns, detail pola per hari, work pattern types, shift definition', 'Master Data'],
    ['9', 'Cuti — Tipe & Kebijakan', '2 Jun', 'Leave types (cuti/izin/sakit), policies, periods, kuota cuti', 'Fitur Baru'],
    ['10', 'Schedule — Shift & Kalender', '3 Jun', 'Shift windows (overtime, toleransi, overnight), working calendars, holidays', 'Master Data'],
    ['11', 'Absensi — Raw Logs', '4 Jun', 'Import raw log fingerprint, deteksi check-in/out, batch import', 'Fitur Baru'],
    ['12', 'Schedule — Roster', '5 Jun', 'Employee shift roster per hari, employee overtime records', 'Fitur Baru'],
    ['13', 'Cuti — Permohonan & Approval', '6-7 Jun', 'Leave requests, approval workflow (approve/reject/cancel/bulk)', 'Fitur Baru'],
    ['14', 'BPJS — Restruktur', '8 Jun', 'BPJS per-periode (JHT/JKK/JKM/JP/Kesehatan), max wage cap, employer/employee', 'Upgrade'],
    ['15', 'Lembur — Aturan & Perhitungan', '9 Jun', 'Overtime rules (hari kerja vs libur), overtime rule details, upah lembur', 'Fitur Baru'],
    ['16', 'Lembur — Approval & Logs', '10 Jun', 'Approval lembur, perhitungan massal, import lembur, employee overtime logs', 'Fitur Baru'],
    ['17', 'Approval System', '11 Jun', 'Sistem approval multi-modul (lembur, cuti), approve/reject workflow', 'Fitur Baru'],
    ['18', 'PPh 21 — Tarif Progressive', '12 Jun', 'Progressive tax rates, perhitungan PPh per karyawan per periode', 'Fitur Baru'],
    ['19', 'PPh 21 — Finalisasi', '13-15 Jun', 'PPh methods (gross/gross_up/net), non-NPWP penalty, employee PPH records', 'Melengkapi'],
    ['20', 'Dashboard Admin', '15 Jun', 'Dashboard overview dengan statistik data HR', 'Melengkapi'],
    ['21', 'Supervisor — Absensi & Payroll', '17 Jun', 'Supervisor portal: attendance management, payroll, rekap per cabang', 'Fitur Baru'],
    ['22', 'Push Notification', '18 Jun', 'Web push (VAPID), notifikasi approval & pengumuman real-time', 'Fitur Baru'],
    ['23', 'Docker & Supervisor', '19 Jun', 'Dockerfile, Supervisor queue worker, rpcinterface', 'Upgrade'],
    ['24', 'Supervisor — Karyawan & Schedule', '20-22 Jun', 'Supervisor: karyawan, schedule, shift management per cabang', 'Fitur Baru'],
    ['25', 'Mobile Auth & QR Code', '23 Jun', 'Mobile auth, QR code login, CORS, Sanctum stateful domain Tauri/Capacitor', 'Fitur Baru'],
    ['26', 'Payroll — Konfigurasi', '25 Jun', 'Payroll configs, salary component master, BPJS payroll config', 'Melengkapi'],
    ['27', 'Kasbon — Permohonan & Angsuran', '26 Jun', 'Salary advance: requests, tenor, approval workflow, installment tracking', 'Fitur Baru'],
    ['28', 'Kasbon — Approval & Histori', '27 Jun', 'Kasbon approval, riwayat pinjaman (pending→approved→disbursed→completed)', 'Fitur Baru'],
    ['29', 'THR — Perhitungan', '29 Jun', 'THR configuration, perhitungan per karyawan, service year allowance', 'Fitur Baru'],
    ['30', 'Kasbon — Finalisasi', '30 Jun', 'Angsuran massal, push to payroll, integrasi potongan gaji', 'Melengkapi'],

    // JULI
    ['31', 'Supervisor — Payroll', '1 Jul', 'Supervisor payroll: gaji karyawan, slip gaji, breakdown per cabang', 'Fitur Baru'],
    ['32', 'Docker — Perbaikan', '3 Jul', 'Composer fix, build optimization untuk production', 'Perbaikan'],
    ['33', 'Employee — Grouping', '4-5 Jul', 'Employee groups (GRP-JKT, GRP-ALLIN), group masters, group settings', 'Fitur Baru'],
    ['34', 'Extra Employees', '6 Jul', 'Karyawan titipan (outsource), employee reserves, manual detect absensi', 'Fitur Baru'],
    ['35', 'Manual Detect & Perbaikan', '7-9 Jul', 'Attendance manual detection, perbaikan payroll, supervisor fixes', 'Perbaikan'],
    ['36', 'Employee — Ordering', '10 Jul', 'Employee ordering (urutan), employee group assignments', 'Fitur Baru'],
    ['37', 'Supervisor — Breakdowns', '11 Jul', 'Supervisor breakdowns gaji, THR supervisor, employee groups', 'Fitur Baru'],
    ['38', 'Pay Slip & BPJS Iuran', '12-13 Jul', 'Payslip generation, BPJS iuran (contribution), pay period fixes', 'Melengkapi'],
    ['39', 'Employee — PPh & BPJS', '14 Jul', 'PPh 21 employee per periode, BPJS membership, revisi perhitungan', 'Melengkapi'],
    ['40', 'THR & Service Year', '15-16 Jul', 'THR employee records, service year allowance brackets', 'Melengkapi'],
    ['41', 'Employee — Terminasi', '17-18 Jul', 'Termination workflow, resign/PHK dengan approval', 'Fitur Baru'],
    ['42', 'Payroll — Gaji & Slip', '20 Jul', 'Pay record kalkulasi (gaji_kotor, bpjs, pph, kasbon, gaji_bersih), payslip PDF', 'Melengkapi'],
    ['43', 'Supervisor — Payroll & Groups', '21 Jul', 'Supervisor payroll final, employee groups final, breakdown completion', 'Melengkapi'],
    ['44', 'Sync Module — Integrasi', '22 Jul', 'Sync desktop, license keys, PR #1 merge (hrisweb → master)', 'Fitur Baru'],
    ['45', 'Sync & Lisensi Desktop', '23 Jul', 'Desktop licenses, sync timestamps, UUID, multi-instance sync, audit logs', 'Fitur Baru'],
    ['46', 'Karyawan Titipan', '24 Jul', 'Karyawan titipan CRUD, roster management, period-based tracking', 'Fitur Baru'],
    ['47', 'Karyawan Titipan — Laporan', '25 Jul', 'Karyawan titipan rosters, laporan, reports framework', 'Fitur Baru'],
    ['48', 'Reports — Lembur & Uang Makan', '26 Jul', 'Laporan lembur harian/bulanan/resume, uang makan detail/rekap', 'Fitur Baru'],
    ['49', 'Reports — Rekap Kerja', '27 Jul', 'Attendance report matrix, rekap kerja combined export, section sheet', 'Fitur Baru'],
    ['50', 'Reports — PPh Kompensasi', '28 Jul', 'Rekap PPh Kompensasi controller + export, Rekap Kerja + 4 export classes', 'Fitur Baru'],
    ['51', 'Reports — Lembur Finalisasi', '29 Jul', 'Lembur export refactoring, LemburUangMakanDetail/Resume export, Tab Vue', 'Fitur Baru'],
];

// ─── BUILD ──────────────────────────────────────────────────────────────────
$section = $phpWord->addSection([
    'orientation' => 'landscape',
    'pageSizeW' => Converter::cmToTwip(29.7),
    'pageSizeH' => Converter::cmToTwip(21),
]);

// Title
$section->addText('PROGRESS LIST REBUILD APLIKASI HRIS (URANOP)', ['name' => 'Calibri', 'size' => 16, 'bold' => true, 'color' => '1F4E79'], 'pCenter');
$section->addText('Periode: 27 Mei - 29 Juli 2026 (64 hari) | Total: 338 commit | Pengembang: Sigit Suseno', ['name' => 'Calibri', 'size' => 10, 'color' => '555555'], 'pCenter');
$section->addTextBreak(1);

// Table
$table = $section->addTable('tblProgress');

// ── Header Row ──
$headerW = [0.7, 5.5, 2, 13, 3];
$headerTxt = ['No', 'Fase', 'Tanggal', 'Deskripsi / Fokus Utama', 'Kategori'];
$header = $table->addRow(null, ['tblHeader' => 'tblHeader']);
foreach ($headerTxt as $i => $txt) {
    $header->addCell(Converter::cmToTwip($headerW[$i]), ['bgColor' => '1F4E79'])
        ->addText($txt, ['bold' => true, 'color' => 'FFFFFF', 'name' => 'Calibri', 'size' => 9], 'pHeader');
}

// ── Category coloring ──
$catColors = [
    'Fondasi' => 'D6E4F0',
    'Master Data' => 'E2EFDA',
    'Payroll' => 'FFF2CC',
    'Fitur Baru' => 'D9E2F3',
    'Perbaikan' => 'FCE4EC',
    'Upgrade' => 'E8D5F5',
    'Melengkapi' => 'D5F5E3',
];

// ── Data Rows ──
$prevMonth = '';
foreach ($timelineData as $td) {
    $row = $table->addRow();

    // Determine month group separator
    $month = '';
    $num = (int)$td[0];
    if ($num <= 7) $month = 'MEI';
    elseif ($num <= 30) $month = 'JUNI';
    else $month = 'JULI';

    // No column
    $row->addCell(Converter::cmToTwip(0.7))
        ->addText($td[0], ['name' => 'Calibri', 'size' => 9], 'pCellCenter');

    // Phase name - bold
    $row->addCell(Converter::cmToTwip(5.5))
        ->addText($td[1], ['name' => 'Calibri', 'size' => 9, 'bold' => true], 'pCell');

    // Date
    $row->addCell(Converter::cmToTwip(2))
        ->addText($td[2], ['name' => 'Calibri', 'size' => 9], 'pCellCenter');

    // Description
    $row->addCell(Converter::cmToTwip(13))
        ->addText($td[3], ['name' => 'Calibri', 'size' => 9], 'pCell');

    // Category with color
    $cat = $td[4];
    $color = $catColors[$cat] ?? 'FFFFFF';
    $row->addCell(Converter::cmToTwip(3), ['bgColor' => $color])
        ->addText($cat, ['name' => 'Calibri', 'size' => 9, 'bold' => true], 'pCellCenter');
}

// ── Footer ──
$section->addTextBreak(1);
$section->addText(
    'Total: 51 fase | 338 commit | 64 hari pengembangan | 17 modul backend | 109+ halaman frontend | 75+ tabel database',
    ['name' => 'Calibri', 'size' => 9, 'italic' => true, 'color' => '888888'],
    'pCenter'
);

// ─── SIMPAN ─────────────────────────────────────────────────────────────────
$outputPath = __DIR__ . '/../docs/Progress_List_Rebuild_HRIS_' . date('Ymd_His') . '.docx';
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($outputPath);

echo "✅ Progress List berhasil dibuat!\n";
echo "   File: $outputPath\n";
echo "   Ukuran: " . round(filesize($outputPath) / 1024, 1) . " KB\n";
