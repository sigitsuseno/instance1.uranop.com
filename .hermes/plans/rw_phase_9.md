# Phase 9: Reports + Web UI + Desktop

**File**: `rw_phase_9.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1-8 (semua modul data)

---

## Tujuan

Report engine yang config-driven via `report_configs` JSON.

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/Reports/
├── Models/
│   └── ReportConfig.php
├── Services/
│   └── ReportService.php
├── Controllers/
│   └── Api/
│       └── V1/
│           └── ReportController.php
└── Routes/
    └── api.php
```

---

## 2. DATABASE

### 2.1 `report_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(100) | Nama report |
| key | varchar(50) UNIQUE | attendance_daily, payroll_summary |
| module | varchar(30) | attendance, payroll, employee |
| type | enum('table','chart','summary') | |
| config | json | Konfigurasi kolom, filter, aggregasi |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `report_configs.config` JSON Structure

```json
{
  "title": "Rekap Absensi Harian",
  "description": "Laporan absensi per hari per karyawan",
  "source": {
    "model": "AttendanceAutolog",
    "scope": "byPeriod"
  },
  "filters": [
    { "key": "start_date", "type": "date", "label": "Tanggal Mulai", "required": true },
    { "key": "end_date", "type": "date", "label": "Tanggal Akhir", "required": true },
    { "key": "employee_id", "type": "select", "label": "Karyawan", "source": "employees" }
  ],
  "columns": [
    { "key": "date", "label": "Tanggal", "format": "date_id" },
    { "key": "employee_name", "label": "Nama" },
    { "key": "status", "label": "Status", "format": "attendance_status" },
    { "key": "check_in", "label": "Masuk", "format": "time" },
    { "key": "check_out", "label": "Keluar", "format": "time" },
    { "key": "lm", "label": "LM", "format": "overtime" },
    { "key": "lembur", "label": "Lembur", "format": "overtime" }
  ],
  "aggregations": [],
  "export": { "enabled": true, "format": "xlsx" },
  "print": { "enabled": true, "format": "a4_landscape" }
}
```

---

## 3. TASK LIST

### Task 1: Migration
- [ ] `report_configs`

### Task 2: Models
- [ ] `ReportConfig.php` — `$casts` (config → array)

### Task 3: Services
- [ ] `ReportService.php`
  - `getAvailable()` — list report yang aktif
  - `generate(key, filters)` — query data sesuai config
  - `export(key, filters)` — Excel
  - `print(key, filters)` — HTML/PDF

### Task 4: Controllers
- [ ] `ReportController.php` — index, generate, export, print

### Task 5: Seeder
- [ ] `ReportConfigSeeder` — 5 report default (daily, summary, payroll, employee, supervisor)

---

## 4. ENDPOINT SUMMARY

```
GET    /api/v1/reports                           # list available reports
GET    /api/v1/reports/{key}?start_date=&end_date=  # generate
GET    /api/v1/reports/{key}/export?start_date=&end_date=
GET    /api/v1/reports/{key}/print?start_date=&end_date=
```
