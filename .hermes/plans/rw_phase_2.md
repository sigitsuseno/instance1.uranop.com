# Phase 2: Organization, Settings & RBAC + Web UI + Desktop

**File**: `rw_phase_2.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1 selesai (auth, helpers, traits, Inertia web, Tauri scaffold)

---

## Tujuan

1. CRUD Company, Branch, Department, Position
2. Settings page (system settings, payroll config, attendance config)
3. RBAC matrix: role-based access per endpoint

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/
├── Organization/
│   ├── Models/
│   │   ├── Company.php
│   │   ├── Branch.php
│   │   ├── Department.php
│   │   └── Position.php
│   ├── Services/
│   │   └── OrganizationService.php
│   ├── Controllers/
│   │   ├── Web/                        # Inertia pages
│   │   │   ├── CompanyController.php
│   │   │   ├── BranchController.php
│   │   │   ├── DepartmentController.php
│   │   │   └── PositionController.php
│   │   ├── Api/V1/                     # REST API
│   │   │   ├── CompanyController.php
│   │   │   ├── BranchController.php
│   │   │   ├── DepartmentController.php
│   │   │   └── PositionController.php
│   │   └── Api/Sync/                   # Desktop sync
│   │       └── OrganizationSyncController.php
│   └── Routes/
│       ├── web.php
│       ├── api.php
│       └── api-sync.php
│
└── Settings/
    ├── Models/
    │   ├── SystemSetting.php
    │   ├── PayrollConfig.php
    │   ├── AttendanceConfig.php
    │   ├── ThrConfig.php
    │   ├── BpjsConfig.php
    │   └── PphConfig.php
    │   ├── Services/
    │   │   └── SettingsService.php
    │   ├── Controllers/
    │   │   ├── Web/                        # Inertia pages
    │   │   │   ├── SystemSettingController.php
    │   │   │   ├── PayrollConfigController.php
    │   │   │   └── AttendanceConfigController.php
    │   │   ├── Api/V1/                     # REST API
    │   │   │   ├── SystemSettingController.php
    │   │   │   ├── PayrollConfigController.php
    │   │   │   └── AttendanceConfigController.php
    │   │   └── Api/Sync/                   # Desktop sync config
    │   │       └── SettingsSyncController.php
    │   └── Routes/
    │       ├── web.php
    │       ├── api.php
    │       └── api-sync.php
```

---

## 2. DATABASE — TABEL & KOLOM

### 2.1 `companies`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(100) | |
| code | varchar(20) | |
| address | text nullable | |
| phone | varchar(20) nullable | |
| email | varchar(100) nullable | |
| npwp | varchar(30) nullable | |
| logo | varchar(255) nullable | |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `branches`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| company_id | bigint (FK→companies) | |
| name | varchar(100) | |
| code | varchar(20) | |
| address | text nullable | |
| phone | varchar(20) nullable | |
| is_head_office | tinyint(1) default 0 | |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.3 `departments`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| branch_id | bigint (FK→branches) | |
| name | varchar(100) | |
| code | varchar(20) | |
| parent_id | bigint nullable (FK→departments) | Self-referencing |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.4 `positions`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| department_id | bigint (FK→departments) | |
| name | varchar(100) | |
| code | varchar(20) | |
| level | int default 0 | Hierarki |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.5 `system_settings`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| key | varchar(50) | |
| value | text | |
| type | varchar(20) default 'string' | string, integer, boolean, json |
| group | varchar(30) | general, employee, attendance, payroll |
| label | varchar(100) | Nama tampilan |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(key)`

### 2.6 `payroll_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| key | varchar(50) | |
| value | text | |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.7 `attendance_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| key | varchar(50) | |
| value | text | |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.8 `thr_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| min_months | int default 12 | Minimal bulan kerja |
| max_months | int default 36 | Max bulan untuk prorate |
| is_prorated | tinyint(1) default 1 | |
| percentage | decimal(5,2) default 100.00 | |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.9 `bpjs_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| type | varchar(20) | tk, ks, pensiun |
| company_rate | decimal(5,2) | Rate ditanggung perusahaan (%) |
| employee_rate | decimal(5,2) | Rate ditanggung karyawan (%) |
| max_salary | decimal(15,2) | Batas upah |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.10 `pph_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| method | varchar(20) | ter (monthly) atau progressive (yearly) |
| ptkp_type | varchar(10) | TK/0, TK/1, K/0, etc |
| ptkp_value | decimal(15,2) | Nilai PTKP |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 3. YANG HARUS DIPERBAIKI

### 3.1 Settings — Pisah dari `system_settings`

**Existing**: Semua config (payroll, attendance, THR, BPJS, PPh) numpuk di `system_settings` sebagai key-value.

**Perbaikan**: Masing-masing config punya tabel sendiri:
- `payroll_configs` — khusus payroll
- `attendance_configs` — khusus attendance
- `thr_configs` — khusus THR (yang existing ada tabel ini)
- `bpjs_configs` — khusus BPJS
- `pph_configs` — khusus PPh

`system_settings` hanya untuk setting general (nama app, logo, timezone, etc).

### 3.2 Department Self-Reference

**Existing**: Department gak support parent-child (divisi → sub-divisi).

**Perbaikan**: Tambah kolom `parent_id` nullable.

### 3.3 Branch Tanpa Company

**Existing**: Branch langsung terhubung ke instance, gak ada Company.

**Perbaikan**: Tambah tabel `companies`, FK dari `branches.company_id`.

---

## 4. TASK LIST

### Task 1: Migration
- [ ] `companies` table
- [ ] `branches` table (rename & tambah company_id)
- [ ] `departments` table (tambah parent_id)
- [ ] `positions` table
- [ ] `system_settings` table (redesign — pindahin config spesifik ke tabel sendiri)
- [ ] `payroll_configs` table
- [ ] `attendance_configs` table
- [ ] `thr_configs` table
- [ ] `bpjs_configs` table
- [ ] `pph_configs` table

### Task 2: Models
- [ ] `Company.php` — `$fillable`, `$casts`, `branch()`, `HasAudit`
- [ ] `Branch.php` — `$fillable`, `$casts`, `company()`, `departments()`, `HasAudit`
- [ ] `Department.php` — `$fillable`, `$casts`, `branch()`, `parent()`, `children()`, `HasAudit`
- [ ] `Position.php` — `$fillable`, `$casts`, `department()`, `HasAudit`
- [ ] `SystemSetting.php` — `$fillable`
- [ ] `PayrollConfig.php` — `$fillable`
- [ ] `AttendanceConfig.php` — `$fillable`
- [ ] `ThrConfig.php` — `$fillable`
- [ ] `BpjsConfig.php` — `$fillable`
- [ ] `PphConfig.php` — `$fillable`

### Task 3: Services
- [ ] `OrganizationService.php`
  - `getCompanies(instanceId)` — list company
  - `createCompany(data)`
  - `updateCompany(id, data)`
  - `deleteCompany(id)`
  - `getBranches(companyId)` — list branch per company
  - `getDepartments(branchId)` — list department per branch + nested
  - `getPositions(departmentId)` — list position per department
- [ ] `SettingsService.php`
  - `getSettings(instanceId, group)` — ambil setting per group
  - `updateSettings(instanceId, group, data)` — bulk update
  - `getPayrollConfigs(instanceId)`
  - `updatePayrollConfigs(instanceId, data)`
  - `getAttendanceConfigs(instanceId)`
  - `updateAttendanceConfigs(instanceId, data)`

### Task 4: Controllers
- [ ] `CompanyController.php` — index, store, show, update, destroy
- [ ] `BranchController.php` — index, store, show, update, destroy
- [ ] `DepartmentController.php` — index, store, show, update, destroy
- [ ] `PositionController.php` — index, store, show, update, destroy
- [ ] `SystemSettingController.php` — index, update
- [ ] `PayrollConfigController.php` — index, update
- [ ] `AttendanceConfigController.php` — index, update

### Task 5: Routes
- [ ] `Organization/Routes/api.php` — CRUD companies, branches, departments, positions
- [ ] `Settings/Routes/api.php` — GET/PUT settings per group

### Task 6: RBAC Matrix

| Role | Company | Branch | Dept | Position | Settings |
|------|---------|--------|------|----------|----------|
| Superadmin | CRUD | CRUD | CRUD | CRUD | CRUD |
| Admin | Read | CRUD | CRUD | CRUD | Read |
| Supervisor | Read | Read | Read | Read | — |

- [ ] Define permissions: `company.*`, `branch.*`, `department.*`, `position.*`, `settings.*`
- [ ] Assign ke role via seeder
- [ ] Middleware `permission:` di route

### Task 7: Seeder
- [ ] `CompanySeeder` — 1 company default
- [ ] `BranchSeeder` — 1 cabang default (head office)
- [ ] `DepartmentSeeder` — beberapa department dasar
- [ ] `PositionSeeder` — beberapa posisi dasar
- [ ] `SettingsSeeder` — payroll config, attendance config defaults

### Task 8: Test
- [ ] `OrganizationServiceTest.php` — CRUD + hierarchical department
- [ ] `CompanyControllerTest.php` — endpoint + permission
- [ ] `SettingsServiceTest.php` — get/update settings

---

## 5. ENDPOINT SUMMARY

```
# Company
GET    /api/v1/companies
POST   /api/v1/companies
GET    /api/v1/companies/{id}
PUT    /api/v1/companies/{id}
DELETE /api/v1/companies/{id}

# Branch (filter by company)
GET    /api/v1/branches?company_id=1
POST   /api/v1/branches
GET    /api/v1/branches/{id}
PUT    /api/v1/branches/{id}
DELETE /api/v1/branches/{id}

# Department (filter by branch, nested)
GET    /api/v1/departments?branch_id=1&nested=true
POST   /api/v1/departments
GET    /api/v1/departments/{id}
PUT    /api/v1/departments/{id}
DELETE /api/v1/departments/{id}

# Position (filter by department)
GET    /api/v1/positions?department_id=1
POST   /api/v1/positions
GET    /api/v1/positions/{id}
PUT    /api/v1/positions/{id}
DELETE /api/v1/positions/{id}

# Settings
GET    /api/v1/settings?group=general
PUT    /api/v1/settings
GET    /api/v1/settings/payroll
PUT    /api/v1/settings/payroll
GET    /api/v1/settings/attendance
PUT    /api/v1/settings/attendance
```
