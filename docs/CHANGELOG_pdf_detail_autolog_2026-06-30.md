# CHANGELOG — PDF Detail Autolog Absensi

**Tanggal:** 30 Juni 2026  
**Modul:** Supervisor > Attendance > Autolog  
**Tujuan:** Mengubah cetak laporan detail absensi dari HTML `window.print()` menjadi PDF proper via dompdf, dengan format sesuai sample `keluaran_pdf_detail.pdf`.

---

## File Berubah

### 1. `composer.json`
- Tambah dependency: `barryvdh/laravel-dompdf` (^3.1)

### 2. `resources/views/supervisor/attendance/autolog-detail-pdf.blade.php` **(NEW)**
- Blade view untuk generate PDF, format A4 portrait
- Struktur:
  - **Header:** PT. KEMILAU UNGARAN SUKSES + alamat, garis pemisah
  - **Judul:** LAPORAN DETAIL ABSENSI KARYAWAN (center, bold)
  - **Info Karyawan:** Nama, NIK, Departemen, Jabatan, Periode (format `Label : Value`)
  - **Tabel 10 kolom:** No, Tanggal (dd/mm/yyyy), Hari (singkat), Masuk, Pulang, Lembur, Pengali (multi-baris), Total, Status, Shift
  - **Ringkasan:** Hadir, Lembur, Lembur Hitung, Cuti, Izin, Sakit, Absen
  - **Footer:** Semarang, tanggal cetak — Dicetak oleh — (HR Branch)
- Font: Arial, 8-9px body, 12-13px header
- Border: `1px solid #000` all cells

### 3. `app/Modules/Supervisor/Attendance/Controllers/AttendanceAutologController.php`
- **`printDetail()`** — method di-refactor:
  - Sebelum: return `view('autolog-detail-print')` → HTML + `window.print()`
  - Sesudah: return `\Barryvdh\DomPDF\Facade\Pdf::loadView('autolog-detail-pdf')` → download `.pdf`
  - Set paper: A4 portrait
  - Filename: `Absensi_{Nama}_{Bulan_Tahun}.pdf`

### 4. `resources/js/Pages/Supervisor/Attendance/Autolog/Show.vue`
- **`handlePrint()`** — method di-refactor:
  - Sebelum: `fetch` HTML → `document.write()` → `window.open()`
  - Sesudah: `fetch` PDF blob → download via `<a>` click
  - Accept header: `application/pdf`
  - Filename parsing dari `Content-Disposition`

---

## Cara Test
1. Buka `/supervisor/attendance/autolog/{id}?start_date=...&end_date=...`
2. Klik tombol **Cetak Laporan**
3. Browser akan download file PDF

---

## Catatan
- **Main list page print** (`absensi/print`) masih menggunakan HTML `window.print()` — belum diubah
- Template HTML lama (`autolog-detail-print.blade.php`) tetap dipertahankan, tidak dihapus
- Format mengacu pada sample `sample_data/keluaran_pdf_detail.pdf`
