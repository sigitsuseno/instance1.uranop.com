# Catatan: Laporan Lembur — Kolom yang Dipakai

**Rule:** Perhitungan uang lembur HARUS pakai kolom multiplier (`_count`), BUKAN kolom mentah.
Tampilan (L/M & Lembur) pakai kolom mentah (`lm`, `overtime`).

| Kolom | Arti | Dipakai buat |
|---|---|---|
| `att_prepares.lm` | Lembur Minggu mentah (menit) | ✅ Tampilan L/M |
| `att_prepares.lm_count` | Lembur Minggu setelah multiplier | ✅ Hitung uang |
| `att_prepares.overtime` | Lembur biasa mentah (menit) | ✅ Tampilan Lembur |
| `att_prepares.overtime_count` | Lembur biasa setelah multiplier | ✅ Hitung uang |

**Formula uang lembur:**
```
hourlyRate × (lm_count + overtime_count) / 60
```

**Hourly rate:**
```
(gaji_pokok + tj_masa_kerja + tunjangan) / 173
```

**Presisi:** Semua round() pakai 2 desimal (`round($val, 2)`), bukan integer.
Export Excel pakai format `#,##0.00` (ada desimal).

**Lokasi:** `LaporanLemburController.php` + `LemburHarianExport.php` + `LemburBulananExport.php`

**Tanggal fix:** 2026-06-09
