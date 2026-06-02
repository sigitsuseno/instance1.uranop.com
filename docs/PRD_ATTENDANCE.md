# PRD — Modul Attendance (Kehadiran)

| Metadata | |
|----------|---------|
| **Author** | Sigit / Paijo |
| **Project** | instance1.uranop.com — HRIS Multi-Instance |
| **Fase** | Fase 7 (dari MIGRATION_GUIDE.md) |
| **Versi** | Draft v0.1 |
| **Status** | Draft |
| **Tanggal** | 2026-06-02 |
| **Dependencies** | Fase 4 (Employee) ✅, Fase 5 (Schedule) |

---

## 1. Executive Summary

Modul Attendance menangani seluruh proses absensi karyawan: mulai dari **import data log mesin fingerprint**, **proses check-in/check-out otomatis**, **koreksi manual**, **review & approval**, **perhitungan keterlambatan & lembur**, hingga **generate ringkasan absensi per periode** yang siap dikonsumsi modul Payroll.

Di aplikasi lama (`hris-system`), modul ini memiliki 15 controller, 16 model, dan 9 service — menunjukkan kompleksitasnya yang tinggi. Aplikasi baru akan mempertahankan alur bisnis yang sama, namun disederhanakan dengan:
- Menghapus `company_id` / `branch_id` (1 instance = 1 DB)
- Renaming tabel dengan prefix `att_` (kecuali `overtime_rules` → `att_overtime_rules`)
- REST API (bukan Inertia) + Vue Router SPA frontend
- `overtime_rules` sebagai shared table (digunakan Attendance & Payroll)
- `permit_requests` dihapus — izin ditangani via Leave module (`lve_types` dengan `category: permit`)

---

## 2. Problem Statement & Latar Belakang

### Kondisi Saat Ini (Aplikasi Lama)
- **15 controller** yang menangani aspek berbeda: log import, record, roster, holiday, overtime, adjustment, consecutive day
- **Alur data 6 tahap:** Fingerprint → Raw Log → Attendance Log → Attendance Autolog → Attendance Prepare → Attendance Record → Payroll
- **Review workflow** di Attendance Prepare: pending → updated → approved/rejected
- **Locking mechanism** untuk mencegah perubahan setelah periode payroll di-lock
- **Multi-tenancy** (`company_id` + `branch_id`) di setiap tabel — tidak relevan di multi-instance

### Masalah yang Dipecahkan
1. **Import data fingerprint** — support Excel/CSV/.bin dari berbagai mesin absensi
2. **Auto-processing** — mencocokkan scan dengan karyawan, menentukan check-in/out
3. **Kalkulasi otomatis** — keterlambatan, pulang awal, lembur (dengan multiplier rules)
4. **Review workflow** — HR bisa review, koreksi, lock data absensi sebelum diproses payroll
5. **Ringkasan periodik** — aggregate data absensi per periode payroll
6. **Integrasi cuti/izin** — hari cuti, sakit, izin masuk ke perhitungan absensi
7. **Lembur** — pengajuan, approval, perhitungan, koneksi ke payroll

---

## 3. Goals & Success Metrics

| Goal | Metric | Baseline | Target | Timeline |
|------|--------|----------|--------|----------|
| Import data fingerprint | % log terproses otomatis | - | 100% | Fase 7 |
| Akurasi check-in/out matching | False positive rate | - | < 1% | Fase 7 |
| Waktu proses 1000 log | Durasi auto-processing | - | < 30 detik | Fase 7 |
| Review & approval | Waktu review 1 periode | 2 hari (manual) | 30 menit | Fase 7 |
| Generate ringkasan | Durasi per periode | 1 jam manual | 5 detik | Fase 7 |

---

## 4. Alur Data (Data Flow)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        ALUR DATA ATTENDANCE                          │
└──────────────────────────────────────────────────────────────────────┘

                          ┌─────────────────┐
                          │  Mesin Fingerprint │
                          │  (Excel / CSV /  │
                          │   .bin / via API) │
                          └────────┬─────────┘
                                   │
                                   ▼
                     ┌─────────────────────────┐
                     │  1. IMPORT RAW LOGS      │
                     │  ┌─────────────────────┐ │
                     │  │  att_raw_logs       │ │
                     │  │  (data mentah dari   │ │
                     │  │   mesin fingerprint) │ │
                     │  └─────────────────────┘ │
                     └───────────┬─────────────┘
                                 │ proses auto: match employee
                                 ▼
                     ┌─────────────────────────┐
                     │  2. ATTENDANCE LOGS      │
                     │  ┌─────────────────────┐ │
                     │  │  att_logs           │ │
                     │  │  (scan matched ke   │ │
                     │  │   karyawan + status) │ │
                     │  └─────────────────────┘ │
                     └───────────┬─────────────┘
                                 │ proses: pair check-in/out
                                 ▼
                ┌────────────────────────────────────┐
                │  3. SCHEDULE / SHIFT MATCHING       │
                │  ┌──────────────────────────────┐   │
                │  │  sch_rosters (jadwal per      │   │
                │  │  karyawan per tanggal)        │   │
                │  └──────────────────────────────┘   │
                │  ┌──────────────────────────────┐   │
                │  │  sch_shifts + sch_patterns    │   │
                │  │  (definisi shift & pola)      │   │
                │  └──────────────────────────────┘   │
                │  ┌──────────────────────────────┐   │
                │  │  sch_calendars + sch_holidays │   │
                │  │  (kalender kerja & libur)     │   │
                │  └──────────────────────────────┘   │
                └────────────────┬───────────────────┘
                                 │ kalkulasi: late, early leave, overtime
                                 ▼
                     ┌─────────────────────────┐
                     │  4. ATTENDANCE PREPARE   │ ←── Manual koreksi
                     │  ┌─────────────────────┐ │     Review workflow
                     │  │  att_prepares       │ │     Lock/unlock
                     │  │  (daily attendance  │ │
                     │  │   per karyawan,     │ │
                     │  │   siap direview)     │ │
                     │  └─────────────────────┘ │
                     └───────────┬─────────────┘
                                 │ generate ringkasan per periode
                                 ▼
                     ┌─────────────────────────┐
                     │  5. ATTENDANCE RECORDS   │
                     │  ┌─────────────────────┐ │
                     │  │  att_records        │ │ ←── Dipakai Payroll
                     │  │  (ringkasan per     │ │
                     │  │   karyawan per       │ │
                     │  │   periode)          │ │
                     │  └─────────────────────┘ │
                     └───────────┬─────────────┘
                                 │
                                 ▼
                     ┌─────────────────────────┐
                     │  6. PAYROLL              │
                     │  (Fase 8)                │
                     └─────────────────────────┘

  ┌──────────────────────────────────────────────────────────────────┐
  │                        SIDE PROCESSES                            │
  └──────────────────────────────────────────────────────────────────┘

  ┌──────────────────────┐     ┌──────────────────────┐
  │  OVERTIME REQUESTS    │     │  CONSECUTIVE DAYS    │
  │  ┌──────────────────┐ │     │  ┌─────────────────┐ │
  │  │ att_overtimes    │ │     │  │ att_consecutive_ │ │
  │  │ (pengajuan       │ │     │  │ days (track      │ │
  │  │  lembur +        │ │     │  │  kehadiran       │ │
  │  │  approval)       │ │     │  │  berturut-turut) │ │
  │  └──────────────────┘ │     │  └─────────────────┘ │
  └──────────────────────┘     └──────────────────────┘

  ┌──────────────────────────────┐
  │  OVERTIME RULES               │
  │  ┌──────────────────────────┐ │
  │  │ att_overtime_rules       │ │
  │  │ (shared: Attendance &    │ │
  │  │  Payroll)                │ │
  │  └──────────────────────────┘ │
  └──────────────────────────────┘
```

---

## 5. Target Persona

| Role | Persona | Pain Point | Goal |
|------|---------|------------|------|
| **HR Assistant** | Sari — input absensi harian | Harus manual cocokin scan fingerprint | Import otomatis + auto-matching karyawan |
| **HR Manager** | Budi — review & approval | Ragu data absensi sebelum generate payroll | Review workflow + locking mechanism |
| **Karyawan** | Adi — staff | Gak tahu rekap absensinya sendiri | Lihat riwayat absensi pribadi |
| **Manajemen** | Pak Dirut | Mau rekap absensi bulanan cepat | Ringkasan per periode + export Excel |

---

## 6. Functional Requirements

### 🔵 FR-001: Import Data Fingerprint
**Priority:** Must Have
**User Story:** Sebagai HR Assistant, saya ingin import file Excel/CSV dari mesin fingerprint, agar data scan karyawan masuk ke sistem.

**Acceptance Criteria:**
- [ ] Import file Excel (.xlsx, .xls) dan CSV — maks 20MB
- [ ] Auto-detect kolom: PIN, Nama, Tanggal, Jam, Mesin SN, Verify Type
- [ ] Preview hasil parsing sebelum commit
- [ ] Matching otomatis ke Employee berdasarkan `employee_code` (PIN) atau `name`
- [ ] Flag `is_processed = false` untuk data baru
- [ ] Tracking via `import_batch` dan `source_file`
- [ ] Mode import: **Create** (tambah baru) atau **Replace** (hapus data batch lalu import ulang)
- [ ] Logging: catat jumlah sukses, gagal, skip per batch
- [ ] Tampilkan statistik: total log, processed, unprocessed, with/without employee

**Notes:** Di old app ada `ProcessAttendanceLogImport` job + `FingerprintBinParser` service untuk parsing file .bin. Di app baru, support Excel/CSV dulu, .bin menyusul.

---

### 🔵 FR-002: Auto-Process Logs → Check-in/Check-out Pairing
**Priority:** Must Have
**User Story:** Sebagai sistem, saya ingin memproses log mentah menjadi pasangan check-in/check-out per karyawan per hari, agar HR tinggal review.

**Acceptance Criteria:**
- [ ] Proses semua `att_logs` dengan `is_processed = false`
- [ ] Pasangkan scan jadi check-in (scan pertama) dan check-out (scan terakhir) per karyawan per hari
- [ ] Cocokkan dengan jadwal shift karyawan (`sch_rosters`) untuk tentuin toleransi & aturan
- [ ] Hitung otomatis: `late_duration`, `early_leave_duration`, `overtime_duration`
- [ ] Deteksi hari libur (`sch_holidays`), hari Minggu, half-day
- [ ] Flag `is_leave` jika ada cuti/izin yang di-approve di tanggal tersebut
- [ ] Hasilnya simpan ke `att_autologs` (auto-generated, siap review)

**Kalkulasi (port dari AttendanceCalculatorService):**
| Aspek | Rumus |
|-------|-------|
| **Late** | `scan_in - shift_start - tolerance` (tolerance default 30 menit) |
| **Early Leave** | `shift_end - scan_out` |
| **Overtime** | `(scan_out - scan_in) - normal_hours` |
| **Overtime Multiplier** | Hari kerja: jam1 × 1.5, sisanya × 2. Libur: (max 8-1) × 2 |
| **Round Up** | 0-24→0, 25-54→30, 55-84→60, kelipatan 30 menit |

---

### 🔵 FR-003: Review & Approval Attendance (Daily Prepares)
**Priority:** Must Have
**User Story:** Sebagai HR Manager, saya ingin mereview dan menyetujui data absensi harian sebelum di-lock ke payroll, agar data yang digunakan akurat.

**Acceptance Criteria:**
- [ ] Tampilkan daily attendance per karyawan (`att_prepares`)
- [ ] Status: `present`, `late`, `absent`, `leave`, `permit`, `holiday`, `off`
- [ ] Review workflow: `pending` → `updated` (manual edit) → `approved` / `rejected`
- [ ] HR bisa koreksi manual: check-in time, check-out time, status, notes
- [ ] Setelah koreksi, kalkulasi ulang otomatis (late, overtime, dll)
- [ ] Locking: data yang sudah di-lock tidak bisa diedit
- [ ] Batch approve / batch lock per departemen atau per periode
- [ ] Audit log: catat siapa yang edit, kapan, nilai lama & baru

**Tabel `att_prepares` fields (port dari old):**
```
date, employee_id, shift_roster_id
check_in, check_out, check_in_log_id, check_out_log_id
status (present/late/absent/leave/permit/holiday/off)
review_status (pending/updated/approved/rejected)
late_duration, early_leave_duration, overtime_duration
is_halfday, is_sunday, is_holiday_flag, is_leave_flag, is_permit_flag
shift_code, work_pattern_type, shift_is_overnight
is_manual_edit, last_edited_at, last_edited_by
is_locked, locked_at, locked_by
notes, metadata
```

---

### 🔵 FR-004: Generate Ringkasan Periode (Attendance Records)
**Priority:** Must Have
**User Story:** Sebagai HR, saya ingin generate ringkasan absensi per periode payroll, agar data siap diproses Payroll.

**Acceptance Criteria:**
- [ ] Pilih periode payroll (`pay_periods`)
- [ ] Hitung per karyawan:
  - `hari_kerja` — dari config (fixed days per month) - `deduct_day`
  - `deduct_day` — unpaid leave + absen
  - `leave` — paid leave (cuti)
  - `sakit` — sakit (leave type code = SKT)
  - `absen` — jumlah hari absent
  - `lm` (lembur libur) — total `holiday_overtime_duration` dari prepares
  - `lembur` — total `overtime_duration` dari prepares
  - `kalkulasi_lembur` — total `calculated_overtime` dari prepares
- [ ] Simpan ke `att_records` dengan relasi ke `pay_periods`
- [ ] Hanya karyawan aktif (`is_active = true`) yang di-generate
- [ ] Data yang sudah ada di-update (tidak duplikat)

**Tabel `att_records` fields (port dari old):**
```
employee_id, periode_id, group_id, prepare_id, leave_id
hari_kerja (integer)
deduct_day (decimal:2)
leave (decimal:2)
sakit (decimal:2)
absen (integer)
lm (integer) — holiday overtime
lembur (integer) — regular overtime
kalkulasi_lembur (integer) — calculated overtime
```

---

### 🟢 FR-005: Lembur (Overtime Requests)
**Priority:** Should Have
**User Story:** Sebagai HR, saya ingin mengelola pengajuan lembur karyawan — mulai dari pengajuan, approval, hingga kalkulasi, agar lembur terbayar sesuai aturan.

**Acceptance Criteria:**
- [ ] Buat pengajuan lembur: pilih karyawan, tanggal, start_time, end_time, reason
- [ ] Hitung otomatis `duration_minutes` dari start-end time
- [ ] Multiplier dari `att_overtime_rules` berdasarkan day_type + work_system + hour_sequence
- [ ] Approval flow: `pending` → `approved` / `rejected` (dengan rejection_reason)
- [ ] Tampilkan: total_payable_minutes (`duration_minutes × multiplier`)
- [ ] Flag `is_paid` dan `is_included_in_payroll`
- [ ] Link ke payroll: setelah masuk payroll, `is_included_in_payroll = true`

**Tabel `att_overtimes` fields:**
```
employee_id, approved_by
date, start_time, end_time, duration_minutes, multiplier
type (regular/holiday), reason, status (pending/approved/rejected)
rejection_reason, approved_at
is_paid, is_included_in_payroll, payroll_id
```

---

### 🟢 FR-006: Aturan Lembur (Overtime Rules)
**Priority:** Should Have
**User Story:** Sebagai Admin, saya ingin mengkonfigurasi aturan lembur (multiplier per jam, per hari libur), agar perhitungan lembur sesuai kebijakan perusahaan.

**Acceptance Criteria:**
- [ ] CRUD aturan lembur
- [ ] Fields: `rule_code`, `name`, `day_type` (weekday/saturday/sunday/holiday), `work_system` (R/S)
- [ ] `hour_sequence`: range jam (contoh: "1-3" untuk jam ke 1-3, ">4" untuk di atas 4 jam)
- [ ] `multiplier`: faktor pengali (1.5, 2, 3, dll)
- [ ] `max_minutes`: batas maksimal lembur
- [ ] Effective date: `effective_from` - `effective_until`
- [ ] Shared table: digunakan juga oleh modul Payroll

**Tabel `att_overtime_rules` fields (port dari old):**
```
rule_code, name, day_type, work_system (R/S)
hour_sequence, multiplier, max_minutes
effective_from, effective_until, is_active
```

---

### 🟢 FR-007: Hari Libur & Kalender Kerja
**Priority:** Should Have
**User Story:** Sebagai Admin, saya ingin mengatur hari libur nasional & kalender kerja, agar sistem tahu kapan hari kerja efektif.

**Acceptance Criteria:**
- [ ] Lihat kalender kerja (view per bulan/tahun)
- [ ] Tambah/edit/hapus hari libur: `date`, `name`, `type` (national/company/optional)
- [ ] Hari libur otomatis flag `is_holiday` di proses absensi
- [ ] Integrasi dengan `sch_calendars` (dari Fase 5 Schedule)

---

### 🟢 FR-008: Logs & Riwayat Scan
**Priority:** Should Have
**User Story:** Sebagai HR, saya ingin melihat raw log scan fingerprint dengan filter tanggal, karyawan, status proses, agar bisa tracking masalah.

**Acceptance Criteria:**
- [ ] Tabel log dengan filter: date range, employee, processed/unprocessed
- [ ] Tampilkan: employee_code, name, scan_datetime, scan_type, machine_sn, verify_type
- [ ] Statistik: total, processed, unprocessed, with_employee, without_employee
- [ ] Hapus log per batch
- [ ] Download log (export)

---

### 🟡 FR-009: Consecutive Days Tracking
**Priority:** Could Have
**User Story:** Sebagai HR, saya ingin melacak kehadiran berturut-turut karyawan, untuk perhitungan tunjangan kehadiran.

**Acceptance Criteria:**
- [ ] Hitung otomatis hari kehadiran berturut-turut per karyawan
- [ ] Record: `earned_date`, `used_date`, `expired_at`, `status` (available/used)
- [ ] Track via `att_consecutive_days`

---

### 🟡 FR-010: Dashboard Ringkasan Absensi (Summary)
**Priority:** Should Have
**User Story:** Sebagai HR, saya ingin melihat ringkasan kehadiran per periode (present, absent, late, overtime) dalam bentuk cards & chart.

**Acceptance Criteria:**
- [ ] Stat cards: total karyawan, hadir, absen, terlambat, cuti, izin, sakit
- [ ] Pie chart / bar chart per departemen
- [ ] Tabel ringkasan per karyawan bisa di-export
- [ ] Link ke detail per karyawan

---

## 7. Non-Functional Requirements

| Kategori | Requirement |
|----------|------------|
| **Performance** | Import 10.000 log < 10 detik (background job) |
| **Performance** | Generate ringkasan 500 karyawan < 5 detik |
| **Security** | Data absensi hanya bisa diakses sesuai role permission |
| **Reliability** | Import pake queue job + progress tracking |
| **Scalability** | Support 10.000+ log per hari |
| **Sync** | Semua tabel data utama punya `uuid` + `synced_at` untuk desktop sync |
| **Audit** | Semua perubahan (edit, approve, lock) tercatat di `audit_logs` |

---

## 8. Scope

### ✅ In Scope (Fase 7)
| Fitur | Priority |
|-------|----------|
| Import data fingerprint (Excel/CSV) | Must Have |
| Auto-process log → pairing check-in/out | Must Have |
| Review & approval daily attendance | Must Have |
| Lock/unlock attendance data | Must Have |
| Generate ringkasan per periode (att_records) | Must Have |
| CRUD lembur (overtime requests) | Should Have |
| Approval lembur | Should Have |
| Aturan lembur (overtime rules) | Should Have |
| Hari libur & kalender kerja | Should Have |
| Logs & riwayat scan viewer | Should Have |
| Dashboard ringkasan absensi | Should Have |
| Export rekap absensi (Excel) | Should Have |
| Consecutive days tracking | Could Have |

### ❌ Out of Scope (Fase 7)
| Fitur | Keterangan |
|-------|------------|
| Import file .bin fingerprint | Menyusul setelah Excel/CSV stabil |
| Fingerprint device API integration | Integrasi real-time via API device |
| Face recognition / geofence / QR scan | Fitur tambahan di versi mendatang |
| Supervisor Dashboard (Aplikasi Bayangan) | Ada di fase terpisah (AuditSection) |
| Desktop sync (offline-first) | Detail akan direncanakan terpisah |
| Integrasi payroll otomatis | Dilakukan di Fase 8 (Payroll) |

---

## 9. Tabel Database (Migration)

### Renaming dari Old → New (sesuai MIGRATION_GUIDE.md)

| Old Name | New Name | Notes |
|----------|----------|-------|
| `raw_logs` / `attendances` | **`att_raw_logs`** | Data mentah dari mesin fingerprint |
| `attendance_logs` | **`att_logs`** | Scan matched ke karyawan |
| `attendance_autologs` | **`att_autologs`** | Auto-generated check-in/out pairs |
| `attendance_prepares` | **`att_prepares`** | Daily attendance siap review |
| `attendance_records` | **`att_records`** | Ringkasan per periode |
| `attendance_summaries` | **`att_summaries`** | Ringkasan detail + snapshot |
| `attendance_consecutive_days` | **`att_consecutive_days`** | Tracking kehadiran berturut-turut |
| `attendance_configs` | **`att_configs`** | Konfigurasi absensi |
| `scan_detection_configs` | **`att_scan_configs`** | Konfigurasi deteksi scan |
| `overtime_rules` | **`att_overtime_rules`** | Shared: Attendance & Payroll |
| `overtimes` | **`att_overtimes`** | Pengajuan lembur |

### Tabel yang DIHAPUS
- ~~`permit_requests`~~ → izin ditangani via Leave module (`lve_types` category = permit)

### Struktur per Tabel

> **Catatan:** Semua tabel berikut **TIDAK** memiliki `company_id` / `branch_id` (1 instance = 1 DB).

#### `att_raw_logs`
```
id, uuid, synced_at (nullable)
employee_code (nullable), scan_datetime, scan_type (in/out)
machine_sn, machine_name, verify_type
pin (nullable), raw_data (json, nullable)
import_batch, source_file
status (pending/matched/unmatched)
created_at, updated_at
```

#### `att_logs`
```
id, uuid, synced_at (nullable)
employee_id (FK → employees, nullable)
employee_code, employee_name
scan_datetime, scan_type, machine_sn, machine_name, verify_type
pin (nullable), raw_data (json, nullable)
is_processed (bool, default false), processed_at (nullable), processed_by (FK → users, nullable)
import_batch, source_file
created_at, updated_at, deleted_at
```

#### `att_autologs`
```
id, uuid, synced_at (nullable)
employee_id, employee_shift_roster_id (nullable)
date, check_in, check_out
check_in_log_id, check_out_log_id
status (present/late/absent/leave/permit/holiday/off)
late_duration (int), early_leave_duration (int), overtime_duration (int)
deduct_attendance (int)
actual_in, actual_out
is_half_day (bool), holiday_overtime (bool)
is_sun (bool), is_sat (bool), is_holiday (bool)
is_manual_edit (bool), last_edited_at, last_edited_by
is_locked (bool), locked_at, locked_by
notes (text), metadata (json, nullable)
scan_count (int)
is_leave (bool), leave_id (nullable)
deduct_day (decimal:2)
izin_duration (int), sakit_duration (int)
overtime_converted_hours (int)
created_at, updated_at, deleted_at
```

#### `att_prepares`
```
id, uuid, synced_at (nullable)
employee_id, employee_shift_roster_id (nullable)
date, check_in, check_out
check_in_log_id (nullable), check_out_log_id (nullable)
status (present/late/absent/leave/permit/holiday/off)
review_status (pending/updated/approved/rejected)
late_duration (int), early_leave_duration (int)
holiday_overtime_duration (int), overtime_duration (int)
lm_count (int), calculated_overtime (int)
is_halfday (bool), is_sunday (bool), is_holiday_flag (bool)
is_leave_flag (bool), is_permit_flag (bool)
shift_code, work_pattern_type (nullable)
shift_check_in_start, shift_check_in_end
shift_check_out_start, shift_check_out_end
shift_check_out_overnight_start, shift_check_out_overnight_end
shift_is_overnight (bool)
halfday_end_time
is_manual_edit (bool), last_edited_at, last_edited_by
deduct_attendance (int)
is_locked (bool), locked_at, locked_by
reviewed_at, reviewed_by
attendance_record_id (nullable)
notes (text), metadata (json, nullable)
created_at, updated_at, deleted_at
```

#### `att_records`
```
id, uuid, synced_at (nullable)
employee_id, group_id (nullable), prepare_id (nullable)
periode_id (FK → pay_periods), leave_id (nullable)
hari_kerja (int)
deduct_day (decimal:2)
leave (decimal:2), sakit (decimal:2)
absen (int)
lm (int) — holiday overtime
lembur (int) — regular overtime
kalkulasi_lembur (int) — calculated overtime
created_at, updated_at, deleted_at
```

#### `att_summaries`
```
id, employee_id, period_code
period_start, period_end
total_working_days (int), total_present_days (int)
total_absent_days (int), total_late_days (int)
total_late_minutes (int), total_early_leave_minutes (int)
total_overtime_minutes (int)
total_leave_days (int), total_unpaid_days (int)
total_sick_days (int), total_permit_days (int)
overtime_breakdown (json, nullable)
attendance_snapshot (json, nullable)
status (draft/locked/processed)
is_locked (bool), locked_by, locked_at
payroll_id (nullable), payroll_period_id (nullable)
metadata (json, nullable)
created_by, updated_by
created_at, updated_at, deleted_at
```

#### `att_consecutive_days`
```
id, employee_id
earned_date, used_date (nullable), expired_at (nullable)
status (available/used)
notes (nullable), created_by, updated_by
created_at, updated_at
```

#### `att_overtimes`
```
id, uuid, synced_at (nullable)
employee_id, approved_by (nullable)
date, start_time, end_time, duration_minutes (int)
multiplier (decimal:2)
type (regular/holiday)
reason, status (pending/approved/rejected)
rejection_reason (nullable), approved_at (nullable)
is_paid (bool, default false)
is_included_in_payroll (bool, default false)
payroll_id (nullable)
created_at, updated_at
```

#### `att_overtime_rules` (shared table)
```
id, rule_code, name
day_type (weekday/saturday/sunday/holiday)
work_system (R/S)
hour_sequence (string — format: "1-3", ">4", "1")
multiplier (float)
max_minutes (int, nullable)
effective_from, effective_until (nullable)
is_active (bool, default true)
description (nullable)
created_at, updated_at
```

#### `att_configs`
```
id, shift_id (nullable)
label, start_time, end_range, start_range
is_active (bool, default true)
created_at, updated_at
```

#### `att_scan_configs`
```
id, config_key, config_value (text)
description (nullable), is_active (bool)
created_at, updated_at
```

---

## 10. API Endpoints

### Attendance Logs (Import & Logs)
```
GET    /api/attendance/logs                    → List logs (filterable)
POST   /api/attendance/logs/import             → Import Excel/CSV
GET    /api/attendance/logs/import/{batch}     → Cek status import
POST   /api/attendance/logs/process            → Proses unprocessed logs
DELETE /api/attendance/logs/batch/{batch}      → Hapus log per batch
GET    /api/attendance/logs/stats              → Statistik log
```

### Attendance Prepares (Daily Attendance)
```
GET    /api/attendance/prepares                → List daily attendance
POST   /api/attendance/prepares/generate       → Generate dari autologs
GET    /api/attendance/prepares/{id}           → Detail
PUT    /api/attendance/prepares/{id}           → Update (koreksi manual)
POST   /api/attendance/prepares/batch-approve  → Batch approve
POST   /api/attendance/prepares/batch-lock     → Batch lock
POST   /api/attendance/prepares/{id}/approve   → Approve single
POST   /api/attendance/prepares/{id}/reject    → Reject single
POST   /api/attendance/prepares/{id}/lock      → Lock single
POST   /api/attendance/prepares/{id}/unlock    → Unlock single
```

### Attendance Records (Period Summary)
```
GET    /api/attendance/records                 → List records (filter by periode)
POST   /api/attendance/records/generate        → Generate ringkasan per periode
GET    /api/attendance/records/{id}            → Detail record
```

### Overtime
```
GET    /api/attendance/overtimes               → List lembur
POST   /api/attendance/overtimes               → Buat pengajuan
PUT    /api/attendance/overtimes/{id}          → Edit
DELETE /api/attendance/overtimes/{id}          → Hapus
POST   /api/attendance/overtimes/{id}/approve  → Approve
POST   /api/attendance/overtimes/{id}/reject   → Reject
```

### Overtime Rules
```
GET    /api/attendance/overtime-rules          → List aturan
POST   /api/attendance/overtime-rules          → Buat aturan baru
PUT    /api/attendance/overtime-rules/{id}     → Edit
DELETE /api/attendance/overtime-rules/{id}     → Hapus
```

### Holidays & Calendar
```
GET    /api/attendance/holidays                → List holiday
POST   /api/attendance/holidays                → Tambah
PUT    /api/attendance/holidays/{id}           → Edit
DELETE /api/attendance/holidays/{id}           → Hapus
GET    /api/attendance/calendar                → Kalender kerja (bulan/tahun)
```

### Dashboard / Summary
```
GET    /api/attendance/summary                 → Ringkasan per periode
GET    /api/attendance/dashboard               → Stat cards + chart
```

### Supervisor (Aplikasi Bayangan) — setelah Fase 9
```
GET    /api/supervisor/attendance/...          → Endpoint terpisah untuk AuditSection
```

---

## 11. Service Layer

### Services yang Di-port dari Old App

| Service | File | Fungsi |
|---------|------|--------|
| **AttendanceCalculatorService** | `app/Modules/Attendance/Services/AttendanceCalculatorService.php` | Hitung late, early leave, overtime, multiplier |
| **AttendanceSyncService** | `app/Modules/Attendance/Services/AttendanceSyncService.php` | Sync log → autolog → prepare |
| **OvertimeService** | `app/Modules/Attendance/Services/OvertimeService.php` | CRUD + approval lembur |
| **OvertimeRuleService** | `app/Modules/Attendance/Services/OvertimeRuleService.php` | Validasi aturan lembur |
| **HolidayService** | `app/Modules/Attendance/Services/HolidayService.php` | Manajemen hari libur |
| **FingerprintBinParser** | `app/Modules/Attendance/Services/FingerprintBinParser.php` | Parsing .bin (post-MVP) |
| **TimeParser** | `app/Modules/Attendance/Services/TimeParser.php` | Helper parsing waktu |

### Jobs (Background Processing)

| Job | Fungsi | Trigger |
|-----|--------|---------|
| `ProcessAttendanceLogImport` | Import Excel/CSV ke `att_logs` | Upload file |
| `ProcessUnprocessedLogsJob` | Auto-pairing check-in/out ke `att_autologs` | Manual / Cron |
| `ProcessAttendanceOvertimeSync` | Sinkronisasi lembur ke prepare | Setelah approve lembur |
| `RunAttendanceSyncJob` | Full sync: log → autolog → prepare | Manual / Cron |

---

## 12. Risiko & Dependensi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| **Dependensi Fase 5 (Schedule)** | Prepares butuh shift & roster | Pastikan Fase 5 selesai sebelum Fase 7 |
| **Dependensi Fase 4 (Employee)** | Semua proses butuh data karyawan | Udah selesai ✅ |
| **Complexitas kalkulasi lembur** | Beda client beda aturan | Buat `att_overtime_rules` fleksibel |
| **Format file fingerprint** | Tiap vendor beda format kolom | Buat mapping kolom + preview sebelum import |
| **Data lama dari hris-system** | Format data bisa beda | Buat migration script khusus |

---

## 13. Timeline Estimasi

### Sprint 1 — Core (Minggu 1-2)
- [ ] Migration: `att_raw_logs`, `att_logs`, `att_autologs`, `att_prepares`
- [ ] Model + Migration semua tabel attendance
- [ ] Service: AttendanceCalculatorService (port)
- [ ] API: Logs import & auto-process

### Sprint 2 — Review & Records (Minggu 3-4)
- [ ] API: Prepares CRUD + review workflow
- [ ] API: Records generate
- [ ] Service: AttendanceSyncService (port)
- [ ] Frontend: Halaman Log Import + List Log

### Sprint 3 — Overtime & Holiday (Minggu 5-6)
- [ ] API: Overtime CRUD + approval
- [ ] API: Overtime Rules
- [ ] API: Holidays & Calendar
- [ ] Frontend: Halaman Prepares + Records
- [ ] Frontend: Halaman Overtime

### Sprint 4 — Finalisasi (Minggu 7-8)
- [ ] Frontend: Dashboard summary + chart
- [ ] Frontend: Export Excel
- [ ] Jobs: background processing queue
- [ ] Testing: import → process → review → generate → payroll integration

---

## 14. Frontend Pages (Vue Router SPA)

| Route | Page | Priority |
|-------|------|----------|
| `/admin/attendance/logs` | Log Import & Riwayat Scan | 🟢 |
| `/admin/attendance/prepares` | Daily Attendance (Review) | 🔴 |
| `/admin/attendance/records` | Ringkasan Periode | 🔴 |
| `/admin/attendance/overtimes` | Pengajuan Lembur | 🟢 |
| `/admin/attendance/overtime-rules` | Aturan Lembur | 🟢 |
| `/admin/attendance/holidays` | Hari Libur & Kalender | 🟢 |
| `/admin/attendance/summary` | Dashboard Ringkasan | 🟢 |

---

## 15. Permission Mapping

| Permission | superadmin | hrmanager | adm_manager | hrbranch | hr_ast |
|-----------|------------|-----------|-------------|----------|--------|
| `view attendances` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `import attendances` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `edit attendances` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `manage overtime` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `approve overtime` | ✅ | ✅ | ❌ | ❌ | ❌ |

---

## 16. Catatan Penting

1. **Wajib port `AttendanceCalculatorService`** — ini jantung perhitungan absensi. Jangan rewrite dari 0, port logic dari old app.
2. **Wajib port `AttendanceSyncService`** — alur log → autolog → prepare harus dipertahankan.
3. **`att_prepares` adalah tabel paling kompleks** — banyak field, review workflow, locking. Fokus testing di sini.
4. **Integrasi Payroll** hanya lewat `att_records.periode_id` — pastikan format data cocok dengan Fase 8.
5. **Data lama** dari `hris-system` perlu migration script khusus untuk menyesuaikan format tabel baru.
6. **Preview import** — jangan langsung commit. Tampilkan preview dulu ke user.

---

> *Draft PRD Attendance v0.1 — 2 Juni 2026*
> *Siap di-breakdown bareng, Sigit! 🚀*
