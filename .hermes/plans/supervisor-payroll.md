# Supervisor Payroll Module — Plan

## Tujuan
Porting 3 halaman Payroll admin (Gaji Karyawan, Slip Gaji, Perhitungan THR) ke Supervisor dengan:
- UI persis seperti admin
- Tabel data independen: `supervisor_payrolls` (struktur = `pay_records`)
- Full fungsi: Generate Awal + Kalkulasi & Kunci (seperti admin)

---

## Phase 1: Database

### 1.1 Migration `supervisor_payrolls`
- Struktur kolom **identik** dengan `pay_records` (2026_06_05_000003)
- Nama tabel: `supervisor_payrolls`
- Unique key: `['employee_id', 'pay_period_id', 'segment']`
- FK: `pay_period_id` → `pay_periods`, `employee_id` → `employees`, `att_record_id` → `att_records`

### 1.2 Model `SupervisorPayRecord`
- `app/Modules/Supervisor/Payroll/Models/SupervisorPayRecord.php`
- `$table = 'supervisor_payrolls'`
- Fillable, casts, booted (UUID) — identik dengan PayRecord
- Relationships: payPeriod, employee, attRecord

---

## Phase 2: Backend API

### 2.1 Routes (file baru)
- `app/Modules/Supervisor/Payroll/Routes/api.php`
- Prefix: `v1/supervisor/payroll`
- Middleware: `auth:sanctum` + permission guard

### 2.2 Controller: Supervisor Gaji Karyawan
- `app/Modules/Supervisor/Payroll/Controllers/Api/V1/SupervisorGajiKaryawanController.php`
- Clone dari `GajiKaryawanController` dengan:
  - `index()` → baca dari `supervisor_payrolls`, return JSON
  - `export()` → export Excel

### 2.3 Controller: Supervisor Payslip
- `app/Modules/Supervisor/Payroll/Controllers/Api/V1/SupervisorPayslipController.php`
- Clone dari `PayslipController` dengan:
  - `index()` → baca dari `supervisor_payrolls`, return JSON

### 2.4 THR
- **Reuse** admin `ThrApiController` (THR pakai tabel `employee_thrs` — shared)
- Atau buat route proxy di supervisor namespace

---

## Phase 3: Frontend Vue

### 3.1 Gaji Karyawan (`Pages/Supervisor/Payroll/Index.vue`)
- Clone dari `Pages/Admin/Payroll/GajiKaryawan/Index.vue`
- Ganti API endpoint: `/api/v1/supervisor/payroll/gaji-karyawan`
- Fitur: period selector, Generate Awal, Kalkulasi & Kunci, Export, Settings, Section A/B filter, search

### 3.2 Slip Gaji (`Pages/Supervisor/Payroll/Slip.vue`)
- Clone dari `Pages/Admin/Payroll/Slip/Index.vue`
- Ganti API endpoint: `/api/v1/supervisor/payroll/payslips`
- Fitur: period selector, search, print single, bulk print

### 3.3 Perhitungan THR (`Pages/Supervisor/Payroll/Thr.vue`)
- Clone dari `Pages/Admin/Payroll/Thr.vue`
- API endpoint tetap `/api/v1/payroll/thr` (shared)
- Fitur: year filter, generate THR, edit komponen, bulk print

### 3.4 Sidebar
- **Sudah ada** di `Supervisor/Sidebar.vue` (Penggajian → Gaji Karyawan, Slip Gaji, Perhitungan THR)

---

## Phase 4: Wiring

### 4.1 Router
- **Sudah ada** di `resources/js/router/index.js`:
  - `/supervisor/payroll` → `SupervisorPayroll`
  - `/supervisor/payroll/slip` → `SupervisorPayrollSlip`
  - `/supervisor/payroll/thr` → `SupervisorThr`

### 4.2 Route discovery (Laravel)
- Tambahkan glob pattern untuk supervisor payroll routes di `bootstrap/app.php` (jika belum auto-discover)

### 4.3 Permission
- Tambahkan permission untuk supervisor payroll di seeder & permission store

---

## Open Questions (perlu konfirmasi)
1. **Generate Awal** — admin Generate panggil `AttendanceApiController::recapGenerate()` + `recapApprove()`. Supervisor perlu method terpisah yang baca dari supervisor attendance snapshot → tulis ke `supervisor_payrolls`. Atau cukup reuse admin flow?

2. **THR** — admin THR pakai `employee_thrs` table. Supervisor perlu tabel THR sendiri atau share?
