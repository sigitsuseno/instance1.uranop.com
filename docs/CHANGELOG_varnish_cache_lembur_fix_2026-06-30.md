# CHANGELOG: Debug Varnish Cache + Fix lembur_calc/lm_calc

**Tanggal:** 30 Juni 2026

---

## 🐛 Bug 1: Halaman megahris.teknisiungaran.my.id Blank

**Gejala:** Halaman hanya render `<div id="app"></div>` kosong, console kosong.

**Penyebab:** Hash mismatch antara HTML dan manifest Vite — `app-R2BR3rH_.js` (HTML) vs `app-BOuLEXKb.js` (manifest). File JS/CSS 404.

**Root cause:** Varnish cache (CloudPanel built-in) dengan TTL 7 hari (`604800s`) menyimpan HTML lama. Setelah `npm run build`, cache tidak otomatis ter-refresh.

### Solusi
```bash
# Purge Varnish cache via HTTP (dari mana saja yang bisa akses server)
curl -X PURGE -H "Host: megahris.teknisiungaran.my.id" http://10.10.10.19/

# Atau bypass Varnish untuk testing:
# Tambahkan ?noCache=1 di URL (excludedParams di Varnish settings)
```

### Cara alternatif (purge via SSH ke server)
```bash
# SSH ke server
ssh megahris@10.10.10.19    # pass: paijo21ok

# Purge via Varnish port langsung
curl -X PURGE -H "Host: megahris.teknisiungaran.my.id" http://127.0.0.1:6081/

# Clear Laravel cache (bonus)
cd /home/megahris/htdocs/megahris.teknisiungaran.my.id
php artisan optimize:clear
```

### Arsitektur cache server
```
Client → Nginx (80) → Varnish (6081) → PHP-FPM 8.4 (19005)
              ↑              ↑
         cache juga?    TTL 604800s (7 hari)
                        exclude: /admin/*
                        bypass param: noCache, __SID
```

### Varnish settings
Lokasi: `/home/megahris/.varnish-cache/settings.json`
```json
{
    "enabled": true,
    "server": "127.0.0.1:6081",
    "cacheLifetime": "604800",
    "excludes": ["^/admin/"],
    "excludedParams": ["__SID", "noCache"]
}
```

---

## 🔧 Fix 2: Unit Mismatch lembur_calc vs lm_calc

### Masalah
- `lembur_calc` disimpan dalam **JAM** (÷60 setelah multiplier)
- `lm_calc` disimpan dalam **MENIT** (tidak ÷60)
- Index page hanya sum `lembur_calc`, holiday overtime (`lm_calc`) tidak muncul

### Definisi bisnis (dari Mas Sigit)
| Kolom | Arti | Lingkup |
|-------|------|---------|
| `lembur` | Menit mentah | Workday + Sabtu |
| `lembur_calc` | Setelah multiplier (JAM) | Workday + Sabtu |
| `lm` | Menit mentah | Minggu + Holiday |
| `lm_calc` | Setelah multiplier (JAM) | Minggu + Holiday |
| **Total** | `lembur_calc + lm_calc` | Semua |

### File yang diubah

#### 1. `AttendanceAutologController.php`
- **`adjustment()`**: `lm_calc` sekarang ÷60 (simpan dalam JAM)
  ```php
  // BEFORE: $updateData['lm_calc'] = $calc['lm_count'];
  // AFTER:  $updateData['lm_calc'] = round($calc['lm_count'] / 60, 2);
  ```
- **`index()`**: sum `lembur_calc + lm_calc` (2 titik)
  ```php
  // BEFORE: 'lembur' => round($logs->sum('lembur_calc'), 1),
  // AFTER:  'lembur' => round($logs->sum('lembur_calc') + $logs->sum('lm_calc'), 1),
  ```
- **`show()`**: buang `÷60` (sudah sama unit), 2 titik
- **`print()`**: sum `lembur_calc + lm_calc`
- **`lembur_total_calc`**: buang `÷60` (line 374 & 528)

#### 2. `StaffOvertimeController.php`
- 6 titik: `sum('lembur_calc')` → `sum('lembur_calc') + sum('lm_calc')`

#### 3. `AttendanceSnapshotController.php`
- 5 titik: `calculatedOvertime` & `total_overtime_minutes` include `lm_calc`

#### 4. Data migration
```sql
-- 12 row existing dikonversi: menit → jam
UPDATE attendance_autologs SET lm_calc = lm_calc / 60 WHERE lm_calc > 0;
-- Before: 840.00 (menit) → After: 14.00 (jam)
```

### Verifikasi
```bash
# Semua sum('lembur_calc') sekarang diikuti + sum('lm_calc')
grep -rn "sum('lembur_calc')" app/Modules/Supervisor/ --include="*.php" | grep -v "lm_calc"
# → 0 results ✅
```
