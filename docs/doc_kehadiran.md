# Dokumentasi Menu Kehadiran (Attendance)

> Auto-generated analysis oleh Paijo — 2026-06-11  
> Project: instance1.uranop.com (HRIS)

---

## Daftar Submenu

| # | Submenu | Route | Role | Halaman Vue |
|---|---------|-------|------|-------------|
| 1 | Import Kehadiran | `/admin/attendance/import` | superadmin, hrmanager | `Pages/Admin/Attendance/LogImport.vue` |
| 2 | Cek Log | `/admin/attendance/cek-log` | (linked dari Import) | `Pages/Admin/Attendance/CekLog.vue` |
| 3 | Sync Kehadiran | `/admin/attendance/sync` | superadmin, hrmanager | `Pages/Admin/Attendance/SyncKehadiran.vue` |
| 4 | Hitung Lembur | `/admin/attendance/overtime-calculation` | superadmin, hrmanager | `Pages/Admin/Attendance/OvertimeCalculation/Index.vue` |
| 5 | Detail Lembur | `/admin/attendance/overtime-calculation/:id` | superadmin, hrmanager | `Pages/Admin/Attendance/OvertimeCalculation/Detail.vue` |
| 6 | Resume Kehadiran | `/admin/attendance/recap` | semua | `Pages/Admin/Attendance/Recap/Index.vue` |
| 7 | Consecutive Day | `/admin/attendance/consecutive` | semua | `Pages/Admin/Attendance/Consecutive/Index.vue` |

> **Catatan**: Roster (`/admin/attendance/roster`) redirect ke `/admin/schedule/roster`.  
> **Supervisor** juga punya halaman sendiri di `/supervisor/attendance/*` tapi logika mirip, hanya scope berbeda.

---

## Tabel Database

| Tabel | Model | Fungsi |
|-------|-------|--------|
| `att_raw_logs` | `RawLog` | Log mentah dari mesin fingerprint / file import |
| `att_prepares` | `AttendancePrepare` | **Tabel inti** — data kehadiran hasil sinkronisasi & kalkulasi |
| `att_records` | `AttendanceRecord` | Resume/rekap kehadiran per periode payroll |
| `att_consecutive_days` | `ConsecutiveDay` | Input manual hari berturut-turut (hadir/absen) |

---

## 1. Import Kehadiran

**File**: `resources/js/Pages/Admin/Attendance/LogImport.vue`  
**Controller**: `AttendanceApiController`  
**Routes**:
- `GET /api/v1/attendance/logs/import/template` → download template
- `POST /api/v1/attendance/logs/import` → upload file
- `GET /api/v1/attendance/logs/import/status/{batch}` → polling status
- `POST /api/v1/attendance/logs/import-bin` → upload file `.bin` fingerprint

### Proses

1. **Upload file** (`.xlsx`, `.csv`, atau `.bin`) via drag-and-drop.
2. Pilih mode:
   - **Create** — tambah data baru
   - **Replace** — hapus data lama dari file yang sama, lalu import ulang
3. File disimpan di `storage/app/temp/imports/` dengan nama unik.
4. Untuk file Excel/CSV: dispatch `ProcessAttendanceLogImport` job → diproses di background.
5. Untuk file `.bin`: parse langsung via `FingerprintBinParser`, simpan ke `att_raw_logs` secara sinkron.
6. Frontend polling status via `GET .../import/status/{batch}`.

### Kolom RawLog (`att_raw_logs`)

| Field | Type | Keterangan |
|-------|------|------------|
| `employee_code` | string | NIP / kode karyawan |
| `scan_datetime` | datetime | Waktu scan fingerprint |
| `machine_sn` | string | Serial number mesin |
| `is_processed` | boolean | Sudah diproses ke prepare? |
| `import_batch` | string | Batch ID import |
| `source_file` | string | Nama file sumber |

---

## 2. Cek Log

**File**: `resources/js/Pages/Admin/Attendance/CekLog.vue`  
**Controller**: `RawLogController@cekLog`  
**Route**: `GET /api/v1/attendance/logs/cek?date=YYYY-MM-DD`

### Proses

1. User pilih tanggal.
2. Backend ambil **semua karyawan aktif** + **roster** mereka untuk tanggal tersebut.
3. Ambil **semua raw_logs** dalam range:
   - **SHIFT**: hari itu 00:00 s/d 23:59 (scan valid: 05:30–23:00)
   - **NON-SHIFT**: hari itu 06:30 s/d besok 06:29
4. Tampilkan per karyawan: NIP, nama, pola kerja, dan daftar scan (warna hijau = dalam rentang, abu-abu = luar rentang).

---

## 3. Sync Kehadiran

**File**: `resources/js/Pages/Admin/Attendance/SyncKehadiran.vue`  
**Controller**: `AttendanceApiController`  
**Service**: `AttendanceSyncService`  
**Routes**:
- `POST /api/v1/attendance/prepare/sync` — sync
- `GET /api/v1/attendance/prepare/list` — list data
- `GET /api/v1/attendance/prepare/stats` — statistik
- `POST /api/v1/attendance/prepare/lengkapi` — lengkapi manual
- `POST /api/v1/attendance/prepare/auto-lengkapi` — auto lengkapi
- `POST /api/v1/attendance/prepare/hitung-lembur` — hitung lembur
- `POST /api/v1/attendance/prepare/lock` — lock/unlock
- `POST /api/v1/attendance/prepare/update-status-legacy` — migrasi status
- `GET /api/v1/attendance/prepare/employee-groups` — mapping grup karyawan
- `GET /api/v1/attendance/prepare/overtime-summary` — ringkasan overtime
- `GET /api/v1/attendance/prepare/overtime-navigation` — navigasi prev/next

### Proses Sync (Flow Utama)

```
EmployeeShiftRoster (roster harian)
        ↓
  RawLog (log fingerprint)
        ↓
  AttendanceSyncService.syncPeriod()
        ↓
  AttendancePrepare (att_prepares)
```

#### Detail Sync (`AttendanceSyncService`)

1. **Ambil semua roster** dalam rentang tanggal.
2. **Preload holidays** & **approved leaves** untuk efisiensi.
3. Untuk setiap roster per tanggal:
   - **Cek leave dulu** → kalau ada approved leave, status = kode leave type (ct, skt, itm, dll), review_status = `lengkap`, check_in/out = null.
   - **Ambil raw_logs** karyawan (by NIP/employee_code) untuk tanggal tersebut.
   - **Kalau tidak ada log**:
     - SHIFT + external_code='L' → `off`
     - SHIFT tanpa 'L' → `absent` (review: `cek`)
     - NON-SHIFT + holiday → `libur`
     - NON-SHIFT + sunday → `off`
     - NON-SHIFT + external_code='L' → `off`
     - NON-SHIFT hari kerja → `absent` (review: `cek`)
   - **Kalau ada log**: deteksi check_in/check_out sesuai **work pattern type**:

#### Work Pattern Types & Detektor

| Pattern | Detector | Cara Deteksi |
|---------|----------|-------------|
| `FIXED` | `detectFixed()` | Scan pertama = check_in, terakhir = check_out |
| `SHIFT` | `detectShift()` → `detectShiftWorker()` | Window matching: cek range check_in_start..end & check_out_start..end |
| `FLEX-SHIFT` | `detectFlexShift()` | Holiday: window dari metadata shift. Non-holiday: `detectShiftWorker()` |
| `LONGSHIFT` | `detectLongshift()` | `detectShiftWorker()` |
| `SPLIT` | `detectSplit()` | `detectShiftWorker()` |
| `FLEXI` | `detectFlexi()` | `detectShiftWorker()` |
| `HOURLY` | `detectHourly()` | `detectShiftWorker()` |
| `ON_CAL` | `detectOnCall()` | `detectShiftWorker()` |
| `SEASONAL` | `detectSeasonal()` | `detectShiftWorker()` |

**Window Matching** (`detectShiftWorker`):
- Check-in: cari log dalam `check_in_start`..`check_in_end`, ambil yang terdekat ke `work_hour_start`.
- Check-out: cari log dalam `check_out_start`..`check_out_end` (atau `check_out_overnight_start`..`check_out_overnight_end` untuk overnight), ambil yang terdekat ke `work_hour_end`.

4. **Simpan ke `att_prepares`** via `updateOrCreate`:
   - Tidak overwrite record yang sudah di-lock.
   - Set `late_minutes`, `lm`, `lm_count`, `overtime`, `overtime_count` = 0 (kalkulasi dilakukan terpisah).
   - Periode menggunakan sistem **25-24** (tanggal ≥25 → bulan ini 25 s/d bulan depan 24).

#### Status Constants (`AttendancePrepare`)

| Constant | Value | Label |
|----------|-------|-------|
| `STATUS_HADIR` | `hadir` | Hadir |
| `STATUS_ABSENT` | `absent` | Absen |
| `STATUS_LIBUR` | `libur` | Libur |
| `STATUS_OFF` | `off` | Off |
| (kode leave type) | `ct`, `sk`, `it`, dll | Resolved dari tabel LeaveType |

#### Review Status

| Constant | Value | Label | Warna |
|----------|-------|-------|-------|
| `REVIEW_CEK` | `cek` | Cek | Kuning |
| `REVIEW_PERHATIAN` | `perhatian` | Perhatian | Orange |
| `REVIEW_LENGKAP` | `lengkap` | Lengkap | Hijau |
| `REVIEW_CSF` | `csf` | CSF (Consecutive Staff Flag) | Biru |

#### Proses Lengkapi

**Manual** (`prepareLengkapi`):
- Kirim array records dengan `id` (update) atau `employee_id`+`date` (create baru).
- Isi `check_in`, `check_out`, `status` yang kosong.
- Set `review_status` = `lengkap`.

**Auto** (`prepareAutoLengkapi`):
- Filter berdasarkan `group_codes` (contoh: `['GRP-JKT']`, `['GRP-ALLIN', 'GRP-GD', 'GRP-SS']`).
- Rules per record:
  - **Holiday**: lengkapi dari roster → status `libur` (ada scan) / `off` (tidak)
  - **Minggu**: lengkapi dari roster → status `hadir` (ada scan) / `off` (tidak)
  - **Weekday**: cek cuti → kode leave type. Kalau ada scan → `hadir`. Kalau tidak + `fill_absent` → isi dari roster. Kalau tidak → `absent`.
  - **Consecutive Day**: cek `att_consecutive_days` — kalau type=`worked` & no scan → `hadir` + `REVIEW_CSF`.

#### Proses Lock/Unlock

- Bulk via `prepareLock`: array `ids` + boolean `lock`.
- Record yang di-lock tidak bisa diedit/di-sync ulang.

#### Migrasi Status Legacy

- `prepareUpdateStatusLegacy`: record dengan status `cuti`/`izin`/`sakit` di-migrasi ke kode LeaveType spesifik (ct, it, sk, dll).
- Matching berdasarkan approved `LeaveRequest` yang overlap dengan tanggal.

---

## 4. Hitung Lembur

**File**: `resources/js/Pages/Admin/Attendance/OvertimeCalculation/Index.vue`  
**Controller**: `AttendanceApiController@prepareHitungLembur` & `prepareOvertimeSummary`  
**Service**: `AttendanceCalculatorService`, `AttendanceService@bulkHitungLembur`  
**Routes**:
- `POST /api/v1/attendance/prepare/hitung-lembur` — kalkulasi
- `GET /api/v1/attendance/prepare/overtime-summary` — ringkasan per karyawan
- `GET /api/v1/attendance/prepare/overtime-navigation` — navigasi karyawan

### Proses

1. **Bulk Hitung Lembur** (`AttendanceService@bulkHitungLembur`):
   - Ambil semua `att_prepares` unlocked + punya check_in & check_out.
   - **Skip** record dengan status cuti/izin/sakit (`isExcused()`) yang **bukan** hari libur (`!isOffDay()`).
   - Untuk setiap record, panggil `hitungLembur()`.

2. **Hitung Lembur Per Record** (`AttendanceService@hitungLembur`):
   - Ambil data dari `EmployeeShiftRoster`: shift, work_pattern_type, is_holiday, is_saturday, is_sunday.
   - Fallback: cek tabel Holiday dan Carbon.
   - Panggil `AttendanceCalculatorService@calculate()`.

### Kalkulasi (`AttendanceCalculatorService`)

#### Late Minutes
```
late = max(0, check_in - (schedule_in + tolerance))
```
- Tolerance dari `shift.tolerance_minutes` (default 30).
- Skip jika shift punya `shift_checkin_options`.

#### Raw Overtime

> **Semua nilai sudah di-rounding 30 menit** (`roundUp`) dan dikonversi ke **jam** (÷60) sebelum disimpan.
> Rounding: `floor((menit + 5) / 30) × 30` → 0-24→0, 25-54→30, 55-84→60, ...

| Work Pattern | Holiday | Minggu | Sabtu | Hari Kerja |
|-------------|---------|--------|-------|-----------|
| **SHIFT** | full c/i→c/o (max 8 jam) | **0** (hari kerja biasa) | flat **2 jam** | 0 |
| **FIXED** | full (max 8 jam) | full (max 8 jam) | — | c/o - sched_out |
| **FLEX-SHIFT P** | full (max 8 jam) | full (max 8 jam) | — | c/o - sched_out |
| **FLEX-SHIFT S** | full (max 8 jam) | full (max 8 jam) | — | total - 8j (min 0) |
| Lainnya | full (max 8 jam) | full (max 8 jam) | — | c/o - sched_out |

> **SHIFT = pola satpam/security.** Minggu tetap hari kerja normal, jadi overtime = 0.  
> Hanya Holiday (full) dan Sabtu (flat 2 jam) yang dihitung overtime.  
> **FIXED & FLEX-SHIFT = pola kantoran.** Minggu dan Holiday dihitung full (max 8 jam).

#### LM vs Regular Overtime

Semua nilai dalam **JAM** (sudah ÷60, sudah rounding 30m).

| Kondisi | lm | overtime | lm_count | overtime_count |
|---------|-----|----------|----------|---------------|
| **Off day** (holiday/minggu) | rawOT (jam) | 0 | multiplier × (jam - 1) | 0 |
| **Hari kerja** | 0 | rawOT (jam) | 0 | multiplier × jam |

#### Late Minutes
```
late = max(0, check_in - (schedule_in + tolerance))
```
- Tolerance dari `shift.tolerance_minutes` (default 30). **Late tetap dalam menit.**
- Skip jika shift punya `shift_checkin_options`.

#### Multiplier (dari `overtime_rules`)

Semua output dalam **JAM** (bukan menit × multiplier).

**LM**: (jam - 1) × multiplier (is_holiday=true). Fallback: `jam × 2`.
- Potong 1 jam istirahat.

**Overtime Reguler**: jam × multiplier (is_holiday=false). Fallback: 1 jam pertama ×1.5, sisanya ×2.

**Overtime Rules** (`overtime_rules` table):
- Bisa spesifik per `work_pattern_id` atau global.
- Detail per jam (hour=1→1.5, hour=2→2.0 → jam ke-1 ×1.5, jam ke-2 ×2.0, dst).
- Output: `Σ (jam_in_block × multiplier)`, dibulatkan 1 desimal.

---

## 5. Resume Kehadiran

**File**: `resources/js/Pages/Admin/Attendance/Recap/Index.vue`  
**Controller**: `AttendanceApiController@recapList`, `recapGenerate`, `recapApprove`  
**Routes**:
- `GET /api/v1/attendance/recap` — list (filter: period_id, search, department_id)
- `POST /api/v1/attendance/recap/generate` — generate
- `POST /api/v1/attendance/recap/approve` — approve & lock

### Proses

#### Generate (`recapGenerate`)

1. Pilih periode payroll (`pay_periods`).
2. Ambil karyawan yang:
   - **Aktif** di periode (`activeInPeriod`).
   - **Punya roster** di rentang tanggal periode.
3. Hitung **fixed working days** dari setting `attendance.fixed_days_per_month` (default 22).
4. **Split logic**: jika `pay_period.is_split = true`:
   - Segment A: start_date s/d akhir bulan pertama
   - Segment B: awal bulan kedua s/d end_date
   - HK per segment dari `attendance.split_working_days` (JSON: `{"A": 11, "B": 11}`).
   - Bersihkan old null-segment records.
5. Untuk setiap karyawan × segment:
   - **Aggregate leave**: dari `LeaveRequest` approved dalam rentang → `cuti`, `izin`, `sakit` (by leaveType category).
   - **Aggregate att_prepares**: jumlahkan `absen`, `late_minutes`, `lm`, `lm_count`, `overtime`, `overtime_count`.
   - **Hitung**: `deduct_day = izin + absen`, `hari_kerja = max(0, HK - deduct_day)`.
   - **Upsert** ke `att_records` (by employee_id + pay_period_id + segment).
6. Status record = `generated`.

#### List (`recapList`)

- Filter by `period_id`, `search`, `department_id`, `per_page`.
- Filter segment: split → hanya A/B, non-split → hanya null.
- Order by employee_code via subquery.

#### Approve (`recapApprove`)

1. Kirim array `ids` dari `att_records` yang akan di-approve.
2. Untuk setiap record:
   - **Cek `isGroupGaji()`**: skip karyawan yang bukan group penggajian (hapus pay_record jika ada).
   - **Hitung gaji**:
     - `gaji = (gaji_pokok / HK) × hari_kerja`
     - `upah_lembur = ceil(((gaji_pokok + tj_masa_kerja + tunjangan) / 173) × (lm_count + lembur_count) / 100) × 100`
     - `premi_hadir = (premi / HK) × hari_kerja`
     - `pot_kehadiran = deduct_day × (gaji_pokok / HK)`
   - **Split logic (Part 1 vs Part 2)**:
     - Segment A: `revisi = tj_masa_kerja × -1`, BPJS = 0
     - Segment B: BPJS normal
   - **Pembulatan 100**: `before_rounding = gaji_kotor - total_potongan`, lalu `rounded = ceil(before_rounding / 100) × 100`, `pblt = rounded - before_rounding`.
   - **Create/Update `pay_record`** dengan data lengkap.
   - **Lock `att_record`** (status = `locked`).

### Kolom AttendanceRecord (`att_records`)

| Field | Type | Keterangan |
|-------|------|------------|
| `employee_id` | FK | Karyawan |
| `pay_period_id` | FK | Periode payroll |
| `segment` | string/null | `A`/`B` (split) atau null |
| `hari_kerja` | int | Hari kerja efektif |
| `cuti` | decimal | Total hari cuti |
| `izin` | decimal | Total hari izin |
| `sakit` | decimal | Total hari sakit |
| `absen` | int | Total hari absen |
| `deduct_day` | decimal | Hari pemotongan (izin + absen) |
| `lm` | decimal(8,1) | Total LM dalam jam |
| `lm_count` | decimal(8,1) | LM × multiplier dalam jam |
| `lembur` | decimal(8,1) | Total overtime dalam jam |
| `lembur_count` | decimal(8,1) | Overtime × multiplier dalam jam |
| `status` | string | `generated` / `locked` |

---

## 6. Consecutive Day

**File**: `resources/js/Pages/Admin/Attendance/Consecutive/Index.vue`  
**Controller**: `AttendanceApiController@consecutiveList`, `consecutiveStore`, `consecutiveUpdate`, `consecutiveDestroy`  
**Service**: `ConsecutiveDayService`  
**Routes**:
- `GET /api/v1/attendance/consecutive` — list
- `POST /api/v1/attendance/consecutive` — create
- `PUT /api/v1/attendance/consecutive/{id}` — update
- `DELETE /api/v1/attendance/consecutive/{id}` — delete

### Proses

1. **Input manual**: HR input karyawan + range tanggal + tipe (`worked`/`absent`).
2. **Auto-hitung `total_days`** dari `start_date` s/d `end_date`.
3. **Sync ke `att_prepares`**:
   - `worked` → status `hadir`, review `csf`
   - `absent` → status `absent`, review `csf`
   - Untuk setiap tanggal dalam range: `updateOrCreate` att_prepares.
4. **Update**: revert range lama (review `csf` → `cek`), apply range baru.
5. **Delete**: revert att_prepares (review `csf` → `cek`), hapus record.

### Kolom ConsecutiveDay (`att_consecutive_days`)

| Field | Type | Keterangan |
|-------|------|------------|
| `employee_id` | FK | Karyawan |
| `start_date` | date | Tanggal mulai |
| `end_date` | date | Tanggal selesai |
| `total_days` | int | Jumlah hari |
| `type` | enum | `worked` / `absent` |
| `status` | enum | `calculated` / `reviewed` / `flagged` |
| `notes` | text | Catatan |

---

## Flow Utama End-to-End

```
┌─────────────────────────────────────────────────────────────┐
│                     IMPORT LOG                              │
│  .xlsx/.csv/.bin → ProcessAttendanceLogImport / BinParser  │
│                         ↓                                   │
│                   att_raw_logs                              │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                     SYNC KEHADIRAN                          │
│  AttendanceSyncService: roster + raw_logs → att_prepares   │
│  (check_in, check_out, status, review_status)              │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                       LENGKAPI                              │
│  Manual / Auto: isi check_in/out kosong, status, dll       │
│  Consecutive Day: set status hadir/absent untuk range      │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                     HITUNG LEMBUR                           │
│  AttendanceCalculatorService: late, lm, overtime,          │
│  multiplier dari overtime_rules                             │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│                    RESUME KEHADIRAN                          │
│  recapGenerate: att_prepares + leave → att_records         │
│  recapApprove: att_records + gaji → pay_records (lock)     │
└─────────────────────────────────────────────────────────────┘
```

---

## File Index

### Backend (PHP)

| Path | Fungsi |
|------|--------|
| `app/Modules/Attendance/Routes/api.php` | Semua routes kehadiran |
| `app/Modules/Attendance/Controllers/Api/V1/AttendanceApiController.php` | Controller utama (1105 lines) |
| `app/Modules/Attendance/Controllers/Api/V1/RawLogController.php` | Cek log controller |
| `app/Modules/Attendance/Services/AttendanceSyncService.php` | Sync service (927 lines) |
| `app/Modules/Attendance/Services/AttendanceService.php` | Lengkapi, hitung lembur, lock, migrasi (740 lines) |
| `app/Modules/Attendance/Services/AttendanceCalculatorService.php` | Kalkulasi late/overtime/LM (353 lines) |
| `app/Modules/Attendance/Services/FingerprintBinParser.php` | Parser file .bin fingerprint |
| `app/Modules/Attendance/Services/ConsecutiveDayService.php` | Service untuk consecutive day |
| `app/Modules/Attendance/Models/AttendancePrepare.php` | Model att_prepares |
| `app/Modules/Attendance/Models/RawLog.php` | Model att_raw_logs |
| `app/Modules/Attendance/Models/AttendanceRecord.php` | Model att_records |
| `app/Modules/Attendance/Models/ConsecutiveDay.php` | Model att_consecutive_days |
| `app/Modules/Attendance/Jobs/ProcessAttendanceLogImport.php` | Background job import |
| `app/Modules/Attendance/Imports/AttendanceLogImport.php` | Excel import logic |

### Frontend (Vue)

| Path | Fungsi |
|------|--------|
| `resources/js/Pages/Admin/Attendance/Index.vue` | Halaman utama Data Absensi (read-only view) |
| `resources/js/Pages/Admin/Attendance/LogImport.vue` | Import log (upload Excel/CSV/.bin) |
| `resources/js/Pages/Admin/Attendance/CekLog.vue` | Cek log per tanggal per karyawan |
| `resources/js/Pages/Admin/Attendance/SyncKehadiran.vue` | Sync + Lengkapi + Kalkulasi (1128 lines) |
| `resources/js/Pages/Admin/Attendance/OvertimeCalculation/Index.vue` | Ringkasan overtime per karyawan |
| `resources/js/Pages/Admin/Attendance/OvertimeCalculation/Detail.vue` | Detail overtime per karyawan |
| `resources/js/Pages/Admin/Attendance/Recap/Index.vue` | Resume kehadiran + generate + approve |
| `resources/js/Pages/Admin/Attendance/Consecutive/Index.vue` | CRUD consecutive day |
| `resources/js/Layouts/Admin/Sidebar.vue` | Sidebar navigation |
| `resources/js/router/index.js` | Vue Router |
