# Changelog — Perbaikan Perhitungan Laporan Uang Makan

**Tanggal:** 2026-06-10
**File:** `app/Modules/Reports/Controllers/Api/V1/UangMakanReportController.php`

## Perubahan

### 1. Holiday check — `buildHarianData()` & `buildBulananData()`
- **Sebelum:** `$isHoliday = ($statusRaw === 'libur')`
- **Sesudah:** `$isHoliday = $roster && $roster->is_holiday`

### 2. `buildDayInfo()` — Rework logika Uang Makan
| Elemen | Sebelum | Sesudah |
|--------|---------|---------|
| **Upah/Hari** | Rate uang makan (minggu_full/sabtu_dua/dll) | `(gaji_pokok + tj_mk) / 25` — kosong utk I, A, OFF, Minggu/Holiday |
| **Weekday threshold** | >= 3 jam | >= 2 jam |
| **Weekday nominal** | `gajiPokok / 25` (dinamis) | **15.000 fixed** |
| **Minggu/Holiday Upah/Hari** | Diisi rate | **Kosong (0)** |
| **Sabtu/Minggu nominal** | Rate grup (tetap) | Rate grup (tetap) |

### 3. `buildHarianData()` — Hitung upahPerHari
- Tambah: `$upahPerHariValue = round(($gaji + $tjMk) / 25, 2)`
- Oper ke `buildDayInfo(...)` sbg parameter baru

### 4. `buildBulananData()` — Hitung upahPerHari
- Tambah: `$upahPerHariValue = round(($gaji + $tjMk) / 25, 2)`
- Oper ke `buildDayInfo(...)` sbg parameter baru

## Aturan Final

| | Weekday | Sabtu | Minggu/Holiday |
|---|---|---|---|
| **Upah/Hari** | `(gaji+tmk)/25` (kecuali I/A/OFF) | `(gaji+tmk)/25` (kecuali I/A/OFF) | **KOSONG** |
| **Syarat** | Lembur >= 2 jam | >=2→DUA, >=4→FULL | >=4→HALF, >=8→FULL |
| **Kolom Lembur** | `UM` | `DUA` / `FULL` | (LM: `HALF`/`FULL`) |
| **Nominal** | **15.000** | Rate grup | Rate grup |

Rate grup:
- KABAG: Sabtu 55rb/110rb, Minggu 110rb/220rb
- KASHIFT: Sabtu 52.500/105rb, Minggu 105rb/210rb
- ALL IN: Sabtu 50rb/100rb, Minggu 100rb/200rb
