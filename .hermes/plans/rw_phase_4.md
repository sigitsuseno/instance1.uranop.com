# Phase 4: Schedule Engine + Web UI + Desktop

**File**: `rw_phase_4.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1 (auth) + Phase 2 (organization) + Phase 3 (employee)

---

## Tujuan

CRUD Work Pattern, Shift, Roster, Holiday — pondasi untuk attendance engine.

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/Schedule/
├── Models/
│   ├── ScheduleWorkPattern.php
│   ├── ScheduleWorkPatternDetail.php
│   ├── ScheduleWorkPatternType.php
│   ├── ScheduleShift.php
│   ├── ScheduleEmployeeRoster.php
│   ├── ScheduleHoliday.php
│   └── ScheduleWorkingCalendar.php
├── Services/
│   ├── WorkPatternService.php
│   ├── ShiftService.php
│   ├── RosterService.php
│   └── HolidayService.php
├── Controllers/
│   └── Api/
│       └── V1/
│           ├── WorkPatternController.php
│           ├── ShiftController.php
│           ├── RosterController.php
│           └── HolidayController.php
└── Routes/
    └── api.php
```

---

## 2. DATABASE

### 2.1 `schedule_work_patterns` (rename from `sch_work_patterns`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(100) | Nama pola (contoh: "Shift Pagi 5 Hari") |
| code | varchar(20) | |
| work_day_hours | int default 8 | Jam kerja per hari normal |
| half_day_hours | int default 4 | Jam kerja half day (Sabtu) |
| wd_rest_hours | int default 1 | Jam istirahat weekday |
| hd_rest_hours | int default 0 | Jam istirahat half day |
| sun_overtime | tinyint(1) default 0 | Minggu dihitung lembur? |
| is_half_day_all | tinyint(1) default 0 | Semua hari half day? |
| type_id | bigint (FK→sch_work_pattern_types) | |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `schedule_work_pattern_details` (rename from `sch_work_pattern_details`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| work_pattern_id | bigint (FK) | |
| day_of_week | int | 0=Minggu, 6=Sabtu |
| start_time | time | |
| end_time | time | |
| is_work_day | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.3 `schedule_work_pattern_types` (rename from `sch_work_pattern_types`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(50) | PL, OS, SC, GD, UMUM |
| description | varchar(200) nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.4 `schedule_shifts` (rename from `sch_shifts`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(50) | Pagi, Siang, Malam |
| code | varchar(10) | P, S, ML |
| start_time | time | |
| end_time | time | |
| crossing_midnight | tinyint(1) default 0 | Melewati tengah malam? |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.5 `schedule_employee_rosters` (rename from `sch_employee_shift_rosters`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| date | date | |
| shift_id | bigint (FK→schedule_shifts) | |
| work_pattern_id | bigint (FK→schedule_work_patterns) | |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(employee_id, date)`

### 2.6 `schedule_holidays` (rename from `sch_holidays`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(100) | |
| date | date | |
| type | enum('national','company') | |
| is_recurring | tinyint(1) default 0 | Berulang tiap tahun? |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.7 `schedule_working_calendars` (rename from `sch_working_calendars`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| year | int | |
| month | int | |
| total_working_days | int | |
| data | json | Detail per tanggal |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 3. YANG HARUS DIPERBAIKI

### 3.1 Nama Tabel — Prefix `sch_` → `schedule_`

Semua tabel schedule rename.
Migration pakai `RENAME TABLE` (bukan drop+create).

### 3.2 Roster Conflict Detection

**Existing**: Tidak ada validasi — 1 employee bisa punya 2 roster di tanggal sama.

**Perbaikan**: Unique constraint + validasi di `RosterService::assign()`.

### 3.3 Work Pattern Type

**Existing**: `sch_work_pattern_types` berisi kategori WP: PL (produksi 2-shift), OS (pagi semua), SC (satpam 3-shift).

**Perbaikan**: Bikin enum `WorkPatternType` untuk mapping: PL, OS, OPS, OP, OS2, UMUM, GD, SC.

---

## 4. TASK LIST

### Task 1: Migration
- [ ] Rename semua tabel `sch_*` → `schedule_*`
- [ ] Verifikasi FK constraint setelah rename

### Task 2: Models
- [ ] `ScheduleWorkPattern.php` — `$fillable`, `$casts`, `details()`, `type()`
- [ ] `ScheduleWorkPatternDetail.php`
- [ ] `ScheduleWorkPatternType.php`
- [ ] `ScheduleShift.php`
- [ ] `ScheduleEmployeeRoster.php`
- [ ] `ScheduleHoliday.php`
- [ ] `ScheduleWorkingCalendar.php`

### Task 3: Services
- [ ] `WorkPatternService.php` — CRUD + jam kerja calculation + detail per hari
- [ ] `ShiftService.php` — CRUD + crossing midnight detection
- [ ] `RosterService.php` — assign (single + bulk), conflict detection, getByDateRange
- [ ] `HolidayService.php` — CRUD + isHoliday(date), recurring year rollover

### Task 4: Controllers
- [ ] `WorkPatternController.php`
- [ ] `ShiftController.php`
- [ ] `RosterController.php`
- [ ] `HolidayController.php`

### Task 5: Test
- [ ] `WorkPatternServiceTest.php` — jam kerja normal vs half day
- [ ] `RosterServiceTest.php` — conflict detection, bulk assign

---

## 5. ENDPOINT SUMMARY

```
GET    /api/v1/work-patterns
POST   /api/v1/work-patterns
GET    /api/v1/work-patterns/{id}
PUT    /api/v1/work-patterns/{id}
DELETE /api/v1/work-patterns/{id}

GET    /api/v1/shifts
POST   /api/v1/shifts
PUT    /api/v1/shifts/{id}
DELETE /api/v1/shifts/{id}

GET    /api/v1/rosters?start_date=&end_date=&employee_id=
POST   /api/v1/rosters/assign
POST   /api/v1/rosters/bulk

GET    /api/v1/holidays?year=
POST   /api/v1/holidays
PUT    /api/v1/holidays/{id}
DELETE /api/v1/holidays/{id}
```
