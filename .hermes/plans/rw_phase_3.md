# Phase 3: Employee + Supervisor EmployeeGroup + Web UI + Desktop

**File**: `rw_phase_3.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1 (auth) + Phase 2 (organization) (auth, helpers) + Phase 2 (organization)

---

## Tujuan

1. CRUD Employee + sub-resources (salary, contract, document, family, termination)
2. Employee search, filter, export
3. Supervisor EmployeeGroup — kanban assignment per periode

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/
├── Employee/
│   ├── Models/
│   │   ├── Employee.php
│   │   ├── EmployeeBpjs.php
│   │   ├── EmployeeContract.php
│   │   ├── EmployeeDocument.php
│   │   ├── EmployeeFamily.php
│   │   ├── EmployeePositionHistory.php
│   │   ├── EmployeeSalary.php
│   │   ├── EmployeeSalaryComponent.php
│   │   └── EmployeeTermination.php
│   ├── Services/
│   │   ├── EmployeeService.php
│   │   ├── EmployeeSalaryService.php
│   │   └── EmployeeContractService.php
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── EmployeeController.php
│   │           ├── EmployeeSalaryController.php
│   │           ├── EmployeeContractController.php
│   │           ├── EmployeeDocumentController.php
│   │           ├── EmployeeFamilyController.php
│   │           └── EmployeeTerminationController.php
│   ├── Exports/
│   │   └── EmployeeExport.php
│   └── Routes/
│       └── api.php
│
└── Supervisor/
    └── EmployeeGroup/
        ├── Models/
        │   └── SupervisorEmployeeGroup.php
        ├── Services/
        │   └── EmployeeGroupService.php
        ├── Controllers/
        │   └── Api/
        │       └── V1/
        │           └── EmployeeGroupController.php
        ├── Exports/
        │   └── EmployeeGroupTemplateExport.php
        └── Routes/
            └── api.php
```

---

## 2. DATABASE — TABEL & KOLOM

### 2.1 `employees`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| branch_id | bigint (FK→branches) | |
| department_id | bigint (FK→departments) | |
| position_id | bigint (FK→positions) | |
| employee_code | varchar(20) UNIQUE | NIP/NIK |
| name | varchar(100) | |
| gender | enum('L','P') | |
| birth_place | varchar(50) nullable | |
| birth_date | date nullable | |
| religion | varchar(20) nullable | |
| marital_status | varchar(20) nullable | |
| address | text nullable | |
| phone | varchar(20) nullable | |
| email | varchar(100) nullable | |
| join_date | date | Tanggal masuk |
| permanent_date | date nullable | Tanggal pengangkatan |
| resign_date | date nullable | Tanggal resign |
| bank_name | varchar(50) nullable | |
| bank_account | varchar(30) nullable | |
| bank_holder | varchar(100) nullable | |
| bpjs_tk | varchar(30) nullable | No BPJS TK |
| bpjs_ks | varchar(30) nullable | No BPJS Kesehatan |
| bpjs_pen | varchar(30) nullable | No BPJS Pensiun |
| npwp | varchar(30) nullable | |
| ptkp | varchar(10) nullable | TK/0, K/1, etc |
| photo | varchar(255) nullable | |
| is_active | tinyint(1) default 1 | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `employee_bpjs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| type | varchar(20) | tk, ks, pensiun |
| number | varchar(30) | Nomor BPJS |
| created_at | timestamp | |
| updated_at | timestamp | |

> Catatan: tabel ini alternatif kalau mau normalisasi BPJS per tipe. Existing pakai kolom langsung di `employees`.

### 2.3 `employee_salaries`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| gaji_pokok | decimal(15,2) | Gaji pokok |
| tunjangan | decimal(15,2) default 0 | Tunjangan tetap |
| premi | decimal(15,2) default 0 | Premi hadir |
| tj_masa_kerja | decimal(15,2) default 0 | Tunjangan masa kerja |
| effective_date | date | Tanggal berlaku |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(employee_id, effective_date)`

### 2.4 `employee_salary_components`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| component_name | varchar(100) | Nama komponen |
| amount | decimal(15,2) | Nilai |
| type | enum('allowance','deduction') | Tunjangan / Potongan |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.5 `employee_contracts`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| contract_number | varchar(50) | No kontrak |
| type | enum('probation','fixed','permanent') | |
| start_date | date | |
| end_date | date | |
| status | enum('active','expired','terminated') | |
| document_file | varchar(255) nullable | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.6 `employee_documents`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| name | varchar(100) | Nama dokumen |
| type | varchar(30) | ktp, ijazah, sk, etc |
| file_path | varchar(255) | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.7 `employee_families`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| name | varchar(100) | |
| relation | varchar(20) | spouse, child, parent |
| gender | enum('L','P') nullable | |
| birth_date | date nullable | |
| occupation | varchar(50) nullable | |
| is_dependent | tinyint(1) default 0 | Tanggungan |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.8 `employee_position_histories`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| position_id | bigint (FK→positions) | |
| department_id | bigint (FK→departments) | |
| effective_date | date | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.9 `employee_terminations`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| type | enum('resign','retire','laid_off','other') | |
| date | date | |
| reason | text | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.10 `supervisor_employee_groups`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| period_start | date | |
| period_end | date | |
| group_name | varchar(100) | |
| group_code | varchar(20) | |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(employee_id, period_start, period_end)`

---

## 3. YANG HARUS DIPERBAIKI

### 3.1 `employee_code` vs `nip`

**Existing**: Dua field mirip — `employee_code` (untuk payroll) dan `nip` (untuk fingerprint). 

**Perbaikan**: Hanya pakai `employee_code`. Kalau fingerprint butuh mapping berbeda, simpan di `notes` atau tabel mapping terpisah.

### 3.2 BPJS — Normalisasi atau Denormalisasi?

**Existing**: Kolom BPJS langsung di tabel `employees` (`bpjs_tk`, `bpjs_ks`, `bpjs_pen`).

**Opsi A**: Tetap di `employees` — simple, 1 query.
**Opsi B**: Tabel terpisah `employee_bpjs` — normalisasi, support multiple record per tipe.

**Rekomendasi**: **Opsi A** (tetap di employees). BPJS jarang berubah dan selalu 1:1.

### 3.3 Supervisor Group — Deadlock Pitfall

**Existing**: Kolom group di-source dari `supervisor_employee_groups` (tabel assignment). Kalau kosong → kolom gak muncul → gak bisa drag → gak bisa assign → tetap kosong.

**Perbaikan**: Kolom group SELALU dari `payroll_periods`. Tabel assignment cuma untuk data yang sudah di-assign.

### 3.4 Gender Field

**Existing**: `gender` varchar dengan nilai bervariasi ("L", "P", "laki-laki", "perempuan", "Laki-Laki").

**Perbaikan**: Pakai `enum('L','P')` untuk konsistensi.

---

## 4. TASK LIST

### Task 1: Migration
- [ ] `employees` table
- [ ] `employee_salaries` table
- [ ] `employee_salary_components` table
- [ ] `employee_contracts` table
- [ ] `employee_documents` table
- [ ] `employee_families` table
- [ ] `employee_position_histories` table
- [ ] `employee_terminations` table
- [ ] `supervisor_employee_groups` table

### Task 2: Models
- [ ] `Employee.php` — `$fillable`, `$casts`, `branch()`, `department()`, `position()`, `salaries()`, `contracts()`, `families()`, `HasAudit`
- [ ] `EmployeeSalary.php`
- [ ] `EmployeeSalaryComponent.php`
- [ ] `EmployeeContract.php`
- [ ] `EmployeeDocument.php`
- [ ] `EmployeeFamily.php`
- [ ] `EmployeePositionHistory.php`
- [ ] `EmployeeTermination.php`
- [ ] `SupervisorEmployeeGroup.php`

### Task 3: Services
- [ ] `EmployeeService.php`
  - `getList(filters)` — search + filter by branch/department/status + paginate
  - `getById(id)` — detail + all relations
  - `create(data)` — validasi + insert
  - `update(id, data)` — validasi + update
  - `delete(id)` — soft delete (set is_active=0)
  - `export(filters)` — Excel export
- [ ] `EmployeeSalaryService.php`
  - `getByEmployee(employeeId)` — riwayat gaji
  - `setSalary(employeeId, data)` — insert salary baru + update effective_date
  - `getCurrentSalary(employeeId)` — salary terbaru
- [ ] `EmployeeContractService.php`
  - `getByEmployee(employeeId)`
  - `create(data)` — validasi tanggal tidak overlap
- [ ] `EmployeeGroupService.php`
  - `getRosterPool(periodStart, periodEnd)` — karyawan roster yg belum di-assign
  - `getGroupPool(periodStart, periodEnd)` — karyawan yg sudah di-assign
  - `bulkUpdate(data)` — assign & unassign (transaction)

### Task 4: Controllers
- [ ] `EmployeeController.php` — index, store, show, update, destroy, export
- [ ] `EmployeeSalaryController.php` — index, store, update
- [ ] `EmployeeContractController.php` — index, store, update
- [ ] `EmployeeDocumentController.php` — index, store, destroy
- [ ] `EmployeeFamilyController.php` — index, store, update, destroy
- [ ] `EmployeeTerminationController.php` — store
- [ ] `EmployeeGroupController.php` — index, bulkUpdate, previewImport, processImport, template

### Task 5: Routes
- [ ] `Employee/Routes/api.php`
- [ ] `Supervisor/EmployeeGroup/Routes/api.php`

### Task 6: Seeder
- [ ] `EmployeeSeeder` — 20 employee dummy
- [ ] `EmployeeSalarySeeder` — salary per employee (random)

### Task 7: Test
- [ ] `EmployeeServiceTest.php` — CRUD, search, filter
- [ ] `EmployeeGroupServiceTest.php` — assign/unassign, duplicate prevention, bulkUpdate transaction

### Task 8: RBAC

| Role | Employee | Salary | Contract | Document | Family | Termination |
|------|----------|--------|----------|----------|--------|-------------|
| Superadmin | CRUD | CRUD | CRUD | CRUD | CRUD | CRUD |
| Admin | CRUD | CRUD | CRUD | CRUD | CRUD | CRUD |
| Supervisor | Read | — | — | — | — | — |

---

## 5. ENDPOINT SUMMARY

```
# Employee
GET    /api/v1/employees?search=&branch_id=&department_id=&is_active=1
POST   /api/v1/employees
GET    /api/v1/employees/{id}
PUT    /api/v1/employees/{id}
DELETE /api/v1/employees/{id}
GET    /api/v1/employees/export

# Employee Salary
GET    /api/v1/employees/{id}/salaries
POST   /api/v1/employees/{id}/salaries
PUT    /api/v1/employees/{id}/salaries/{salaryId}

# Employee Contract
GET    /api/v1/employees/{id}/contracts
POST   /api/v1/employees/{id}/contracts
PUT    /api/v1/employees/{id}/contracts/{contractId}

# Employee Document
GET    /api/v1/employees/{id}/documents
POST   /api/v1/employees/{id}/documents
DELETE /api/v1/employees/{id}/documents/{docId}

# Employee Family
GET    /api/v1/employees/{id}/families
POST   /api/v1/employees/{id}/families
PUT    /api/v1/employees/{id}/families/{familyId}
DELETE /api/v1/employees/{id}/families/{familyId}

# Employee Termination
POST   /api/v1/employees/{id}/termination

# Supervisor — Employee Group
GET    /api/v1/supervisor/employee-groups?period_start=&period_end=
POST   /api/v1/supervisor/employee-groups          # bulk update
POST   /api/v1/supervisor/employee-groups/import   # import Excel
GET    /api/v1/supervisor/employee-groups/template  # download template
```
