# Phase 6: Kasbon + Web UI + Desktop

**File**: `rw_phase_6.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1 + Phase 3 (employee)

---

## Tujuan

Kasbon request (submit, approve, reject) + installment schedule + payment tracking.

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/Kasbon/
├── Models/
│   ├── KasbonRequest.php
│   └── KasbonInstallment.php
├── Services/
│   ├── KasbonRequestService.php
│   └── KasbonInstallmentService.php
├── Controllers/
│   └── Api/
│       └── V1/
│           ├── KasbonRequestController.php
│           └── KasbonInstallmentController.php
└── Routes/
    └── api.php
```

---

## 2. DATABASE

### 2.1 `kasbon_requests`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| amount | decimal(15,2) | Jumlah pengajuan |
| installment_count | int | Jumlah cicilan |
| installment_amount | decimal(15,2) | Nilai per cicilan |
| reason | text | Alasan |
| status | enum('pending','approved','rejected','completed') | |
| approved_by | bigint (FK→users) nullable | |
| approved_at | timestamp nullable | |
| rejected_reason | text nullable | |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `kasbon_installments`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| kasbon_request_id | bigint (FK→kasbon_requests) | |
| installment_number | int | Cicilan ke-1, ke-2, ... |
| amount | decimal(15,2) | |
| due_date | date | Jatuh tempo |
| paid_date | date nullable | Tanggal bayar |
| status | enum('pending','paid') | |
| payroll_period_id | bigint nullable (FK→payroll_periods) | Dipotong di periode mana |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 3. TASK LIST

### Task 1: Migration
- [ ] `kasbon_requests`
- [ ] `kasbon_installments`

### Task 2: Models
- [ ] `KasbonRequest.php` — `$fillable`, `$casts`, `employee()`, `installments()`
- [ ] `KasbonInstallment.php` — `$fillable`, `$casts`, `request()`

### Task 3: Services
- [ ] `KasbonRequestService.php`
  - `submit(employeeId, data)` — validasi + create request
  - `approve(id, userId)` — create installment schedule + update request
  - `reject(id, userId, reason)`
- [ ] `KasbonInstallmentService.php`
  - `generateSchedule(request)` — generate cicilan berdasarkan installment_count
  - `markAsPaid(id, payrollPeriodId)` — tandai lunas

### Task 4: Controllers

### Task 5: Test
- [ ] `KasbonRequestServiceTest.php` — approve → installment schedule generated
- [ ] `KasbonInstallmentServiceTest.php` — schedule calculation

---

## 4. ENDPOINT SUMMARY

```
GET    /api/v1/kasbon/requests?employee_id=&status=
POST   /api/v1/kasbon/requests
GET    /api/v1/kasbon/requests/{id}
PUT    /api/v1/kasbon/requests/{id}/approve
PUT    /api/v1/kasbon/requests/{id}/reject

GET    /api/v1/kasbon/installments?kasbon_request_id=
PUT    /api/v1/kasbon/installments/{id}/pay
```
