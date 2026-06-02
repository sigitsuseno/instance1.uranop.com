# PRD — Aplikasi Web Instance1 HRIS (Lengkap)

| Metadata | |
|----------|---------|
| **Author** | Sigit / Paijo |
| **Project** | instance1.uranop.com — HRIS Multi-Instance |
| **Versi** | Draft v0.1 |
| **Status** | Draft |
| **Tanggal** | 2026-06-02 |
| **Tech Stack** | Laravel 13 + Vue 3 (SPA) + MySQL |
| **Sumber Referensi** | `hris-system` (D:\laragon\www\hris-system) |
| **Dokumen Terkait** | MIGRATION_GUIDE.md, PRD_ATTENDANCE.md |

---

## 1. Executive Summary

Aplikasi web Instance1 HRIS saat ini sudah memiliki backend untuk modul **Auth**, **Organization**, **Employee**, serta sebagian **Settings**, **Schedule**, dan **Leave**. Frontend untuk semua modul sudah dalam bentuk mock data (kecuali Employee yang sudah terintegrasi API).

Dokumen ini menjabarkan kebutuhan lengkap untuk menyelesaikan **8 modul sisanya**, dari Attendance hingga Supervisor Dashboard, dengan referensi utama dari codebase `hris-system` yang sudah mature.

Total estimasi: **~46 model, ~44 controller, ~32 service** untuk di-port/ditulis ulang dari `hris-system` ke aplikasi baru.

---

## 2. Arsitektur Modular

```
app/Modules/{Module}/
├── Controllers/
│   ├── Api/V1/
│   │   └── {Module}ApiController.php
│   └── Web/
│       └── {Module}WebController.php
├── Models/
│   └── {Model}.php
├── Resources/
│   └── {Module}Resource.php
├── Routes/
│   ├── api.php
│   └── web.php
├── Services/
│   └── {Module}Service.php
└── Providers/
    └── {Module}ServiceProvider.php
```

### Prinsip Porting dari hris-system

1. **Hapus semua `company_id` / `branch_id`** — 1 instance = 1 DB, tidak perlu global scope
2. **Rename tabel** dengan prefix module (`att_`, `pay_`, `lve_`, `sch_`, `set_`, `org_`, `emp_`)
3. **Tabel dihapus** (sesuai MIGRATION_GUIDE.md):
   - `employee_periodes` → diganti effective date pattern
   - `payroll_results`, `payroll_breakdowns`, `payslips` → digabung ke `pay_records`
   - `payroll_component_snapshots`, `payroll_employee_snapshots` → di-embed
   - `permit_requests` → izin via Leave module (`lve_types.category`)
   - `user_branches`, `company_settings`, `branch_settings` → tidak relevan
4. **Minimalisir controller** — beberapa controller kecil di hris-system bisa digabung
5. **API-first** — semua logic via REST API, bukan Inertia/Blade
6. **Service layer** — pisah business logic dari controller

### Kompleksitas Porting

| Level | Jumlah Controller | Contoh |
|-------|------------------|--------|
| 🔴 Sangat Tinggi (>600 baris) | 7 | SalaryBreakdown (1207), StaffOvertime (777), AttendanceAutolog (770), AttendanceRoster (727), AttendanceSnapshot (644), Payroll (633), LeaveWeb (615) |
| 🟡 Tinggi (300-600 baris) | 8 | PengelolaanGaji (570), Report (517), PayrollPeriod (221) + AttendanceLog (229) + lainnya |
| 🟢 Sedang (100-300 baris) | ~15 | Sebagian besar controller Attendance, Employee |
| 🔵 Rendah (<100 baris) | ~10 | Controller kecil: config, settings, dll |

---

## 3. Modul 1: Schedule (Penyempurnaan)

**Status saat ini:** 7 models ✅, 1 controller ✅, belum ada services ❌

### Yang Sudah Ada

| Entity | Model | Controller | Status |
|--------|-------|-----------|--------|
| WorkPatternType | ✅ WorkPatternType | ✅ getWorkPatternTypes | OK |
| WorkPattern | ✅ WorkPattern | ✅ CRUD + details | OK |
| WorkPatternDetail | ✅ WorkPatternDetail | ✅ via WorkPattern | OK |
| Shift | ✅ Shift | ✅ CRUD | OK |
| EmployeeShiftRoster | ✅ EmployeeShiftRoster | ✅ getRoster, generate, import, override | OK |
| WorkingCalendar | ✅ WorkingCalendar | ✅ getCalendars, holidays | OK |
| Holiday | ✅ Holiday | ✅ via Calendar | OK |

### Yang Perlu Ditambahkan

| Item | Keterangan |
|------|-----------|
| **ScheduleService** | Port dari `ShiftRosterService` (generate roster, import logic) |
| **ScheduleResource** | Resource untuk response API |
| **API Export** | Export roster ke Excel |
| **API Import** | Import roster dari Excel (batch) |
| **Validasi** | Roster conflict detection (double booking shift) |
| **Shift Schedule** | Jika ada model ShiftSchedule di hris-system yang belum di-port |

---

## 4. Modul 2: Settings (Penyempurnaan)

**Status saat ini:** 17 models ✅, controllers ✅, belum ada services ❌

### Yang Sudah Ada

| Entity | Model | Controller | Status |
|--------|-------|-----------|--------|
| SystemSetting | ✅ | ✅ SettingsApiController | OK |
| SalaryGrade | ✅ | ✅ SalaryGradeApiController | OK |
| SalaryGradeHistory | ✅ | — (via SalaryGrade) | OK |
| EmployeeGroup | ✅ | ✅ EmployeeDataApiController | OK |
| EmployeeGroupMaster | ✅ | ✅ EmployeeDataApiController | OK |
| EmployeeGroupSetting | ✅ | — | Perlu dicek |
| SalaryComponent | ✅ | ✅ PayrollConfigApiController | OK |
| BpjsConfig | ✅ | — | Perlu API |
| PphConfig | ✅ | — | Perlu API |
| PtkpRate | ✅ | — | Perlu API |
| TerRate | ✅ | — | Perlu API |
| ProgressiveRate | ✅ | — | Perlu API |
| OvertimeRule | ✅ | — | Perlu API |
| OvertimeRuleDetail | ✅ | — | Perlu API |
| ServiceYearAllowance | ✅ | — | Perlu API |
| ThrConfig | ✅ | — | Perlu API |
| WorkPattern (settings) | ✅ | — | Perlu dicek |

### Yang Perlu Ditambahkan

| Item | Keterangan |
|------|-----------|
| **PayrollConfigApiController** | Lengkapi API untuk BpjsConfig, PphConfig, PtkpRate, TerRate, ProgressiveRate, ServiceYearAllowance, ThrConfig, OvertimeRule |
| **SettingsService** | Service untuk business logic settings |
| **SettingsResource** | Resource response |
| **Seeder** | Default values untuk semua config |

---

## 5. Modul 3: Leave (Penyempurnaan)

**Status saat ini:** 6 models ✅, 2 controllers, 2 services, routes ✅

### Yang Sudah Ada

| Entity | Model | Controller | Status |
|--------|-------|-----------|--------|
| LeaveRequest | ✅ | ✅ LeaveApiController (index, store, approve, reject, cancel) | OK |
| LeaveType | ✅ | ✅ LeaveSettingApiController (CRUD) | OK |
| LeavePeriod | ✅ | ✅ LeaveSettingApiController (CRUD) | OK |
| LeavePolicy | ✅ | ✅ LeaveSettingApiController (CRUD) | OK |
| LeaveDocument | ✅ | — | Perlu dicek |
| EmployeeLeave (balance) | ✅ | ✅ LeaveApiController (balances, generateQuota) | OK |
| LeaveBalanceService | — | ✅ | OK |
| LeaveRequestService | — | ✅ | OK |

### Yang Perlu Ditambahkan

| Item | Keterangan |
|------|-----------|
| **LeavePeriodConfig** | Model + migration (port dari hris-system) |
| **LeaveEntitlement** | Model + migration (entitlement per employee) |
| **LeaveBalance** | Model + migration (saldo cuti real-time) |
| **LeaveSettingApiController** | API untuk LeavePeriodConfig |
| **LeaveApprovalService** | Port dari hris-system (approval workflow) |
| **LeaveEntitlementService** | Port dari hris-system (entitlement calculation) |
| **LeavePeriodService** | Port dari hris-system (period management) |
| **LeavePolicyService** | Port dari hris-system |
| **LeavePayrollService** | Port — integrasi cuti ke payroll |
| **Job GenerateLeaveEntitlement** | Port — auto-generate entitlement |
| **Job SyncLeaveBalance** | Port — sinkronisasi saldo |
| **Job SendLeaveReminder** | Port — reminder cuti |
| **LeaveDocumentController** | Upload dokumen pendukung cuti |

---

## 6. Modul 4: Attendance — Paling Kompleks

**Status saat ini:** ❌ **Kosong total** — hanya route file
**Referensi hris-system:** 16 models, 15 controllers, 10 services, 2 calculators, 2 jobs

### 6.1 Ringkasan Alur Data Attendance

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  IMPORT   │──►│   RAW    │──►│   LOG    │──►│  PREPARE │──►│  RECORD  │──► Payroll
│  File     │    │  LOGS    │    │(check-in │    │ (review) │    │(ringkasan│
│ .csv/xlsx │    │          │    │  /out)   │    │          │    │ periode) │
└──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
                                      │
                                      ▼
                               ┌──────────┐    ┌──────────┐
                               │ OVERTIME │    │CONSECUTIVE│
                               │ REQUESTS │    │   DAYS   │
                               └──────────┘    └──────────┘
                                      │
                                      ▼
                               ┌──────────┐
                               │  LOCK &  │
                               │  CLOSE   │
                               └──────────┘
```

### 6.2 Tabel Database (Target)

| Tabel Baru | Tabel Lama (hris-system) | Fungsi |
|-----------|-------------------------|--------|
| `att_raw_logs` | `raw_logs` | Data import mentah dari file fingerprint |
| `att_logs` | `attendance_logs` | Check-in/out hasil auto-process per hari |
| `att_autologs` | `attendance_autologs` | Auto-generated logs (untuk supervisor) |
| `att_prepares` | `attendance_prepares` | Data siap review (per hari per karyawan) |
| `att_records` | `attendance_records` | Ringkasan per periode (dipakai Payroll) |
| `att_summaries` | `attendance_summaries` | Agregasi data absensi |
| `att_snapshots` | `attendance_snapshots` | Snapshot untuk supervisor |
| `att_consecutive_days` | `attendance_consecutive_days` | Tracking kehadiran berturut-turut |
| `att_configs` | `attendance_configs` | Konfigurasi absensi |
| `att_scan_configs` | `scan_detection_configs` | Konfigurasi deteksi scan |
| `att_overtime_rules` | `overtime_rules` | Aturan lembur (shared) — **sudah di Settings** |
| `att_overtimes` | `overtimes` | Pengajuan lembur |

**Tabel yang dihapus/digabung:**
- `permit_requests` → izin via Leave module
- `permit_types` → digabung ke `lve_types` dengan `category: permit`
- `employee_shifts`, `shift_schedules` → digantikan `sch_rosters`

### 6.3 Controller Mapping

| Controller Baru | Controller Lama (hris-system) | Baris | Fungsi |
|----------------|------------------------------|-------|--------|
| **AttendanceApiController** | AttendanceLogController | 229 | Logs CRUD, import, process |
| **AttendanceApiController** | AttendanceRecordController | 189 | Periodic summary |
| **AttendanceApiController** | AttendanceAdjustmentController | 151 | Koreksi manual |
| **AttendanceApiController** | AttendanceRosterController | 727 | **Pisah ke Schedule** — roster |
| **AttendanceApiController** | ConsecutiveDayController | 98 | Consecutive days |
| **AttendanceApiController** | OvertimeController | 76 | Overtime requests |
| **AttendanceApiController** | OvertimeCalculationController | 330 | Overtime calculation |
| **AttendanceApiController** | OvertimeRuleController | 70 | **Pisah ke Settings** |
| **AttendanceApiController** | ShiftController | 149 | **Pisah ke Schedule** |
| **AttendanceApiController** | HolidayController | 101 | **Pisah ke Schedule** |
| **AttendanceApiController** | WorkingCalendarController | 262 | **Pisah ke Schedule** |
| **AttendanceApiController** | LeaveController (old) | 286 | **Pisah ke Leave** |
| **AttendanceApiController** | LeaveTypeController | 109 | **Pisah ke Leave** |
| **AttendanceApiController** | PermitController | 263 | **Pisah ke Leave** |
| **AttendanceApiController** | PermitTypeController | 109 | **Pisah ke Leave** |

**Strategi:** Bikin 1 controller utama `AttendanceApiController` yang handle:
- `att_raw_logs` — CRUD + import
- `att_logs` — CRUD + process
- `att_prepares` — review workflow
- `att_records` — periodic summary
- `att_overtimes` — pengajuan lembur
- `att_consecutive_days` — (opsional untuk dashboard)

### 6.4 Services yang Diperlukan

| Service Baru | Service Lama (hris-system) | Baris | Prioritas |
|-------------|---------------------------|-------|-----------|
| **AttendanceImportService** | (tersebar di controller) | — | 🔴 Must |
| **AttendanceProcessService** | AttendanceCalculatorService | — | 🔴 Must |
| **AttendanceSyncService** | AttendanceSyncService | 989 | 🟡 Should |
| **OvertimeCalculator** | OvertimeCalculator | 113 | 🔴 Must |
| **OvertimeRuleService** | OvertimeRuleService | — | 🟡 Should |
| **HolidayService** | HolidayService | — | 🟢 Bisa ditunda |

### 6.5 API Endpoints yang Diperlukan

#### Raw Logs
```
GET    /api/attendance/raw-logs              → List import log
POST   /api/attendance/raw-logs/import       → Import file .csv/.xlsx
POST   /api/attendance/raw-logs/batch        → Batch insert via sync
DELETE /api/attendance/raw-logs/{id}         → Hapus raw log
```

#### Attendance Logs (Auto-Process)
```
GET    /api/attendance/logs                  → List attendance logs (filter: date, employee, department)
POST   /api/attendance/logs/process          → Proses auto-matching raw → logs
GET    /api/attendance/logs/{id}             → Detail
PUT    /api/attendance/logs/{id}             → Edit manual (koreksi)
POST   /api/attendance/logs/batch            → Batch insert via sync
```

#### Attendance Prepares (Review)
```
GET    /api/attendance/prepares              → List perlu review
POST   /api/attendance/prepares/{id}/approve → Approve
POST   /api/attendance/prepares/{id}/reject  → Reject (dengan alasan)
POST   /api/attendance/prepares/batch-approve→ Batch approve
```

#### Attendance Records (Rekap Periode)
```
GET    /api/attendance/records               → List rekap per periode
POST   /api/attendance/records/generate      → Generate rekap untuk periode tertentu
GET    /api/attendance/records/{id}          → Detail rekap
POST   /api/attendance/records/lock          → Lock periode (gak bisa diedit)
```

#### Overtime
```
GET    /api/attendance/overtimes             → List lembur
POST   /api/attendance/overtimes             → Buat pengajuan
POST   /api/attendance/overtimes/{id}/approve→ Approve
POST   /api/attendance/overtimes/{id}/reject → Reject
GET    /api/attendance/overtimes/calculate   → Hitung lembur + biaya
```

#### Dashboard
```
GET    /api/attendance/dashboard/today       → Stat hari ini
GET    /api/attendance/dashboard/weekly      → Grafik 7 hari
GET    /api/attendance/dashboard/monthly     → Grafik bulan ini
```

### 6.6 Data Flow Detail

#### Step 1: Import
```
User drag-drop .csv/.xlsx →
  AttendanceImportService membaca file →
    Validasi format kolom (employee_number, scan_datetime, machine_id) →
      Mapping ke employee_id berdasarkan employee_number →
        Insert ke att_raw_logs (batch 100 record) →
          Return summary: "1000 record diimport, 5 gagal (employee not found)"
```

#### Step 2: Auto-Process
```
HR klik "Proses" →
  AttendanceProcessService mengambil att_raw_logs yang belum diproses →
    Untuk setiap raw_log:
      - Cari roster hari itu (sch_rosters)
      - Tentukan check-in (scan terdekat sebelum shift_start)
      - Tentukan check-out (scan terdekat setelah shift_end)
      - Hitung: total_work_minutes, late_minutes, early_leave_minutes
      - Cek: apakah ada izin/cuti/sakit? (lve_requests)
    Simpan hasil ke att_logs →
      Update status raw_log menjadi 'processed'
```

#### Step 3: Review
```
HR Manager buka halaman review →
  Lihat att_logs dengan review_status = 'pending' →
    Bisa koreksi manual (edit check-in/out, tambah catatan) →
      Approve / Reject →
        Jika semua approved → siap generate att_records
```

#### Step 4: Generate Records (Rekap Periode)
```
HR klik "Generate Rekap" untuk periode tertentu →
  AttendanceProcessService mengambil att_logs yang sudah approved per periode →
    Untuk setiap employee:
      - Hitung: total_hadir, total_alpha, total_sakit, total_izin, total_cuti
      - Hitung: total_terlambat (menit), total_pulang_awal (menit)
      - Hitung: total_lembur (jam)
      - Ambil data cuti/izin dari Leave module
    Simpan ke att_records →
      Jika sudah sesuai → lock periode (gak bisa diedit)
```

### 6.7 Jobs

| Job | Fungsi | Schedule |
|-----|--------|----------|
| `ProcessAttendanceLogImport` | Auto-process raw_logs yang baru diimport | Setelah import |
| `ProcessUnprocessedLogsJob` | Proses raw_logs yang belum diproses (periodic) | Setiap 5 menit |
| `AttendanceOvertimeSyncService` | Sinkronisasi data lembur | Daily |

---

## 7. Modul 5: Payroll

**Status saat ini:** 🔶 Hanya PayPeriod model + controller
**Referensi hris-system:** 17+ models, 10 controllers, 7 services, 3 calculators

### 7.1 Ringkasan Alur Data Payroll

```
┌──────────┐   ┌──────────┐   ┌──────────┐   ┌──────────┐
│ PERIODE  │──►│ GENERATE │──►│ REVIEW   │──►│ LOCK &   │
│ (bulanan)│   │ payroll  │   │ + koreksi│   │ CLOSE    │
└──────────┘   └──────────┘   └──────────┘   └──────┬───┘
                                                     │
                                                     ▼
                                            ┌──────────┐   ┌──────────┐
                                            │ PAYSLIP  │   │ EXPORT   │
                                            │ (print)  │   │ Excel    │
                                            └──────────┘   └──────────┘
```

### 7.2 Tabel Database (Target)

| Tabel Baru | Tabel Lama (hris-system) | Perubahan |
|-----------|-------------------------|-----------|
| `pay_settings` | `payroll_settings` | Rename |
| `pay_configs` | `payroll_configs` | Rename |
| `pay_periods` | `payroll_periods` | ✅ Sudah ada |
| `pay_records` | `payrolls` + `payroll_results` + `payroll_employee_snapshots` | **Gabung** |
| `pay_component_values` | `payroll_component_snapshots` + `payroll_breakdowns` | Gabung |
| `pay_audits` | `payroll_audits` | Untuk supervisor |
| `pay_components` | `salary_components` | Rename — **sudah di Settings** |
| `emp_salary_components` | `employee_salary_components` | Rename — **sudah di Employee** |
| `pay_bpjs_configs` | `bpjs_configs` | Rename — **sudah di Settings** |
| `emp_bpjs` | `employee_bpjs` | Rename — **sudah di Employee** |
| `pay_pph_configs` | `pph_configs` | Rename — **sudah di Settings** |
| `pay_ptkp_rates` | `ptkp_rates` | Rename — **sudah di Settings** |
| `pay_ter_rates` | `ter_rates` | Rename — **sudah di Settings** |
| `pay_progressive_rates` | `progressive_rates` | Rename — **sudah di Settings** |
| `pay_service_allowances` | `service_year_allowances` | Rename — **sudah di Settings** |
| `emp_thr` | `employee_thr` | Rename — **sudah ada di Employee** |
| `emp_salary_breakdowns` | `employee_salary_breakdowns` | Untuk supervisor |

**Tabel yang DIHAPUS:**
- ~~`payroll_results`~~ → digabung ke `pay_records` (JSON field: earnings_breakdown, attendance_breakdown, deduction_breakdown)
- ~~`payroll_breakdowns`~~ → JSON field di `pay_records`
- ~~`payslips`~~ → di-generate dari `pay_records`, bukan tabel
- ~~`payroll_component_snapshots`~~ → data ada di `pay_component_values`
- ~~`payroll_employee_snapshots`~~ → embed di kolom `pay_records`

### 7.3 Struktur Tabel PayRecords (Kunci Utama)

```php
pay_records
├── id
├── pay_period_id (FK → pay_periods)
├── employee_id (FK → employees)
│
├── # SNAPSHOT DATA (di-copy dari master saat generate, biar gak berubah)
├── employee_number, full_name, department_name, position_name
├── bank_name, bank_account, bank_holder
├── join_date, ptkp_status
│
├── # KOMPONEN GAJI (dari emp_salaries effective date)
├── base_salary          DECIMAL(15,2)
├── premi                DECIMAL(15,2)
├── tunjangan            DECIMAL(15,2)
├── tunjangan_masa_kerja DECIMAL(15,2)
│
├── # ABSENSI (dari att_records)
├── present_days         INT
├── absent_days          INT
├── sick_days            INT
├── leave_days           INT
├── late_minutes         INT
├── overtime_hours       DECIMAL(5,2)
│
├── # PERHITUNGAN
├── hourly_rate          DECIMAL(15,2)   // (base + tunjMK) / 173
├── attendance_earnings  DECIMAL(15,2)   // (premi / 25) * present_days
├── overtime_earnings    DECIMAL(15,2)   // overtime_hours * hourly_rate * multiplier
├── prorated_salary      DECIMAL(15,2)   // (base / 25) * present_days
├── gross_income         DECIMAL(15,2)   // prorated + overtime + attendance + tunjangan
│
├── # BPJS
├── bpjs_jht_employee    DECIMAL(15,2)
├── bpjs_jht_employer    DECIMAL(15,2)
├── bpjs_jp_employee     DECIMAL(15,2)
├── bpjs_jp_employer     DECIMAL(15,2)
├── bpjs_kesehatan       DECIMAL(15,2)
├── bpjs_jkk             DECIMAL(15,2)
├── bpjs_jkm             DECIMAL(15,2)
│
├── # PPH
├── pph_method           ENUM('ter', 'progressive')
├── ter_category         VARCHAR(5)
├── pph_amount           DECIMAL(15,2)
├── pph_dtp              BOOLEAN  // Ditanggung Pemerintah?
│
├── # THR
├── thr_amount           DECIMAL(15,2)
│
├── # TOTALS
├── total_deductions     DECIMAL(15,2)   // BPJS + PPH + lainnya
├── net_salary           DECIMAL(15,2)   // gross - deductions
│
├── # STATUS
├── status               ENUM('draft', 'calculated', 'approved', 'locked', 'paid')
├── notes                TEXT
│
├── uuid                 CHAR(36)
├── synced_at            TIMESTAMP
├── softDeletes
├── timestamps
```

### 7.4 Controller Mapping

| Controller Baru | Controller Lama (hris-system) | Baris | Fungsi |
|----------------|------------------------------|-------|--------|
| **PayrollApiController** | PayrollController | 633 | Generate, regenerate, edit payroll |
| **PayrollApiController** | PayrollConfigController | 109 | **Pisah ke Settings** |
| **PayrollApiController** | SalaryComponentController | 102 | **Pisah ke Settings** |
| **PayrollApiController** | BpjsConfigController | 149 | **Pisah ke Settings** |
| **PayrollApiController** | BpjsEmployeeController | 435 | BPJS per employee per periode |
| **PayrollApiController** | PphConfigController | 171 | **Pisah ke Settings** |
| **PayrollApiController** | PphManagementController | 369 | PPh recalculate |
| **PayrollApiController** | ThrController | 47 | Generate THR |
| **PayrollApiController** | PayrollExport | 103 | Export payroll |

**Strategi:** Satu controller utama `PayrollApiController` dengan method:
- **PayPeriod** — CRUD ✅ sudah ada
- **Payroll** — generate, regenerate, edit, lock, close, status
- **BPJS Employee** — generate BPJS per periode
- **PPH** — calculate, recalculate, DTP check
- **THR** — generate, prorate
- **Export** — Excel, PDF payslip

### 7.5 Services yang Diperlukan

| Service Baru | Service Lama (hris-system) | Baris | Prioritas |
|-------------|---------------------------|-------|-----------|
| **PayrollGenerationService** | PayrollGenerationService | 267 | 🔴 Must |
| **BpjsCalculator** | BpjsCalculator | 68 | 🔴 Must |
| **BpjsConfigService** | BpjsConfigService | — | 🟡 Should |
| **PphCalculator** | PphCalculator | 286 | 🔴 Must |
| **PphConfigService** | PphConfigService | — | 🟡 Should |
| **ThrService** | ThrService | — | 🟡 Should |
| **SalaryComponentService** | SalaryComponentService | — | 🟢 Bisa ditunda |
| **PayrollConfigService** | PayrollConfigService | — | 🟢 Bisa ditunda |

### 7.6 Kalkulasi Payroll Flow

```
1. HR buka periode payroll → klik "Generate"
2. PayrollGenerationService:
   a. Ambil semua employee aktif dalam periode
   b. Untuk setiap employee:
      - baseSalary(periode)        ← dari emp_salaries (effective date)
      - premi(periode)             ← dari emp_salaries
      - tunjanganMasaKerja(periode)← hitung dari join_date
      - tunjangan(periode)         ← dari emp_salaries
      - Ambil att_records periode  ← dari Attendance module
      - Hitung hourly_rate = (base + tunjMK) / 173
      - Hitung prorated = (base / 25) * present_days
      - Hitung attendance_earnings = (premi / 25) * present_days
      - Hitung overtime_earnings = overtime_hours * hourly_rate * multiplier
      - Hitung gross = prorated + attendance + overtime + tunjMK + tunjangan
   c. Hitung BPJS (BpjsCalculator):
      - JHT: config% × base (employer + employee)
      - JP: config% × base
      - Kesehatan: config% × base
      - JKK: config% × base (employer only)
      - JKM: config% × base (employer only)
   d. Hitung PPH (PphCalculator):
      - Jan–Nov: TER Method → PPh = Gross × TER Rate
      - December: Progressive → Bruto setahun - biaya jabatan(5%, max 6jt) - JHT - JP = Netto
        → Netto - PTKP = PKP → Tarif progresif (0-35%)
      - Cek DTP: gross <= 10jt → PPh = 0
      - Non-NPWP: multiplier
   e. Simpan ke pay_records
   f. Return summary: "Payroll periode Mei 2026: 150 karyawan, Rp 450,000,000"
```

### 7.7 API Endpoints

```
# Pay Periods
GET    /api/v1/payroll/periods              → ✅ sudah ada
POST   /api/v1/payroll/periods              → ✅ sudah ada
PUT    /api/v1/payroll/periods/{id}         → ✅ sudah ada
DELETE /api/v1/payroll/periods/{id}         → ✅ sudah ada

# Payroll Records
GET    /api/v1/payroll/records              → List payroll per periode
GET    /api/v1/payroll/records/{id}         → Detail payroll employee
POST   /api/v1/payroll/records/generate     → Generate payroll untuk periode
POST   /api/v1/payroll/records/{id}/lock    → Lock payroll employee
POST   /api/v1/payroll/records/lock-period  → Lock semua payroll periode
POST   /api/v1/payroll/records/{id}/close   → Close (ubah status ke paid)
POST   /api/v1/payroll/records/{id}/edit    → Edit manual
POST   /api/v1/payroll/records/regenerate   → Regenerate ulang

# BPJS
GET    /api/v1/payroll/bpjs                 → List BPJS per periode
POST   /api/v1/payroll/bpjs/generate        → Generate BPJS

# PPH
GET    /api/v1/payroll/pph                  → List PPh per periode
POST   /api/v1/payroll/pph/calculate        → Calculate ulang PPh

# THR
GET    /api/v1/payroll/thr                  → List THR
POST   /api/v1/payroll/thr/generate         → Generate THR

# Export
GET    /api/v1/payroll/export/excel         → Export Excel
GET    /api/v1/payroll/export/payslip/{id}  → Download payslip PDF
```

---

## 8. Modul 6: Reports

**Status saat ini:** ❌ Kosong — hanya route file
**Referensi hris-system:** 4 controllers, ~15 export classes

### 8.1 Jenis Laporan

| No | Laporan | Controller (hris-system) | Export |
|----|---------|-------------------------|--------|
| 1 | **Rekap Payroll** (per periode) | ReportController::payroll() | Excel, HTML |
| 2 | **Rekap PPh 21** (per tahun/per periode) | ReportController::tax() | Excel per employee, PDF |
| 3 | **Rekap BPJS** (per periode) | ReportController::bpjs() | Excel |
| 4 | **Rekap Absensi** (per periode) | AttendanceReportController | Excel, PDF |
| 5 | **Laporan Lembur Bulanan** | LaporanLemburController::bulanan() | Excel, Print |
| 6 | **Laporan Lembur Harian** | LaporanLemburController::harian() | Excel, Print |
| 7 | **Laporan Uang Makan** | UangMakanReportController | Excel, PDF |
| 8 | **Payroll Template Print** | ReportController::payrollTemplatePrint() | HTML/PDF |

### 8.2 Strategi Implementasi

- **1 controller utama:** `ReportApiController` dengan method per tipe laporan
- **Backend:** Logic agregasi + export (Excel/PDF) di service layer
- **Frontend:** Halaman filter (periode, department, tipe) → panggil API → download file
- **Export Library:** `maatwebsite/laravel-excel` (udah biasa dipake)

### 8.3 API Endpoints

```
GET  /api/v1/reports/payroll?period_id=...             → Rekap payroll
GET  /api/v1/reports/payroll/export?period_id=...      → Download Excel payroll
GET  /api/v1/reports/tax?period_id=...&year=...        → Rekap PPh 21
GET  /api/v1/reports/bpjs?period_id=...                → Rekap BPJS
GET  /api/v1/reports/attendance?period_id=...&dept=... → Rekap absensi
GET  /api/v1/reports/overtime/monthly?month=...        → Lembur bulanan
GET  /api/v1/reports/overtime/daily?date=...           → Lembur harian
GET  /api/v1/reports/meal-allowance?period_id=...      → Uang makan
```

---

## 9. Modul 7: AuditSection / Supervisor Dashboard

**Status saat ini:** ❌ **Kosong total**
**Referensi hris-system:** 12 controllers, 3 services, ~6.000+ baris kode

### 9.1 Konsep

Supervisor Dashboard adalah **aplikasi bayangan** untuk eksternal auditor/inspector. Memiliki perhitungan sendiri yang independen dari aplikasi utama. Semua data disimpan di tabel dengan prefiks yang sama tapi logic perhitungan terpisah.

### 9.2 Sub-Modul Supervisor

| No | Sub-Modul | Controller (hris-system) | Baris | Fungsi |
|----|-----------|-------------------------|-------|--------|
| 1 | **Salary Breakdown** | SalaryBreakdownController | **1207** | Generate, import premi, export Excel/PDF |
| 2 | **Staff Overtime** | StaffOvertimeController | **777** | Index, export, print, detail |
| 3 | **Attendance Autolog** | AttendanceAutologController | **770** | Index, export, print, adjustment |
| 4 | **Attendance Snapshot** | AttendanceSnapshotController | **644** | Index, store, storeBulk, print |
| 5 | **Payroll Management** | PengelolaanGajiController | **570** | Index, generate, update, destroy |
| 6 | **PPh Management** | PphManagementController | **371** | Index, generate, exportPdf |
| 7 | **THR Management** | ThrManagementController | **377** | Index, generate, update, destroy |
| 8 | **BPJS Management** | BpjsManagementController | **312** | Index, generate, exportPdf |
| 9 | **Attendance Recap** | RekapAbsensiController | **354** | Index, export, print |
| 10 | **Attendance Audit** | AttendanceAuditController | **225** | Index, updateOvertime, roster |
| 11 | **Raw Log Viewer** | RawLogController | **170** | Index, importForm, scanDetails |
| 12 | **File Manager** | FileManagerController | **206** | Upload, folder, delete, rename |
| 13 | **Scan Config** | ScanDetectionConfigController | **35** | CRUD + global config |

### 9.3 Tabel Supervisor

| Tabel | Fungsi |
|-------|--------|
| `att_snapshots` | Snapshot absensi versi supervisor |
| `emp_salary_breakdowns` | Rincian gaji versi supervisor |
| `emp_thr` | THR versi supervisor |
| `pay_audits` | Payroll versi supervisor |
| `pay_component_values` | Komponen payroll supervisor |

### 9.4 Strategi Implementasi

1. **Backend:** Buat route prefix `/api/v1/supervisor/*`
2. **Controller:** 1 controller utama `SupervisorApiController` (tidak perlu 12 controller terpisah)
3. **Services:** Port `AuditorLogService` + `AttendanceOvertimeSyncService` + `FingerprintBinParser` (jika perlu)
4. **Frontend:** Pages di `pages/supervisor/*` dengan layout terpisah `SupervisorLayout.vue`
5. **Tabel:** Gunakan tabel yang sama dengan app utama untuk data master, tabel terpisah untuk data perhitungan

### 9.5 API Endpoints

```
# Supervisor
GET    /api/v1/supervisor/dashboard           → Ringkasan supervisor
GET    /api/v1/supervisor/attendance          → Attendance snapshot
POST   /api/v1/supervisor/attendance/snapshot → Generate snapshot
GET    /api/v1/supervisor/salary-breakdown    → Salary breakdown
POST   /api/v1/supervisor/salary-breakdown/generate
GET    /api/v1/supervisor/payroll             → Payroll audit
POST   /api/v1/supervisor/payroll/generate
GET    /api/v1/supervisor/pph                 → PPh management
POST   /api/v1/supervisor/pph/generate
GET    /api/v1/supervisor/bpjs                → BPJS management
POST   /api/v1/supervisor/bpjs/generate
GET    /api/v1/supervisor/thr                 → THR management
POST   /api/v1/supervisor/thr/generate
GET    /api/v1/supervisor/overtime            → Staff overtime
GET    /api/v1/supervisor/recap               → Rekap absensi
GET    /api/v1/supervisor/raw-logs            → Raw log viewer
POST   /api/v1/supervisor/file-manager/upload → Upload file
```

---

## 10. Prioritas Eksekusi

### Fase-fase (Update dari MIGRATION_GUIDE.md)

| Fase | Modul | Estimasi | Ketergantungan |
|------|-------|----------|---------------|
| **5** | Schedule (penyempurnaan) | 1 minggu | Fase 4 (Employee) ✅ |
| **5b** | Settings (penyempurnaan) | 1 minggu | Fase 2 (Organization) ✅ |
| **6** | Leave (penyempurnaan) | 1 minggu | Fase 4 (Employee) ✅ |
| **7** | **Attendance** | **3-4 minggu** | Fase 4 (Employee) ✅, Fase 5 (Schedule) |
| **8** | **Payroll** | **3-4 minggu** | Fase 4 (Employee), Fase 6 (Leave), Fase 7 (Attendance) |
| **9** | **Reports** | **1-2 minggu** | Fase 8 (Payroll), Fase 7 (Attendance) |
| **10** | **Supervisor Dashboard** | **2-3 minggu** | Fase 8 (Payroll), Fase 7 (Attendance) |

**Total estimasi: 12-16 minggu (~3-4 bulan)**

### Prioritas Berdasarkan Dependencies

```
Schedule ──► Attendance ──► Payroll ──► Reports ──► Supervisor
                │               │
                ▼               ▼
Settings ──► Leave ────────────┘
```

### Rekomendasi Urutan Pengerjaan

1. **Schedule** (tinggal service + validasi)
2. **Settings** (tinggal API untuk config yang belum)
3. **Leave** (tinggal beberapa model + service)
4. **ATTENDANCE** — ini yang paling berat, mulai dari:
   - Migration tabel `att_*` (8 tabel)
   - Model + Resource
   - ImportService (csv/xlsx)
   - ProcessService (auto-matching)
   - Review workflow + API
   - Overtime + API
5. **PAYROLL** — kedua terberat:
   - Migration tabel `pay_*` (6 tabel baru)
   - Model + Resource
   - PayrollGenerationService (kalkulasi)
   - BPJS Calculator
   - PPH Calculator
   - Export payslip
6. **Reports** — relatif ringan:
   - Agregasi query + export Excel/PDF
7. **Supervisor** — paling akhir:
   - Tabel shadow + snapshot
   - Logic perhitungan independen

---

## 11. Yang Perlu Diperhatikan dari hris-system

### 11.1 Kode Paling Kritis untuk Di-port

| File | Baris | Module | Alasan Kritis |
|------|-------|--------|--------------|
| `AttendanceSyncService.php` | 989 | Attendance | Logic sync attendance paling kompleks |
| `SalaryBreakdownController.php` | 1207 | AuditSection | Terbesar, logic generate breakdown |
| `PayrollController.php` | 633 | Payroll | Logic generate payroll utama |
| `PayrollGenerationService.php` | 267 | Payroll | Calculator service |
| `PphCalculator.php` | 286 | Payroll | Logic PPh TER + progressive |
| `StaffOvertimeController.php` | 777 | AuditSection | Manajemen lembur staff |
| `AttendanceAutologController.php` | 770 | AuditSection | Autolog processing |
| `AttendanceSnapshotController.php` | 644 | AuditSection | Snapshot management |
| `AttendanceRosterController.php` | 727 | Attendance | Roster management (dipindah ke Schedule) |
| `OvertimeCalculationController.php` | 330 | Attendance | Logic perhitungan lembur |
| `BpjsEmployeeController.php` | 435 | Payroll | BPJS per employee |

### 11.2 Logic yang Tidak Perlu Di-port

| Logic | Alasan |
|-------|--------|
| `company_id` / `branch_id` scoping | 1 instance = 1 DB |
| Multi-tenancy session switch | Tidak relevan |
| Blade views | Frontend pake Vue SPA |
| Inertia.js responses | Pake REST API |
| `employee_periodes` | Diganti effective date pattern |
| `permit_requests` | Izin via Leave module |
| `company_settings` / `branch_settings` | Digabung ke `system_settings` |

### 11.3 Logic yang Perlu Disesuaikan

| Logic Lama | Penyesuaian |
|-----------|------------|
| Shift/shift_schedules di Attendance | Pindah ke Schedule module |
| Holiday/WorkingCalendar di Attendance | Pindah ke Schedule module |
| Leave/permit di Attendance | Pindah ke Leave module |
| OvertimeRules di Attendance | Shared — setting di Settings, dipake Attendance & Payroll |
| SalaryGrade di Organization | Pindah ke Settings |
| SalaryComponent di Payroll | Pindah ke Settings |

---

## 12. Ringkasan Migration Tables

| Module | Tabel Baru | Tabel Dihapus | Total Tambahan |
|--------|-----------|--------------|----------------|
| Schedule | `sch_pattern_types`, `sch_patterns`, `sch_pattern_details`, `sch_shifts`, `sch_rosters`, `sch_calendars`, `sch_holidays` | — | Rename dari existing (7 tabel) |
| Leave | `lve_period_configs`, `lve_periods`, `lve_types`, `lve_policies`, `lve_requests`, `lve_documents`, `lve_entitlements`, `lve_balances` | — | Rename + tambah (8 tabel) |
| Attendance | `att_raw_logs`, `att_logs`, `att_autologs`, `att_prepares`, `att_records`, `att_summaries`, `att_snapshots`, `att_consecutive_days`, `att_configs`, `att_scan_configs`, `att_overtimes` | `permit_requests`, `permit_types`, `employee_shifts`, `shift_schedules` | 11 baru - 4 hapus |
| Payroll | `pay_settings`, `pay_configs`, `pay_periods`, `pay_records`, `pay_component_values`, `pay_audits` | `payroll_results`, `payroll_breakdowns`, `payslips`, `payroll_component_snapshots`, `payroll_employee_snapshots` | 6 baru - 5 hapus |
| Reports | — | — | Query-based |
| AuditSection | (tabel shadow yang sudah ada) | — | Yang sudah ada |

**Total net tambahan: ~25 tabel baru, ~9 tabel dihapus**

---

## 13. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|--------|--------|----------|
| **AttendanceSyncService (989 baris)** kompleks | Butuh waktu lama porting | Baca dulu semua logic, bikin test dulu |
| **Payroll calculation error** | Gaji karyawan salah | Unit test untuk setiap kalkulator, bandingkan hasil dengan hris-system |
| **Data migration existing** | Data Leave/Schedule/Attendance yang udah ada di DB baru ilang | Backup dulu, migration script |
| **Effective date pattern** di EmployeeSalary | Logic baru, rentan bug | Port accessors dari hris-system yang sudah teruji |
| **THR calculation** beda tiap perusahaan | Konfigurasi kurang fleksibel | Port logic prorate dari hris-system |
| **PPH TER vs Progressive** | Hitungan pajak salah | Testing dengan data real dari hris-system, bandingkan output |
| **Supervisor vs Main data mismatch** | Auditor komplain | Mapping jelas tabel mana untuk konteks mana |

---

## 14. Tindak Lanjut

Setelah PRD ini disetujui, langkah selanjutnya:

1. ✅ PRD Attendance — **sudah ada** (PRD_ATTENDANCE.md) — tinggal eksekusi
2. 📝 Buat PRD Payroll — detail kalkulasi + workflow
3. 📝 Buat PRD Reports — detail tipe laporan + format export
4. 📝 Buat PRD Supervisor — detail fitur aplikasi bayangan
5. 🏗️ Eksekusi per fase sesuai prioritas

---

> *Dokumen ini adalah living document — akan di-update seiring perkembangan aplikasi.*
>
> *Referensi utama: `D:\laragon\www\hris-system` — 79 controllers, ~78 models, ~47 services*
>
> *Dibuat dengan ❤️ oleh Paijo untuk Sigit — 2 Juni 2026*
