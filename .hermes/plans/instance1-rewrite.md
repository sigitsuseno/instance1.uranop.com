# Instance1 Rewrite — Plan v3

**Tanggal**: 2026-07-14
**Status**: Draft v3 — Web (Inertia 3) + Desktop (Tauri v2) per Phase
**Target**: `H:\laragon\www\hris-master\` (Laravel API + Inertia 3 Web + Tauri Desktop)

> ⚠️ **UI MANDATORY**: Sebelum membuat UI apapun, **WAJIB** baca `.hermes/uistyle.md`.  
> Tema: Clean Elegant Enterprise. CSS variables only. NO hardcode Tailwind colors.

---

## Tujuan

Rewrite instance1.uranop.com per-modul. Setiap phase langsung jadi: **backend API + web UI (Inertia 3) + desktop app (Tauri v2, offline-first)**. Nggak ada phase UI terpisah.

## Stack Teknologi

| Layer | Teknologi | Auth |
|-------|-----------|------|
| **Web UI** | Inertia 3 (Laravel SSR + Vue 3 components) | Session-based (cookie) |
| **REST API** | Laravel API (`Api/V1/`) | Sanctum token (Bearer) |
| **Sync API** | Laravel API (`Api/Sync/`) | Sanctum token (batch) |
| **Desktop** | Tauri v2 (Rust + WebView) | Sanctum token (localStorage) |
| **Database** | MariaDB/MySQL | — |

> **Kenapa Inertia 3**: SSR + session auth = lebih aman untuk web admin. Desktop & mobile tetap pakai REST API + Sanctum token. Vue components bisa dishare antara Inertia pages dan Tauri WebView.

## Prinsip

1. **Bottom-up** — master data dulu, engine berat belakangan
2. **Service per entity** — business logic di Service, Controller cuma I/O
3. **Cross-cutting di pondasi** — Notification, AuditLog, Instance dibikin Phase 1
4. **Supervisor nyatu** — di phase modul terkait, bukan phase terpisah
5. **Test per service** — TDD untuk business logic
6. **Web + Desktop bareng** — tiap phase: API → Web UI (Inertia) → Desktop UI (Tauri, offline-first)
7. **Offline-first desktop** — local SQLite, sync ke server saat online, queue failed sync
8. **Satu codebase Vue** — Inertia pages & Tauri WebView pakai komponen Vue yang sama, beda wrapper & auth

---

## Struktur Folder Target

```
H:\laragon\www\hris-master\
│
├── app/                              # Laravel API backend
│   ├── Helpers/                      # ResponseHelper, DateHelper, NumberHelper, ExcelHelper
│   ├── Traits/                       # HasPeriod, HasAudit
│   ├── Enums/                        # AttendanceStatus, PayrollStatus, LeaveType
│   ├── Http/Controllers/Api/V1/
│   │   └── BaseApiController.php
│   └── Modules/
│       ├── Auth/                     # Login, register, permission
│       │   ├── Controllers/
│       │   │   ├── Web/              # Inertia (session)
│       │   │   ├── Api/V1/           # REST API (web + mobile)
│       │   │   ├── Api/Sync/         # Desktop sync
│       │   │   └── Report/           # Reports export
│       │   ├── Services/
│       │   ├── Models/
│       │   └── Routes/
│       │       ├── web.php           # Inertia routes
│       │       ├── api.php           # REST routes
│       │       └── api-sync.php      # Sync routes
│       ├── Instance/                 # Multi-instance metadata (1 row per deployment)
│       ├── Dashboard/                # Dashboard stats
│       ├── Notification/             # Web push
│       ├── AuditLog/                 # Activity log
│       ├── Organization/             # Company, Branch, Department, Position
│       ├── Settings/                 # System settings, configs
│       ├── Employee/                 # Karyawan + family, documents, contracts, salary
│       ├── Schedule/                 # Work pattern, shift, roster, holiday
│       ├── Leave/                    # Leave type, policy, request, quota
│       ├── Kasbon/                   # Kasbon request, installment
│       ├── Attendance/               # Sync, Overtime, Report, Config (4 sub-modul)
│       ├── Payroll/                  # Period, calculate, THR, PPh, BPJS, export
│       ├── Reports/                  # Report engine
│       └── Supervisor/               # Group, Attendance, Payroll, THR
│
├── resources/js/                     # Inertia 3 — Web Frontend (SSR via Laravel)
│   ├── Pages/                        # Inertia page components (1:1 dgn route)
│   │   ├── Auth/                     # LoginPage.vue
│   │   ├── Dashboard/                # DashboardPage.vue
│   │   ├── Organization/             # CompanyPage, BranchPage, etc
│   │   └── ...                       # Satu folder per modul
│   ├── Components/                   # Shared Vue components (Inertia + Tauri)
│   ├── Layouts/                      # AppLayout.vue, AuthLayout.vue
│   ├── Composables/                  # useApi, useAuth, useNotification
│   ├── Stores/                       # Pinia stores (shared state)
│   ├── app.js                        # Inertia app entry (createInertiaApp)
│   └── App.vue                       # Root Inertia layout
│
├── src-tauri/                        # Tauri v2 — Desktop App
│   ├── Cargo.toml
│   ├── tauri.conf.json
│   ├── capabilities/
│   └── src/
│       ├── main.rs                   # Tauri entry
│       ├── lib.rs                    # Commands (API calls + local DB)
│       ├── db.rs                     # Local SQLite (offline storage)
│       └── sync.rs                   # Sync engine (queue → server)
│
├── desktop/                          # Desktop-specific Vue entry
│   ├── index.html                    # Entry HTML for Tauri
│   └── main.js                       # Desktop-specific bootstrap (local DB init)
│
├── shared/                           # Shared types & constants
│   └── types/                        # TypeScript interfaces (optional)
│
├── database/migrations/
├── routes/
│   ├── api.php                       # Main API routes + module auto-load
│   └── web.php                       # Inertia routes + module web routes
├── tests/
├── composer.json
├── package.json
└── vite.config.js
```

> **Offline-first flow**: Desktop app baca/tulis ke local SQLite dulu.  
> Background sync worker kirim perubahan ke server via API.  
> Conflict resolution: server wins untuk data master, timestamp-based merge untuk attendance.

---

## Module Map

```
Phase 1 ──► Auth, Instance, Dashboard, Notification, AuditLog  [PONDASI + WEB UI + DESKTOP SCAFFOLD]
Phase 2 ──► Organization, Settings, RBAC                       [KONFIG]
Phase 3 ──► Employee + Supervisor EmployeeGroup                [DATA MASTER]
Phase 4 ──► Schedule (WorkPattern, Shift, Roster, Holiday)     [DATA MASTER]
Phase 5 ──► Leave (Type, Policy, Request, Quota)               [OPERASIONAL]
Phase 6 ──► Kasbon (Request, Installment)                      [OPERASIONAL]
Phase 7 ──► Attendance (Sync, Overtime, Report) + Supervisor   [ENGINE + OFFLINE SYNC]
Phase 8 ──► Payroll (Calculate, THR, PPh) + Supervisor         [ENGINE]
Phase 9 ──► Reports                                            [OUTPUT]
```

> Setiap phase output-nya: **API endpoints + Web UI pages + Desktop UI + Tests**.  
> Desktop offline sync mulai aktif di Phase 7 (Attendance) karena paling butuh offline mode.

---

## Database Rename Mapping

### Prefix Normalization

| Current | Rename |
|---------|--------|
| `att_consecutive_days` | `attendance_consecutive_days` |
| `att_manual_detect` | `attendance_manual_detects` |
| `att_prepares` | `attendance_prepares` |
| `att_records` | `attendance_records` |
| `att_raw_logs` | `attendance_raw_logs` |
| `attendance_calculator_configs` | OK |
| `attendance_configs` | OK |

### Schedule

| Current | Rename |
|---------|--------|
| `sch_employee_shift_rosters` | `schedule_employee_rosters` |
| `sch_holidays` | `schedule_holidays` |
| `sch_shifts` | `schedule_shifts` |
| `sch_work_patterns` | `schedule_work_patterns` |
| `sch_work_pattern_details` | `schedule_work_pattern_details` |
| `sch_work_pattern_types` | `schedule_work_pattern_types` |
| `sch_working_calendars` | `schedule_working_calendars` |

### Supervisor

| Current | Action |
|---------|--------|
| `supervisor_payrolls` | **HAPUS** (0 rows) |
| `supervisor_attendances` | **HAPUS** (cek dulu) |
| `supervisor_att_snapshot` | `supervisor_attendance_snapshots` |
| `supervisor_breakdowns` | `supervisor_payroll_breakdowns` |
| `supervisor_employee_groups` | OK |

### Payroll

| Current | Rename |
|---------|--------|
| `pay_periods` | `payroll_periods` |
| `pay_records` | `payroll_records` |
| `payroll_configs` | OK |

---

## Response Format Standard

```json
// Success
{ "success": true, "data": {...}, "message": "..." }

// Paginated
{ "success": true, "data": [...], "meta": { "current_page":1, "last_page":5, "per_page":25, "total":112 } }

// Error
{ "success": false, "message": "...", "errors": { "field": ["..."] } }

// 401
{ "success": false, "message": "Unauthenticated" }
```

---

## ═══════════════════════════════════════
## PHASE 1: Pondasi — Auth, Instance, Dashboard, Notification, AuditLog
## ═══════════════════════════════════════

**Kenapa Phase 1**: Semua modul lain butuh login, tenant scoping, notifikasi, dan activity log.

### Struktur Module

```
app/Modules/
├── Auth/
│   ├── Models/User.php
│   ├── Services/AuthService.php
│   ├── Controllers/Api/V1/
│   │   ├── AuthController.php        # login, logout, me
│   │   └── PermissionController.php  # roles, permissions
│   └── Routes/api.php
│
├── Instance/
│   ├── Models/Instance.php
│   └── Routes/api.php
│
├── Dashboard/
│   ├── Services/DashboardService.php
│   ├── Controllers/Api/V1/DashboardController.php
│   └── Routes/api.php
│
├── Notification/
│   ├── Models/Notification.php
│   ├── Services/NotificationService.php
│   └── Routes/api.php
│
└── AuditLog/
    ├── Models/AuditLog.php
    ├── Traits/HasAudit.php           # auto-record create/update/delete
    └── Routes/api.php
```

### Tasks

1. Scaffold Laravel 12 + Sanctum + Spatie Permission
2. Bikin `BaseApiController` (`success()`, `error()`, `paginated()`)
3. Bikin `Helpers\`: ResponseHelper, DateHelper, NumberHelper, ExcelHelper
4. Bikin `Traits\\`: HasPeriod, HasAudit
5. Bikin `Enums\`
6. Setup migration fresh (tabel rename mapping)
7. Setup phpunit + fix collision → `php artisan test` jalan
8. Setup CI (GitHub Actions): lint + test

### Auth Endpoints

```
POST   /api/v1/auth/login          # email + password → token
POST   /api/v1/auth/logout         # revoke token
GET    /api/v1/auth/me             # current user + permissions
GET    /api/v1/auth/permissions    # list semua permission
GET    /api/v1/auth/roles          # list semua role
```

### Dashboard Endpoints

```
GET    /api/v1/dashboard/stats     # total employee, attendance today, etc
```

### Test

- AuthService: login success, login fail, token expiration
- BaseApiController response format

---

## ═══════════════════════════════════════
## PHASE 2: Organization, Settings & RBAC
## ═══════════════════════════════════════

**Kenapa Phase 2**: Company/Branch/Department dibutuhkan Employee (Phase 3). Settings dibutuhkan semua modul.

### Struktur Module

```
app/Modules/
├── Organization/
│   ├── Models/
│   │   ├── Company.php
│   │   ├── Branch.php
│   │   ├── Department.php
│   │   └── Position.php
│   ├── Services/OrganizationService.php
│   ├── Controllers/Api/V1/
│   │   ├── CompanyController.php
│   │   ├── BranchController.php
│   │   ├── DepartmentController.php
│   │   └── PositionController.php
│   └── Routes/api.php
│
└── Settings/
    ├── Models/
    │   ├── SystemSetting.php
    │   ├── PayrollConfig.php
    │   ├── AttendanceConfig.php
    │   ├── ThrConfig.php
    │   ├── BpjsConfig.php
    │   └── PphConfig.php
    ├── Services/SettingsService.php
    ├── Controllers/Api/V1/
    │   ├── SystemSettingController.php
    │   ├── PayrollConfigController.php
    │   └── AttendanceConfigController.php
    └── Routes/api.php
```

### Endpoints

```
# Organization
GET    /api/v1/companies
POST   /api/v1/companies
PUT    /api/v1/companies/{id}
GET    /api/v1/branches
POST   /api/v1/branches
PUT    /api/v1/branches/{id}
GET    /api/v1/departments
POST   /api/v1/departments
PUT    /api/v1/departments/{id}
GET    /api/v1/positions
POST   /api/v1/positions
PUT    /api/v1/positions/{id}

# Settings
GET    /api/v1/settings
PUT    /api/v1/settings
GET    /api/v1/settings/payroll
PUT    /api/v1/settings/payroll
GET    /api/v1/settings/attendance
PUT    /api/v1/settings/attendance
```

### RBAC Matrix

| Role | Company | Branch | Dept | Settings |
|------|---------|-------|------|----------|
| Superadmin | CRUD | CRUD | CRUD | CRUD |
| Admin | Read | CRUD | CRUD | Read |
| Supervisor | Read | Read | Read | — |

---

## ═══════════════════════════════════════
## PHASE 3: Employee + Supervisor EmployeeGroup
## ═══════════════════════════════════════

**Kenapa Phase 3**: Data master karyawan — semua modul attendance & payroll butuh employee.

### Struktur Module

```
app/Modules/Employee/
├── Models/
│   ├── Employee.php
│   ├── EmployeeBpjs.php
│   ├── EmployeeContract.php
│   ├── EmployeeDocument.php
│   ├── EmployeeFamily.php
│   ├── EmployeePositionHistory.php
│   ├── EmployeeSalary.php
│   ├── EmployeeSalaryComponent.php
│   └── EmployeeTermination.php
├── Services/
│   ├── EmployeeService.php           # CRUD + search + filter
│   ├── EmployeeSalaryService.php     # salary component management
│   └── EmployeeContractService.php   # contract lifecycle
├── Controllers/Api/V1/
│   ├── EmployeeController.php
│   ├── EmployeeSalaryController.php
│   ├── EmployeeContractController.php
│   ├── EmployeeDocumentController.php
│   ├── EmployeeFamilyController.php
│   └── EmployeeTerminationController.php
├── Exports/
│   └── EmployeeExport.php
└── Routes/api.php

app/Modules/Supervisor/
├── EmployeeGroup/
│   ├── Models/SupervisorEmployeeGroup.php
│   ├── Services/EmployeeGroupService.php
│   ├── Controllers/Api/V1/EmployeeGroupController.php
│   └── Routes/api.php
```

### Endpoints

```
# Employee
GET    /api/v1/employees                  # list + search + filter
POST   /api/v1/employees                  # create
GET    /api/v1/employees/{id}             # detail
PUT    /api/v1/employees/{id}             # update
DELETE /api/v1/employees/{id}             # soft delete

# Employee Sub-resources
GET    /api/v1/employees/{id}/salaries
POST   /api/v1/employees/{id}/salaries
GET    /api/v1/employees/{id}/contracts
POST   /api/v1/employees/{id}/contracts
GET    /api/v1/employees/{id}/documents
POST   /api/v1/employees/{id}/documents
GET    /api/v1/employees/{id}/families
POST   /api/v1/employees/{id}/families
GET    /api/v1/employees/{id}/terminations
POST   /api/v1/employees/{id}/terminations

# Export
GET    /api/v1/employees/export

# Supervisor — Employee Group
GET    /api/v1/supervisor/employee-groups
POST   /api/v1/supervisor/employee-groups
POST   /api/v1/supervisor/employee-groups/import     # import Excel
GET    /api/v1/supervisor/employee-groups/template    # download template
```

### Test

- EmployeeService: CRUD, search, filter by branch/department
- EmployeeGroupService: assign/unassign, duplicate prevention
- Feature: import Excel validation

---

## ═══════════════════════════════════════
## PHASE 4: Schedule Engine
## ═══════════════════════════════════════

**Kenapa Phase 4**: Attendance (Phase 7) butuh roster & shift untuk deteksi check_in/check_out.

### Struktur Module

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
│   ├── WorkPatternService.php     # CRUD + jam kerja calculation
│   ├── ShiftService.php           # shift management
│   ├── RosterService.php          # assign roster + conflict detection
│   └── HolidayService.php         # holiday management
├── Controllers/Api/V1/
│   ├── WorkPatternController.php
│   ├── ShiftController.php
│   ├── RosterController.php
│   └── HolidayController.php
└── Routes/api.php
```

### Endpoints

```
# Work Pattern
GET    /api/v1/work-patterns
POST   /api/v1/work-patterns
GET    /api/v1/work-patterns/{id}
PUT    /api/v1/work-patterns/{id}
DELETE /api/v1/work-patterns/{id}

# Shift
GET    /api/v1/shifts
POST   /api/v1/shifts
PUT    /api/v1/shifts/{id}
DELETE /api/v1/shifts/{id}

# Roster
GET    /api/v1/rosters                    # list by date range + employee
POST   /api/v1/rosters/assign             # assign roster ke employee
POST   /api/v1/rosters/bulk               # bulk assign
DELETE /api/v1/rosters/{id}

# Holiday
GET    /api/v1/holidays
POST   /api/v1/holidays
PUT    /api/v1/holidays/{id}
DELETE /api/v1/holidays/{id}
```

### Business Rules

```
- Work pattern: jam kerja per hari (work_day_hours, half_day_hours)
- Rest hours: wd_rest_hours, hd_rest_hours
- Sun overtime flag: apakah Minggu dihitung lembur
- Half day flag: apakah Sabtu half day
- Roster: employee + date → shift → work pattern
- Conflict detection: 1 employee tidak boleh 2 roster di tanggal sama
```

### Test

- WorkPatternService: jam kerja calculation (full day vs half day)
- RosterService: conflict detection, bulk assign
- Holiday detection (tanggal merah)

---

## ═══════════════════════════════════════
## PHASE 5: Leave
## ═══════════════════════════════════════

### Struktur Module

```
app/Modules/Leave/
├── Models/
│   ├── LeaveType.php
│   ├── LeavePolicy.php
│   ├── LeavePeriod.php
│   ├── LeaveRequest.php
│   ├── EmployeeLeave.php             # quota per employee
│   ├── LeaveDocument.php
│   └── LeaveChangeRequest.php
├── Services/
│   ├── LeaveTypeService.php
│   ├── LeaveRequestService.php       # submit, approve, reject
│   ├── LeaveQuotaService.php         # quota calculation
│   └── LeavePolicyService.php
├── Controllers/Api/V1/
│   ├── LeaveTypeController.php
│   ├── LeaveRequestController.php
│   └── LeaveQuotaController.php
└── Routes/api.php
```

### Endpoints

```
GET    /api/v1/leaves/types
POST   /api/v1/leaves/types
GET    /api/v1/leaves/policies
POST   /api/v1/leaves/policies
GET    /api/v1/leaves/requests
POST   /api/v1/leaves/requests
PUT    /api/v1/leaves/requests/{id}/approve
PUT    /api/v1/leaves/requests/{id}/reject
GET    /api/v1/leaves/quotas                    # per employee
GET    /api/v1/leaves/periods
```

---

## ═══════════════════════════════════════
## PHASE 6: Kasbon
## ═══════════════════════════════════════

### Struktur Module

```
app/Modules/Kasbon/
├── Models/
│   ├── KasbonRequest.php
│   └── KasbonInstallment.php
├── Services/
│   ├── KasbonRequestService.php
│   └── KasbonInstallmentService.php    # schedule generation
├── Controllers/Api/V1/
│   ├── KasbonRequestController.php
│   └── KasbonInstallmentController.php
└── Routes/api.php
```

### Endpoints

```
GET    /api/v1/kasbon/requests
POST   /api/v1/kasbon/requests
PUT    /api/v1/kasbon/requests/{id}/approve
PUT    /api/v1/kasbon/requests/{id}/reject
GET    /api/v1/kasbon/installments
PUT    /api/v1/kasbon/installments/{id}/pay
```

---

## ═══════════════════════════════════════
## PHASE 7: Attendance Engine + Supervisor Attendance ⚡
## ═══════════════════════════════════════

**Kenapa Phase 7**: Module paling buggy di existing. Ditulis ulang total.

### Struktur Module — 4 Sub-Modul

```
app/Modules/Attendance/
├── Sync/                              # Raw logs → autologs + manual detect
│   ├── Models/
│   │   ├── AttendanceRawLog.php
│   │   ├── AttendanceAutolog.php
│   │   ├── AttendanceManualDetect.php
│   │   ├── AttendancePrepare.php
│   │   └── AttendanceRecord.php
│   ├── Services/
│   │   ├── RawLogImportService.php    # import Excel raw logs
│   │   ├── AutologSyncService.php     # raw_logs → autologs
│   │   └── ManualDetectService.php    # auto-detect + correction + push-prepare
│   ├── Controllers/Api/V1/
│   │   ├── RawLogController.php
│   │   ├── AutologController.php
│   │   └── ManualDetectController.php
│   └── Routes/api.php
│
├── Overtime/                          # LM, lembur, rules
│   ├── Models/
│   │   ├── OvertimeRule.php
│   │   └── OvertimeRuleDetail.php
│   ├── Services/
│   │   └── OvertimeCalculatorService.php
│   ├── Controllers/Api/V1/
│   │   └── OvertimeRuleController.php
│   └── Routes/api.php
│
├── Report/                            # Rekap, daily grid, consecutive days
│   ├── Models/
│   │   └── AttendanceConsecutiveDay.php
│   ├── Services/
│   │   └── AttendanceReportService.php
│   ├── Controllers/Api/V1/
│   │   └── AttendanceReportController.php
│   └── Routes/api.php
│
└── Config/                            # Config + calculator config
    ├── Models/
    │   ├── AttendanceConfig.php
    │   └── AttendanceCalculatorConfig.php
    ├── Controllers/Api/V1/
    │   └── AttendanceConfigController.php
    └── Routes/api.php
```

### Supervisor Attendance

```
app/Modules/Supervisor/
└── Attendance/
    ├── Models/
    │   └── SupervisorAttendanceSnapshot.php
    ├── Services/
    │   ├── AttendanceSnapshotService.php
    │   └── SupervisorRecapService.php
    ├── Controllers/Api/V1/
    │   ├── AttendanceSnapshotController.php
    │   └── RecapController.php
    └── Routes/api.php
```

### Endpoints — Sync

```
# Raw Logs
GET    /api/v1/attendance/raw-logs
POST   /api/v1/attendance/raw-logs/import       # upload Excel

# Autologs
GET    /api/v1/attendance/autologs              # list by date range
POST   /api/v1/attendance/autologs/sync         # trigger sync dari raw_logs

# Manual Detect
GET    /api/v1/attendance/manual-detect         # fetch + auto-detect
POST   /api/v1/attendance/manual-detect/save    # koreksi (single/all)
POST   /api/v1/attendance/manual-detect/push-prepare  # → att_prepares
```

### Endpoints — Overtime

```
GET    /api/v1/attendance/overtime-rules
POST   /api/v1/attendance/overtime-rules
PUT    /api/v1/attendance/overtime-rules/{id}
```

### Endpoints — Report

```
GET    /api/v1/attendance/reports/daily         # daily grid
GET    /api/v1/attendance/reports/summary       # summary per employee
GET    /api/v1/attendance/reports/consecutive   # consecutive days
GET    /api/v1/attendance/reports/export
GET    /api/v1/attendance/reports/print
```

### Endpoints — Supervisor

```
GET    /api/v1/supervisor/attendance            # recap per group
POST   /api/v1/supervisor/attendance/snapshot   # generate snapshot
GET    /api/v1/supervisor/attendance/recap      # recap table
GET    /api/v1/supervisor/attendance/export
GET    /api/v1/supervisor/attendance/print
```

### Business Rules (dari existing, di-porting 1:1)

```
- LM (Lembur Minggu): overtime hari Minggu & holiday
  → field: lm (MENIT), lm_calc (JAM)
- Lembur: overtime Senin-Sabtu
  → field: lembur (MENIT), lembur_calc (JAM)
- Present: check_in ATAU check_out ada → 'hadir'
- Absent: keduanya null → 'absent'
- Status mapping: S=Sakit, I=Izin, H=Hadir, C=Cuti, L=Libur, O=Off, - =Absen
- Overtime via autologs: Sunday/holiday → lm, weekday → lembur
  → JANGAN dijumlahkan — satu hari hanya pakai salah satu
- Manual detect auto-detect: ambil scan pertama & terakhir dari raw_logs
- pushPrepare: upsert → att_prepares, unique key (employee_id, date)
- Snapshot: sum apa adanya, JANGAN konversi
  → lm = sum(lm) menit, lm_count = sum(lm_calc) jam
  → lembur = sum(lembur) menit, lembur_count = sum(lembur_calc) jam
- Consecutive days: deteksi hari kerja berturut-turut tanpa libur
- Review status: lengkap, perhatian, cek
```

### Test — PRIORITAS TINGGI

- OvertimeCalculator: Sunday detection, holiday detection, LM vs lembur
- AutologSyncService: sync flow, edge case lintas bulan
- ManualDetectService: auto-detect logic, save correction, push-prepare
- AttendanceReportService: daily grid generation, status mapping

---

## ═══════════════════════════════════════
## PHASE 8: Payroll Engine + Supervisor Payroll ⚡
## ═══════════════════════════════════════

### Struktur Module

```
app/Modules/Payroll/
├── Models/
│   ├── PayrollPeriod.php
│   ├── PayrollRecord.php
│   ├── PayrollConfig.php
│   ├── PphConfig.php
│   ├── BpjsConfig.php
│   ├── ThrConfig.php
│   ├── TerRate.php
│   ├── PtkpRate.php
│   ├── ProgressiveRate.php
│   ├── ServiceYearAllowance.php
│   ├── SalaryGrade.php
│   └── SalaryGradeHistory.php
├── Services/
│   ├── PayrollCalculatorService.php    # kalkulasi utama
│   ├── OvertimePayService.php          # jam lembur → rupiah
│   ├── ThrCalculatorService.php        # kalkulasi THR
│   ├── PphCalculatorService.php        # PPh 21 (TER / progressive)
│   ├── BpjsCalculatorService.php       # BPJS TK, KS, Pensiun
│   ├── PayslipService.php              # generate slip (print + export)
│   └── SplitPeriodService.php          # handle periode split (25-24)
├── Controllers/Api/V1/
│   ├── PayrollPeriodController.php
│   ├── PayrollController.php
│   ├── ThrController.php
│   ├── PayslipController.php
│   └── PayrollExportController.php
├── Exports/
│   ├── PayrollExport.php
│   └── PayslipExport.php
└── Routes/api.php
```

### Supervisor Payroll

```
app/Modules/Supervisor/
└── Payroll/
    ├── Models/
    │   └── SupervisorPayrollBreakdown.php
    ├── Services/
    │   ├── BreakdownCalculatorService.php
    │   ├── CsvImportService.php
    │   └── SupervisorPayslipService.php
    ├── Controllers/Api/V1/
    │   ├── BreakdownController.php
    │   ├── PayslipController.php
    │   └── PayrollExportController.php
    ├── Exports/
    │   └── SupervisorPayrollExport.php
    └── Routes/api.php
```

### Endpoints — Admin

```
# Period
GET    /api/v1/payroll/periods
POST   /api/v1/payroll/periods                # single
POST   /api/v1/payroll/periods/generate        # bulk 12 bulan

# Calculate
POST   /api/v1/payroll/calculate               # trigger per period
GET    /api/v1/payroll/records                  # list results
GET    /api/v1/payroll/records/{id}

# THR
POST   /api/v1/payroll/thr/generate
GET    /api/v1/payroll/thr

# Export & Print
GET    /api/v1/payroll/export
GET    /api/v1/payroll/print
GET    /api/v1/payroll/payslip/{id}
```

### Endpoints — Supervisor

```
GET    /api/v1/supervisor/payroll               # breakdown list
POST   /api/v1/supervisor/payroll/calculate     # kalkulasi dari snapshot
POST   /api/v1/supervisor/payroll/import        # update dari CSV lokal
GET    /api/v1/supervisor/payroll/export
GET    /api/v1/supervisor/payroll/print
GET    /api/v1/supervisor/payroll/slip          # slip gaji
PUT    /api/v1/supervisor/payroll/slip/{id}     # update cashbon + notes
```

### Business Rules (dari existing, 1:1)

```
- Gaji per hari = gapok / fixedDays (25)
- Upah lembur = ceil(((gapok + tjMK + tunjangan) / 173) * totalLemburJam / 100) * 100
- Premi hadir = round((premi / fixedDays) * HK, 2)
- Gaji kotor = gaji + tjMK + upahLembur + revisi + premiHadir + tunjangan
- PBLT = ceil(gajiKotor - totalPotongan / 100) * 100 - (gajiKotor - totalPotongan)
- Gaji bersih = gajiKotor - totalPotongan + PBLT
- Potongan = bpjsTk + bpjsKs + bpjsPen + pph + cashbon + pot_kehadiran

- Split A (Part 1, tgl 25-31): BPJS = 0, revisi = -TMK
- Split B (Part 2, tgl 1-24): normal penuh

- GRP-ALLIN & section A (except GRP-SPR): upahLembur = 0
- GRP-SPR: upah lembur hanya dari lm_count

- THR: >=12 bln = full (gapok + tunjangan + tjMK)
       <12 bln = prorate ((basis/12)/30 × hari kerja)
- Basis THR = gapok + tunjangan + tjMK (premi TIDAK masuk)

- BPJS dari employee.bpjs (TK, KS, Pensiun)
- PPh: TER rate (monthly) atau progressive (yearly)
- TJ Masa Kerja: lookup via ServiceYearAllowance

- LM & lembur dari attendance_autologs langsung (bukan snapshot) untuk split period
- HK = hkSegment dari config
- Zero overtime: kelompok ALLIN & section A non-SPR
```

### Test — PRIORITAS TINGGI

- PayrollCalculator: full formula, split period, edge cases
- ThrCalculator: full month vs prorate, basis calculation
- PphCalculator: TER rate lookup, progressive calculation
- BpjsCalculator: split period BPJS = 0
- OvertimePayService: jam → rupiah conversion
- PayslipService: slip generation, print layout

---

## ═══════════════════════════════════════
## PHASE 9: Reports
## ═══════════════════════════════════════

### Struktur Module

```
app/Modules/Reports/
├── Models/
│   └── ReportConfig.php
├── Services/
│   └── ReportService.php             # report engine (pakai ReportConfig JSON)
├── Controllers/Api/V1/
│   └── ReportController.php
└── Routes/api.php
```

### Endpoints

```
GET    /api/v1/reports                     # list available reports
GET    /api/v1/reports/{key}               # generate report
GET    /api/v1/reports/{key}/export        # export Excel
GET    /api/v1/reports/{key}/print         # print PDF
```

---

## ═══════════════════════════════════════
## PHASE 10: Desktop App — Tauri v2
## ═══════════════════════════════════════

### Stack

- Tauri v2 (Rust backend)
- Vue 3 + Pinia + Vue Router
- Sanctum token auth (dari localStorage)

### Struktur

```
desktop/
├── src-tauri/
│   ├── Cargo.toml
│   ├── tauri.conf.json
│   └── src/
│       └── main.rs
├── src/
│   ├── composables/
│   │   ├── useApi.js           # fetch + Bearer token
│   │   └── useAuth.js          # login state, permission check
│   ├── stores/
│   │   ├── auth.js
│   │   ├── employee.js
│   │   ├── attendance.js
│   │   └── payroll.js
│   ├── views/
│   │   ├── Auth/Login.vue
│   │   ├── Dashboard/
│   │   ├── Employee/
│   │   ├── Attendance/
│   │   ├── Payroll/
│   │   ├── Supervisor/
│   │   ├── Schedule/
│   │   ├── Leave/
│   │   ├── Kasbon/
│   │   ├── Reports/
│   │   └── Settings/
│   ├── components/
│   │   ├── BaseButton.vue
│   │   ├── BaseModal.vue
│   │   ├── DataTable.vue
│   │   ├── SearchInput.vue
│   │   └── Sidebar.vue
│   ├── router/
│   │   └── index.js
│   └── App.vue
├── package.json
├── vite.config.js
└── index.html
```

### Tasks — 10a: Scaffolding + Auth

1. Tauri init + Vue 3 setup + Vite
2. Auth flow (login → token → localStorage → useApi interceptor)
3. 401 redirect (auto logout + redirect ke /login)
4. Sidebar navigation + permission-based visibility
5. Base components: BaseButton, BaseModal, DataTable, SearchInput

### Tasks — 10b: Port Views

Porting dari existing Vue SPA, consume API v2:
- Employee views
- Schedule views
- Attendance views (sync, manual detect, report)
- Payroll views (period, calculate, slip, THR)
- Supervisor views (group, attendance, payroll)
- Leave & Kasbon views
- Settings views
- Reports views

### Tasks — 10c: Desktop Features

- File system access (buka/simpan Excel langsung)
- Print to PDF via native dialog
- Auto-updater (Tauri updater)
- Tray icon (opsional)

---

## ═══════════════════════════════════════
## APPENDIX: Supervisor Module Map
## ═══════════════════════════════════════

Supervisor **tidak punya phase sendiri**. Fiturnya nyebar di phase admin terkait:

| Fitur Supervisor | Phase | Bareng |
|-----------------|-------|--------|
| Employee Group | Phase 3 | Employee |
| Attendance Snapshot | Phase 7 | Attendance |
| Attendance Recap | Phase 7 | Attendance |
| Payroll Breakdown | Phase 8 | Payroll |
| Payslip | Phase 8 | Payroll |
| THR | Phase 8 | Payroll |
| CSV Import | Phase 8 | Payroll |

---

## ═══════════════════════════════════════
## APPENDIX: Cross-Cutting Modules
## ═══════════════════════════════════════

### Notification (Phase 1)

Dibikin di pondasi, dipakai semua modul:

```php
// Di service mana pun:
app(NotificationService::class)->send(
    user: $user,
    title: 'Pengajuan Cuti Disetujui',
    body: 'Cuti tanggal 25-26 Juli telah disetujui',
    data: ['type' => 'leave_approved', 'leave_id' => $leave->id]
);
```

### AuditLog (Phase 1)

Dibikin di pondasi, auto-record via trait:

```php
class Employee extends Model
{
    use HasAudit;  // auto-log create, update, delete
}
```

### Instance (Phase 1)

Multi-tenant scoping, opt-in:

```php
// Default: tanpa scope
// Multi-instance: 1 deployment = 1 DB, no scoping needed
Employee::where('is_active', 1)->get();  // all employees in this deployment
```
