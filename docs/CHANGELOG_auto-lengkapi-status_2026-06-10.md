# Changelog — Auto-Lengkapi Status Fix

**Tanggal:** 2026-06-10

## Ringkasan

Perbaikan auto-lengkapi untuk handling status Holiday dan Minggu yang lebih tepat.

## Perubahan

### 1. Query diperluas — holiday absent → off
- **File:** `app/Modules/Attendance/Services/AttendanceService.php`
- Query auto-lengkapi sekarang juga mengambil record holiday dengan status `absent`
- Record ini diproses dan diubah ke status `off`

### 2. Status Minggu + scan → hadir
- Sebelumnya: Minggu + ada scan = `libur`
- Sekarang: Minggu + ada scan = `hadir` (karyawan benar-benar masuk)

### 3. Holiday logic — dua cabang
- Holiday + ada scan → `libur` (karyawan kerja di hari libur)
- Holiday + no scan → `off` (dari absent dikoreksi)

## Flow Final autoLengkapi

| Hari | Scan? | Status |
|---|---|---|
| Holiday | Ya | `libur` |
| Holiday | Tidak | `off` |
| Minggu | Ya | `hadir` |
| Minggu | Tidak | `off` |
| Weekday + leave | — | kode cuti |
| Weekday + fillAbsent | — | `hadir` |
| Weekday + no scan | — | `absent` |
