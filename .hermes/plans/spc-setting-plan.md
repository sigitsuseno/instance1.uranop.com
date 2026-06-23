# Plan: Setting D. KARYAWAN SPESIFIK (KRY-SPC)

**Tanggal:** 2026-06-23
**Status:** Draft — menunggu review

---

## Konteks
- Section D. KARYAWAN SPESIFIK = karyawan group KRY-SPC (sebelumnya GRP-SPC)
- Saat ini section selalu muncul → perlu conditional berdasarkan periode
- Perhitungan spesifik: hourlyRate pakai base_salary / 173 (bukan gaji dari DB)
- Base salary = satu nilai untuk semua karyawan KRY-SPC

---

## Phase 1 — Rename GRP-SPC → KRY-SPC di Code
**Files:**
- `app/Modules/Reports/Controllers/Api/V1/LaporanLemburController.php` line 901
- `resources/js/Pages/Admin/Reports/LemburUangMakan/Index.vue` line 97, 102 default groups

**Action:** Ganti semua reference `GRP-SPC` ke `KRY-SPC` di code + UI

---

## Phase 2 — Tambah `spc_start_period_id` di Config JSON
**Logic:** Section D hanya tampil jika `period_id >= spc_start_period_id`
- `null` → tampil di semua periode (backward compatible)
- Di-set ke periode X → hanya tampil dari periode X dst

**Files:**
- `app/Modules/Reports/Controllers/Api/V1/LaporanLemburController.php`
  - `buildCombinedDetailData()`: baca config, filter section D
  - `buildCombinedResumeData()`: sama

---

## Phase 3 — Tambah `spc_base_salary` di Config JSON
**Logic:** Jika `spc_base_salary` di-set → pakai itu; jika tidak → fallback ke `gaji / 173`

**Files:**
- `app/Modules/Reports/Controllers/Api/V1/LaporanLemburController.php`
  - Line ~903-904: ganti perhitungan hourlyRate SPC

---

## Phase 4 — Update UI Setting Modal

### 4a. Setting spesifik KRY-SPC (di bawah tabel uang makan)
Komponen baru atau extend `LemburUangMakanSettingsTable.vue`:
- **Input angka:** Base Salary (Rp) — satu nilai untuk semua KRY-SPC
- **Dropdown:** "Mulai Periode" — pilih dari daftar PayPeriod
- **Checkbox:** "Tampilkan D. KARYAWAN SPESIFIK" — enable/disable

### 4b. Daftar karyawan KRY-SPC (read-only)
- Fetch dari API `/api/v1/employee-data/by-group/KRY-SPC`
- Tampilkan tabel kecil: NIP, Nama, Jabatan

**Files:**
- `resources/js/Components/ReportPage/settings/LemburUangMakanSettingsTable.vue` — extend
- `resources/js/Pages/Admin/Reports/LemburUangMakan/Index.vue` — passing data

---

## Phase 5 — API Endpoint Karyawan by Group
**File:** `app/Modules/Settings/Controllers/Api/V1/EmployeeDataApiController.php`
- Tambah method untuk return karyawan by group reference_code
- Endpoint: `GET /api/v1/employee-data/by-group/{code}`

---

## Default Values
```json
{
  "KABAG": { ... },
  "KASHIFT": { ... },
  "ALL IN": { ... },
  "spc_start_period_id": null,
  "spc_base_salary": null
}
```

---

**Ditulis oleh:** Paijo
