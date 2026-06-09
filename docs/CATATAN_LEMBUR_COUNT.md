# Catatan: Laporan Lembur — Kolom yang Dipakai

**Rule:** Perhitungan uang lembur HARUS pakai kolom multiplier (`_count`), BUKAN kolom mentah.

| Kolom | Arti | Dipakai? |
|---|---|---|
| `att_prepares.lm` | Lembur Minggu mentah (menit) | ❌ Jangan |
| `att_prepares.lm_count` | Lembur Minggu setelah multiplier | ✅ PAKAI |
| `att_prepares.overtime` | Lembur biasa mentah (menit) | ❌ Jangan |
| `att_prepares.overtime_count` | Lembur biasa setelah multiplier | ✅ PAKAI |

**Formula uang lembur:**
```
hourlyRate × (lm_count + overtime_count) / 60
```

**Hourly rate:**
```
(gaji_pokok + tj_masa_kerja + tunjangan) / 173
```

**Lokasi fix:** `LaporanLemburController.php` line 124-125 → `lm_count` & `overtime_count`

**Tanggal fix:** 2026-06-09
