# Changelog — Perbaikan Perhitungan Laporan Uang Makan

**Tanggal:** 2026-06-10
**File:** `app/Modules/Reports/Controllers/Api/V1/UangMakanReportController.php`

## Perubahan

### 1. `buildHarianData()` — baris 166
- **Sebelum:** `$isHoliday = ($statusRaw === 'libur');`
- **Sesudah:** `$isHoliday = $roster && $roster->is_holiday;`

### 2. `buildBulananData()` — baris 270
- **Sebelum:** `$isHoliday = ($statusRaw === 'libur');`
- **Sesudah:** `$isHoliday = $roster && $roster->is_holiday;`

## Alasan

Penentuan hari libur/holiday di laporan Uang Makan sebelumnya hanya mengandalkan
`statusRaw === 'libur'` dari attendance. Ini **salah** karena:
- Karyawan bisa berstatus "libur" di hari biasa (cuti, izin tanpa batas, dll)
  tapi itu BUKAN hari libur nasional / Minggu.
- Akibatnya: karyawan yang statusnya "libur" di weekday bisa dapat rate
  Minggu/Libur (FULL/HALF) — padahal tidak semestinya.

Perbaikan mengikuti pola yang sama dengan `LaporanLemburController`:
menggunakan `$roster->is_holiday` dari tabel `employee_shift_rosters`,
yang sudah menandai dengan benar apakah suatu tanggal adalah hari libur.

## Verifikasi

- Cek laporan Uang Makan Harian: karyawan dengan status "libur" di weekday
  tidak lagi mendapat rate Minggu/Libur.
- Cek laporan Uang Makan Bulanan: konsisten dengan harian.
