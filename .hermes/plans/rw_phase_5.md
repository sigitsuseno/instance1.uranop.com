# Phase 5: Leave + Web UI + Desktop

**File**: `rw_phase_5.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1 + Phase 3 (employee)

---

## Tujuan

CRUD Leave Type, Policy, Period + Leave Request (submit, approve, reject) + Quota management.

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/Leave/
├── Models/
│   ├── LeaveType.php
│   ├── LeavePolicy.php
│   ├── LeavePeriod.php
│   ├── LeaveRequest.php
│   ├── EmployeeLeave.php
│   ├── LeaveDocument.php
│   └── LeaveChangeRequest.php
├── Services/
│   ├── LeaveTypeService.php
│   ├── LeaveRequestService.php
│   ├── LeaveQuotaService.php
│   └── LeavePolicyService.php
├── Controllers/
│   └── Api/
│       └── V1/
│           ├── LeaveTypeController.php
│           ├── LeaveRequestController.php
│           └── LeaveQuotaController.php
└── Routes/
    └── api.php
```

---

## 2. DATABASE

### 2.1 `leave_types`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(50) | Tahunan, Sakit, Melahirkan, Besar |
| code | varchar(20) | |
| default_quota | int default 12 | Kuota default per tahun |
| is_paid | tinyint(1) default 1 | |
| can_carry_forward | tinyint(1) default 0 | Bisa diakumulasi? |
| max_carry_forward | int default 0 | Max carry forward |
| requires_document | tinyint(1) default 0 | Butuh lampiran? |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `leave_policies`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| leave_type_id | bigint (FK→leave_types) | |
| min_work_months | int default 0 | Min masa kerja (bulan) |
| max_consecutive_days | int default 14 | Max hari berturut-turut |
| requires_approval | tinyint(1) default 1 | |
| approval_levels | int default 1 | Jumlah level approval |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.3 `leave_periods`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(50) | 2026 |
| start_date | date | |
| end_date | date | |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.4 `leave_requests`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| leave_type_id | bigint (FK→leave_types) | |
| start_date | date | |
| end_date | date | |
| total_days | int | |
| reason | text | |
| status | enum('pending','approved','rejected','cancelled') | |
| approved_by | bigint (FK→users) nullable | |
| approved_at | timestamp nullable | |
| rejected_reason | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.5 `employee_leaves` (quota per employee per type per period)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| leave_type_id | bigint (FK→leave_types) | |
| leave_period_id | bigint (FK→leave_periods) | |
| total_quota | int | Kuota total |
| used_quota | int default 0 | Kuota terpakai |
| remaining_quota | int | Sisa kuota |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(employee_id, leave_type_id, leave_period_id)`

### 2.6 `leave_documents`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| leave_request_id | bigint (FK→leave_requests) | |
| file_name | varchar(100) | |
| file_path | varchar(255) | |
| created_at | timestamp | |

### 2.7 `leave_change_requests`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| leave_request_id | bigint (FK→leave_requests) | |
| change_type | enum('cancel','extend','shorten') | |
| reason | text | |
| status | enum('pending','approved','rejected') | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 3. TASK LIST

### Task 1: Migration
- [ ] `leave_types`
- [ ] `leave_policies`
- [ ] `leave_periods`
- [ ] `leave_requests`
- [ ] `employee_leaves`
- [ ] `leave_documents`
- [ ] `leave_change_requests`

### Task 2: Models
- [ ] Semua model + `$fillable`, `$casts`, relasi

### Task 3: Services
- [ ] `LeaveTypeService.php` — CRUD
- [ ] `LeaveRequestService.php` — submit, approve, reject, cancel
- [ ] `LeaveQuotaService.php` — init quota, update after approve, check availability
- [ ] `LeavePolicyService.php` — validasi policy (min work months, max days)

### Task 4: Controllers

### Task 5: Test
- [ ] `LeaveQuotaServiceTest.php` — quota calculation, carry forward
- [ ] `LeaveRequestServiceTest.php` — submit, approve flow, quota deduction

---

## 4. ENDPOINT SUMMARY

```
GET    /api/v1/leaves/types
POST   /api/v1/leaves/types
PUT    /api/v1/leaves/types/{id}
DELETE /api/v1/leaves/types/{id}

GET    /api/v1/leaves/policies
POST   /api/v1/leaves/policies
PUT    /api/v1/leaves/policies/{id}

GET    /api/v1/leaves/periods
POST   /api/v1/leaves/periods

GET    /api/v1/leaves/requests?employee_id=&status=
POST   /api/v1/leaves/requests
GET    /api/v1/leaves/requests/{id}
PUT    /api/v1/leaves/requests/{id}/approve
PUT    /api/v1/leaves/requests/{id}/reject
PUT    /api/v1/leaves/requests/{id}/cancel

GET    /api/v1/leaves/quotas?employee_id=&period_id=
```
