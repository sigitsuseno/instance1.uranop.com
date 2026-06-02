# PRD — Desktop App Instance1 HRIS

| Metadata | |
|----------|---------|
| **Author** | Sigit / Paijo |
| **Project** | instance1.uranop.com — HRIS Multi-Instance |
| **Versi** | Draft v0.1 |
| **Status** | Draft |
| **Tanggal** | 2026-06-02 |
| **Tech Stack** | Tauri v2 + Vue 3 + shadcn/vue + SQLite |
| **Backend Reference** | instance1.uranop.com (Laravel REST API) |

---

## 1. Executive Summary

Desktop App Instance1 adalah **pendamping offline-first** dari aplikasi web HRIS yang sudah ada. Aplikasi ini memberikan pengalaman native desktop untuk tugas-tugas operasional HR sehari-hari — terutama **pengelolaan absensi (attendance)** yang merupakan modul paling kompleks dan paling sering diakses.

Tidak seperti web app yang membutuhkan koneksi internet terus-menerus, desktop app ini bekerja **offline-first**: data disimpan lokal di SQLite, dan secara otomatis **sinkronisasi dengan backend Laravel** saat koneksi tersedia. Ini sangat krusial untuk HR di lapangan yang mungkin bekerja dari lokasi dengan koneksi tidak stabil, atau perlu mengakses data dari mesin fingerprint langsung dari komputer.

---

## 2. Problem Statement

### Masalah Saat Ini (Web-only)

| Masalah | Dampak |
|---------|--------|
| **Ketergantungan internet** — HR tidak bisa bekerja jika koneksi ke server terputus | Produktivitas terhenti, data absensi menumpuk |
| **Import data fingerprint lambat** — upload file .bin/Excel ke web server via form | Bergantung pada kecepatan upload, bermasalah untuk file besar (>10MB) |
| **Tidak ada akses ke perangkat lokal** — web browser tidak bisa baca file lokal langsung | HR harus copy file manual, lalu upload |
| **Performa berat** — halaman attendance dengan ribuan record terasa lambat di browser | User experience menurun, scrolling & filter lambat |
| **Multi-tab/multi-window terbatas** — browser butuh banyak memori, gampang crash | HR sering kerja dengan banyak data sekaligus |
| **Notifikasi tidak real-time** — harus refresh browser untuk lihat update | Missed notification, approval tertunda |

### Kenapa Sekarang?

- Backend API Laravel sudah siap dengan REST endpoints
- Modul Employee, Auth, Organization sudah functional
- Modul Attendance adalah yang paling berat dan paling cocok untuk desktop-first
- Arsitektur multi-instance sudah matang — desktop app tinggal consume API per instance
- Tauri v2 sudah stable dengan SQLite plugin yang mature

---

## 3. Goals & Success Metrics

| Goal | Metric | Baseline | Target | Timeline |
|------|--------|----------|--------|----------|
| **Offline-first** | Bisa operasional penuh tanpa internet | 0% (web-only) | 100% fitur inti jalan offline | v1.0 |
| **Import fingerprint** | Waktu import 10.000 record | ~2 menit (via web upload) | <5 detik (lokal) | v1.0 |
| **Performa** | Response time filter/search 10.000 record | ~3-5 detik (web) | <100ms (desktop, SQLite lokal) | v1.0 |
| **Sync reliability** | Zero data loss antar device | — | 99.9% sukses sync | v1.0 |
| **User adoption** | HR pake desktop > web untuk daily ops | 0% | 80% HR pake desktop | v2.0 |

---

## 4. Arsitektur & Alur Data

### 4.1 Hubungan Desktop ↔ Backend

```
┌─────────────────────────────────────────────────────────────────┐
│                        DESKTOP APP (Tauri)                       │
│  ┌──────────────┐    ┌──────────────┐    ┌───────────────────┐   │
│  │  Vue 3 SPA    │    │  Sync Engine  │    │  SQLite (Lokal)   │   │
│  │  (shadcn/ui)  │◄──►│  (background) │◄──►│  - att_raw_logs   │   │
│  │               │    │               │    │  - att_logs       │   │
│  │  - Attendance │    │  - Push       │    │  - att_records    │   │
│  │  - Roster     │    │  - Pull       │    │  - employees      │   │
│  │  - Overtime   │    │  - Conflict   │    │  - schedules      │   │
│  │  - Dashboard  │    │  - Retry      │    │  - settings       │   │
│  └──────────────┘    └──────┬───────┘    └───────────────────┘   │
└─────────────────────────────┼───────────────────────────────────┘
                              │ HTTPS / REST
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     BACKEND (Laravel)                            │
│  ┌─────────────────────┐        ┌──────────────────────────┐    │
│  │  REST API            │        │  MySQL (Server)           │    │
│  │  /api/v1/...         │◄──────►│  - Semua tabel master     │    │
│  └─────────────────────┘        └──────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Sync Architecture

```
DESKTOP (SQLite)                    BACKEND (MySQL)
─────────────────                   ────────────────
  [INSERT att_logs]  ───push──►  [INSERT att_logs]
  [UPDATE att_logs]  ───push──►  [UPDATE att_logs]
                       ◄─pull──  [SELECT employees]
                       ◄─pull──  [SELECT departments]
                       ◄─pull──  [SELECT schedules]

CONFLICT RESOLUTION:
  - Last-Write-Wins (LWW) by `synced_at` timestamp
  - Desktop = source of truth untuk: raw_logs, att_logs
  - Backend = source of truth untuk: employees, organization, settings, schedules
```

### 4.3 Sync Flow Detail

```
1. PULL (Saat online, otomatis tiap 5 menit / manual)
   ├── Ambil data master: employees, departments, positions, schedules
   ├── Ambil data transaksi yang berubah sejak `last_synced_at`
   └── Update SQLite lokal

2. PUSH (Setiap ada perubahan + retry jika gagal)
   ├── Kirim data yang `synced_at IS NULL` atau lebih baru dari server
   ├── Kirim batch per 100 record
   ├── Retry 3x dengan exponential backoff
   └── Log gagal sync ke tabel `sync_logs` lokal

3. RESOLVE (Jika terjadi konflik)
   └── Last-write-wins berdasarkan `updated_at` / `synced_at`
```

---

## 5. Target Persona

| Role | Persona | Pain Point | Goal |
|------|---------|------------|------|
| **HR Assistant** | Sari — input absensi harian | Upload file fingerprint lama, harus login web tiap hari | Buka desktop, drag-drop file, otomatis terproses |
| **HR Manager** | Budi — review & approval | Cek data absensi sambil meeting di luar, internet lemot | Buka desktop offline, data tetap bisa diakses & disetujui |
| **Admin Manager** | Adi — supervisor akunting | Butuh data akurat dari aplikasi bayangan | Desktop bisa toggle antara Admin ↔ Supervisor mode |
| **IT Support** | Teknisi — urus mesin fingerprint | Bolak-balik ambil file .bin dari mesin, upload ke web | Colok USB / akses network share, langsung ke desktop app |

---

## 6. Functional Requirements

### FR-001: Desktop Dashboard
- **User Story:** Sebagai HR, saya ingin melihat ringkasan absensi hari ini di desktop, agar cepat tahu kondisi kehadiran tanpa buka browser.
- **Priority:** Must Have
- **Acceptance Criteria:**
  - [ ] Tampilkan stat: total karyawan, hadir, izin, sakit, alpha (hari ini)
  - [ ] Update otomatis tiap kali sync selesai
  - [ ] Bisa filter by department
  - [ ] Grafik kehadiran 7 hari terakhir
  - [ ] **Offline:** Data dari SQLite lokal (tidak perlu koneksi)

### FR-002: Import Data Fingerprint (Lokal)
- **User Story:** Sebagai HR Assistant, saya ingin drag-drop file fingerprint (.bin / .csv / .xlsx) langsung ke desktop app, agar tidak perlu upload via browser.
- **Priority:** Must Have
- **Acceptance Criteria:**
  - [ ] Drag & drop file dari Windows Explorer ke app
  - [ ] Support format: .csv, .xlsx, .xls
  - [ ] Parsing otomatis 10.000 record dalam <5 detik
  - [ ] Preview data sebelum di-save
  - [ ] Deteksi duplikat scan (berdasarkan employee_id + timestamp)
  - [ ] Simpan ke SQLite lokal (`att_raw_logs`)
  - [ ] **Offline:** Bisa import tanpa internet, data tetap tersimpan

### FR-002a: Load dari Network Share / USB
- **User Story:** Sebagai IT Support, saya ingin mengatur folder otomatis yang dipantau desktop app, agar file fingerprint dari mesin absensi langsung terproses.
- **Priority:** Could Have
- **Acceptance Criteria:**
  - [ ] Konfigurasi folder sumber (local path / network share)
  - [ ] Auto-detect file baru di folder
  - [ ] Proses otomatis tanpa klik manual
  - [ ] Notifikasi "N file berhasil diimport"

### FR-003: Auto-Process Absensi
- **User Story:** Sebagai HR Assistant, setelah import log, saya ingin desktop app otomatis mencocokkan scan dengan roster kerja, menetapkan check-in/out, dan menghitung keterlambatan.
- **Priority:** Must Have
- **Acceptance Criteria:**
  - [ ] Matching scan terdekat dengan jadwal shift karyawan
  - [ ] Auto-detect: check-in (scan pertama dalam shift), check-out (scan terakhir)
  - [ ] Hitung: total jam kerja, keterlambatan (menit), pulang awal (menit)
  - [ ] Handle: lembur otomatis (jika melebihi jam kerja)
  - [ ] Handle: absent tanpa scan (tandai alpha)
  - [ ] Proses 5.000 record dalam <10 detik
  - [ ] **Offline:** Proses tetap jalan tanpa server

### FR-004: Review & Koreksi Absensi
- **User Story:** Sebagai HR Manager, saya ingin melihat & mengoreksi data absensi sebelum di-lock dan dikirim ke payroll.
- **Priority:** Should Have
- **Acceptance Criteria:**
  - [ ] Tabel filterable: department, tanggal, status (pending/approved/rejected)
  - [ ] Edit check-in/out time per karyawan
  - [ ] Tambah catatan koreksi (alasan)
  - [ ] Batch approve / reject
  - [ ] Status: Pending → Approved / Rejected
  - [ ] **Sync:** Perubahan dikirim ke server saat online

### FR-005: Manajemen Lembur (Overtime)
- **User Story:** Sebagai Karyawan/HR, saya ingin mengajukan & menyetujui lembur langsung dari desktop.
- **Priority:** Should Have
- **Acceptance Criteria:**
  - [ ] Buat pengajuan lembur: tanggal, jam mulai-selesai, alasan
  - [ ] Approval flow: submit → approve/reject
  - [ ] Hitung otomatis: total jam lembur × rate
  - [ ] Lihat riwayat lembur per karyawan
  - [ ] **Offline:** Pengajuan bisa dibuat offline, sync ketika online

### FR-006: Data Master (Referensi)
- **User Story:** Sebagai HR, saya ingin melihat data karyawan, departemen, dan jadwal kerja dari desktop.
- **Priority:** Must Have
- **Acceptance Criteria:**
  - [ ] Lihat daftar karyawan (search, filter by department)
  - [ ] Lihat detail karyawan: kontrak, posisi, gaji pokok
  - [ ] Lihat daftar departemen & posisi
  - [ ] Lihat jadwal shift & roster
  - [ ] **Data dari sync:** semua read-only, di-pull dari backend

### FR-007: Sync Engine
- **User Story:** Sebagai HR, saya tidak ingin khawatir soal sinkronisasi data — desktop app harus urus sendiri.
- **Priority:** Must Have
- **Acceptance Criteria:**
  - [ ] Auto-pull data master dari backend tiap 5 menit (configurable)
  - [ ] Auto-push data transaksi yang berubah langsung dikirim
  - [ ] Queue system: jika offline, data masuk antrian, push saat online
  - [ ] Conflict resolution: last-write-wins by synced_at
  - [ ] Tampilkan status sync di UI (terakhir sync, antrian pending)
  - [ ] Notifikasi jika sync gagal
  - [ ] Manual sync button

### FR-008: Export Laporan
- **User Story:** Sebagai HR Manager, saya ingin export rekap absensi ke Excel/PDF langsung dari desktop.
- **Priority:** Should Have
- **Acceptance Criteria:**
  - [ ] Export: rekap absensi per periode per departemen
  - [ ] Format: XLSX, PDF
  - [ ] Filter: by department, by tanggal, by status
  - [ ] **Offline:** Export dari data SQLite lokal

### FR-009: Multi-Database Support (Supervisor Mode)
- **User Story:** Sebagai Admin Manager/Supervisor, saya ingin desktop app bisa terhubung ke database bayangan (shadow) untuk perhitungan independen.
- **Priority:** Could Have
- **Acceptance Criteria:**
  - [ ] Toggle: Admin Mode ↔ Supervisor Mode
  - [ ] Mode Supervisor membaca dari tabel `att_snapshots` dan `pay_audits`
  - [ ] Data Supervisor di-pull dari endpoint `/api/supervisor/*`
  - [ ] Tidak bisa edit data Supervisor dari desktop (view-only)

### FR-010: Instance Selector (Multi-Instance)
- **User Story:** Sebagai Superadmin yang manage banyak instance, saya ingin switch antar instance tanpa login ulang.
- **Priority:** Could Have
- **Acceptance Criteria:**
  - [ ] Login sekali, pilih instance dari daftar
  - [ ] Data per instance terisolasi di SQLite (beda database file)
  - [ ] Switch instance: ganti database + sync ulang data master

---

## 7. Non-Functional Requirements

| Kategori | Requirement |
|----------|------------|
| **Performance** | Import 10.000 record fingerprint < 5 detik |
| **Performance** | Query/filter 10.000 record attendance < 200ms |
| **Performance** | Startup app < 3 detik |
| **Storage** | SQLite lokal: estimasi 50MB untuk 50.000 record + master data |
| **Offline** | 100% fitur inti (FR-001, FR-002, FR-003, FR-005, FR-006) jalan tanpa internet |
| **Sync** | Push queue tidak lebih dari 5MB per batch |
| **Sync** | Retry gagal sync: 3x dengan exponential backoff (10s, 30s, 60s) |
| **Security** | API token disimpan di encrypted storage (Tauri secure store) |
| **Security** | SQLite database tidak di-encrypt di v1.0 (pertimbangan untuk v2.0) |
| **Compat** | Windows 10/11 (target utama), macOS (future) |
| **Size** | Installer < 30MB |

---

## 8. Scope

### ✅ In Scope (v1.0)

| Prioritas | Fitur |
|-----------|-------|
| **Must** | Dashboard ringkasan absensi |
| **Must** | Import data fingerprint (drag-drop, .bin/.csv/.xlsx) |
| **Must** | Auto-process absensi (matching, check-in/out, keterlambatan) |
| **Must** | Sync Engine: pull master data, push transaksi, retry |
| **Must** | Data master viewer: employees, departments, schedules (read-only) |
| **Must** | Login & auth dengan API token |
| **Must** | SQLite lokal untuk semua data |
| **Should** | Review & koreksi absensi (approve/reject) |
| **Should** | Manajemen lembur (pengajuan + approval) |
| **Should** | Export laporan (Excel) |
| **Could** | Supervisor Mode (multi-database) |
| **Could** | Auto-watch folder untuk import fingerprint |

### ❌ Out of Scope (v1.0)

| Fitur | Alasan | Rencana |
|-------|--------|---------|
| Payroll full management | Kompleksitas tinggi, via web saja | v2.0 |
| Leave management | Via web, lebih cocok di mobile/browser | v2.0 |
| Employee CRUD | Create/edit via web, desktop view-only | v2.0 |
| Real-time sync dengan WebSocket | Complexity tinggi, cukup periodic polling | v2.0 |
| Enkripsi SQLite | Butuh audit security dulu | v2.0 |
| macOS / Linux support | Tauri support, tapi testing terbatas | v2.0 |
| Multi-bahasa | I18n butuh persiapan | v3.0 |
| Dark mode | shadcn/vue support, tapi belum prioritas | v1.1 |

---

## 9. Struktur Database Lokal (SQLite)

### 9.1 Tabel Master (Sync dari Backend — read-only lokal)

```sql
-- Karyawan
CREATE TABLE employees (
    id INTEGER PRIMARY KEY,
    employee_number TEXT UNIQUE NOT NULL,
    full_name TEXT NOT NULL,
    department_id INTEGER,
    position_id TEXT,
    join_date TEXT,
    end_date TEXT,
    is_active INTEGER DEFAULT 1,
    uuid TEXT UNIQUE,
    synced_at TEXT,
    -- cached / denormalized untuk performa
    department_name TEXT,
    position_name TEXT,
    shift_group TEXT
);

-- Departemen
CREATE TABLE departments (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    parent_id INTEGER,
    uuid TEXT UNIQUE,
    synced_at TEXT
);

-- Jadwal Kerja
CREATE TABLE schedules (
    id INTEGER PRIMARY KEY,
    employee_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    shift_start TEXT,     -- HH:mm
    shift_end TEXT,       -- HH:mm
    is_holiday INTEGER DEFAULT 0,
    uuid TEXT UNIQUE,
    synced_at TEXT
);
```

### 9.2 Tabel Transaksi (Dibuat di Desktop — push ke Backend)

```sql
-- Raw logs hasil import dari file fingerprint
CREATE TABLE att_raw_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_number TEXT,
    scan_datetime TEXT NOT NULL,        -- YYYY-MM-DD HH:mm:ss
    machine_id TEXT,
    file_origin TEXT,                    -- nama file asal
    imported_at TEXT DEFAULT (datetime('now','localtime')),
    uuid TEXT UNIQUE,
    synced_at TEXT,
    sync_status TEXT DEFAULT 'pending'   -- pending/synced/failed
);

-- Log absensi hasil auto-process
CREATE TABLE att_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    check_in TEXT,                       -- HH:mm:ss
    check_out TEXT,                      -- HH:mm:ss
    total_work_minutes INTEGER,
    late_minutes INTEGER DEFAULT 0,
    early_leave_minutes INTEGER DEFAULT 0,
    overtime_minutes INTEGER DEFAULT 0,
    status TEXT DEFAULT 'present',        -- present/alpha/sick/permit/leave
    review_status TEXT DEFAULT 'pending', -- pending/approved/rejected
    review_note TEXT,
    uuid TEXT UNIQUE,
    synced_at TEXT,
    sync_status TEXT DEFAULT 'pending'
);

-- Pengajuan lembur
CREATE TABLE att_overtimes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    start_time TEXT NOT NULL,
    end_time TEXT NOT NULL,
    total_minutes INTEGER,
    reason TEXT,
    approval_status TEXT DEFAULT 'pending',  -- pending/approved/rejected
    approved_by INTEGER,
    uuid TEXT UNIQUE,
    synced_at TEXT,
    sync_status TEXT DEFAULT 'pending'
);

-- Log sinkronisasi
CREATE TABLE sync_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    table_name TEXT NOT NULL,
    record_uuid TEXT,
    action TEXT,           -- push/pull
    status TEXT,           -- success/failed
    error_message TEXT,
    created_at TEXT DEFAULT (datetime('now','localtime'))
);

-- Metadata sync
CREATE TABLE sync_meta (
    key TEXT PRIMARY KEY,
    value TEXT
);
-- isi: last_pull_at, last_push_at, last_full_sync_at
```

### 9.3 Indexes

```sql
CREATE INDEX idx_raw_logs_datetime ON att_raw_logs(scan_datetime);
CREATE INDEX idx_raw_logs_sync ON att_raw_logs(sync_status, synced_at);
CREATE INDEX idx_att_logs_employee_date ON att_logs(employee_id, date);
CREATE INDEX idx_att_logs_review ON att_logs(review_status);
CREATE INDEX idx_att_logs_sync ON att_logs(sync_status, synced_at);
CREATE INDEX idx_overtimes_employee ON att_overtimes(employee_id, date);
CREATE INDEX idx_employees_uuid ON employees(uuid);
```

---

## 10. Struktur Project Frontend

```
src/
├── main.ts                         ← Entry point
├── App.vue                         ← Root component (router-view)
├── assets/main.css                 ← Tailwind + shadcn/vue CSS
├── lib/utils.ts                    ← cn() utility
│
├── components/
│   ├── ui/                         ← shadcn/vue atomic components
│   │   ├── button/
│   │   ├── card/
│   │   ├── dialog/
│   │   ├── table/
│   │   ├── badge/
│   │   ├── input/
│   │   ├── label/
│   │   ├── select/
│   │   └── dropdown-menu/
│   └── shared/                     ← Shared antar layout
│       ├── SyncStatus.vue          ← Status sync bar
│       └── InstanceSelector.vue    ← Pilih instance
│
├── layouts/
│   ├── AdminLayout.vue             ← Sidebar + Topbar untuk Admin
│   ├── SupervisorLayout.vue        ← Sidebar + Topbar untuk Supervisor
│   ├── RootLayout.vue              ← Layout dasar (auth check)
│   └── GuestLayout.vue             ← Layout login
│
├── pages/
│   ├── Login.vue
│   │
│   ├── admin/                      ← Aplikasi Utama (Admin Dashboard)
│   │   ├── Dashboard.vue
│   │   ├── attendance/
│   │   │   ├── Index.vue           ← Tabel attendance records
│   │   │   ├── Import.vue          ← Drag-drop import
│   │   │   ├── Review.vue          ← Review + koreksi
│   │   │   └── Overtime.vue        ← Manajemen lembur
│   │   ├── master/
│   │   │   ├── Employees.vue       ← View data karyawan
│   │   │   └── Schedules.vue       ← View jadwal
│   │   └── reports/
│   │       └── Index.vue           ← Export laporan
│   │
│   └── supervisor/                 ← Aplikasi Bayangan (Supervisor)
│       ├── Dashboard.vue
│       ├── attendance/
│       │   ├── Index.vue           ← Snapshot attendance
│       │   └── Roster.vue          ← Roster view
│       ├── payroll/
│       │   └── Index.vue           ← Payroll audit (view-only)
│       └── reports/
│           └── Index.vue           ← Export laporan supervisor
│
├── services/
│   ├── api.ts                      ← HTTP client (axios/fetch) ke Laravel
│   ├── db.ts                       ← SQLite wrapper (tauri-plugin-sql)
│   ├── sync.ts                     ← Sync engine logic
│   ├── importer.ts                 ← File import parser (.csv/.xlsx)
│   └── processor.ts                ← Attendance auto-process logic
│
├── stores/                         ← Pinia stores
│   ├── auth.ts
│   ├── sync.ts
│   ├── attendance.ts
│   └── employee.ts
│
└── router/
    └── index.ts                    ← Vue Router config
```

---

## 11. API Endpoints (Dikonsumsi Desktop)

### 11.1 Auth

| Method | Endpoint | Fungsi | Sync |
|--------|----------|--------|------|
| POST | `/api/login` | Login, dapet token | — |
| POST | `/api/logout` | Logout | — |
| GET | `/api/user` | Data user login | — |

### 11.2 Pull (Data Master — dari Backend ke Desktop)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/organization/departments` | Daftar departemen |
| GET | `/api/organization/departments/options` | Opsi departemen |
| GET | `/api/organization/positions` | Daftar posisi |
| GET | `/api/organization/positions/options` | Opsi posisi |
| GET | `/api/employees` | Daftar karyawan |
| GET | `/api/employees/{id}` | Detail karyawan |
| GET | `/api/schedule/rosters?date=...` | Roster per tanggal |
| GET | `/api/schedule/calendars` | Kalender kerja |
| GET | `/api/schedule/holidays` | Hari libur |

### 11.3 Push (Dari Desktop ke Backend)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/api/attendance/raw-logs/batch` | Push raw logs hasil import |
| POST | `/api/attendance/logs/batch` | Push hasil auto-process |
| POST | `/api/attendance/logs/{id}/review` | Update review status |
| POST | `/api/attendance/overtimes` | Push pengajuan lembur |
| POST | `/api/attendance/overtimes/{id}/approve` | Approval lembur |
| GET | `/api/attendance/sync/pull?since=...` | Pull update attendance dari server |

---

## 12. Risiko & Dependensi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| **Format file tidak sesuai** | Import gagal | Support .csv/.xlsx sebagai format standar, validasi ketat sebelum proses |
| **Konflik data saat sync** | Data ganda / salah | Last-write-wins + logging, admin bisa manual resolve |
| **SQLite corrupt** | Kehilangan data lokal | Auto-backup SQLite tiap sync sukses, max 5 backup |
| **API backend berubah** | Desktop gagal sync | Versioned API (`/api/v1/`), compatibility contract |
| **Ukuran SQLite membesar** | Performa menurun | Auto-vacuum, batch delete raw logs > 3 bulan |
| **User offline terlalu lama** | Antrian push membesar | Notifikasi "N record menunggu sync", limit queue 10.000 |
| **Rust compile lama** | Development lambat | cache cargo, incremental compilation |

---

## 13. Milestone & Timeline

### Fase 1: Foundation (Minggu 1)
- Setup project Tauri + Vue 3 + shadcn/vue ✅ *(done)*
- SQLite plugin integration ✅ *(done)*
- Struktur folder & routing
- Auth flow: login + token storage
- Sync engine: pull data master

### Fase 2: Data Master & Sync (Minggu 2)
- Tabel SQLite untuk employees, departments, schedules
- Auto-pull dari backend (periodic + manual)
- UI master data viewer
- Sync queue system + retry logic

### Fase 3: Import & Auto-Process (Minggu 3-4)
- Drag-drop import fingerprint (.bin/.csv/.xlsx)
- Parser file .bin (port dari hris-system)
- Auto-process: matching scan → check-in/out
- Kalkulasi: keterlambatan, pulang awal, jam kerja
- Preview & save ke SQLite

### Fase 4: Review & Overtime (Minggu 5)
- Tabel attendance records dengan review status
- UI review & koreksi
- Batch approve/reject
- Manajemen overtime (CRUD + approval)

### Fase 5: Dashboard & Export (Minggu 6)
- Dashboard absensi (stat, grafik)
- Export Excel laporan rekap absensi
- Sync status indicator

### Fase 6: Polish & Testing (Minggu 7)
- Error handling & edge cases
- Performance tuning
- Testing offline → online scenarios
- Binary build & installer

---

## 14. Pertanyaan yang Sudah Terjawab

| # | Pertanyaan | Keputusan |
|---|-----------|-----------|
| 1 | Apakah desktop app perlu fitur **Employee CRUD**? | **TIDAK** — view-only via sync. Employee CRUD ada di aplikasi/web terpisah |
| 2 | Database SQLite: 1 DB atau 2 DB (main + shadow)? | **1 database** dengan prefiks tabel berbeda: tabel utama `att_*`, `pay_*`, dan tabel shadow `shadow_att_*`, `shadow_pay_*` |
| 3 | **Enkripsi SQLite**? | **Partial** — password/API token wajib dienkripsi via Tauri secure store. Data absensi & payroll tidak perlu diencrypt di level SQLite |
| 4 | **Code signing certificate** untuk installer? | **TIDAK** untuk v1.0. Installer akan tampilkan warning Windows standar. Opsi ini bisa dievaluasi jika distribusi meluas |
| 5 | **Auto-update** via Tauri updater? | **TIDAK** untuk v1.0. Update manual via download installer |
| 6 | **Port FingerprintBinParser** dari hris-system? | **TIDAK** — fokus ke aplikasi utama dulu. Support import via .csv/.xlsx sebagai format utama. Format .bin akan dievaluasi di versi berikutnya |

---

## 15. Perubahan dari PRD Sebelumnya

| Perubahan | Detail |
|-----------|--------|
| Struktur frontend | Dipisah: `pages/admin/` (aplikasi utama) dan `pages/supervisor/` (aplikasi bayangan) — mengikuti pola frontend Laravel |
| Fingerprint parser | Tidak di-port. Fokus import via .csv/.xlsx. Format .bin ditunda |
| Enkripsi | Hanya password/token di secure store. SQLite tidak diencrypt untuk data transaksi |
| Code signing | Tidak diperlukan untuk distribusi v1.0 |
