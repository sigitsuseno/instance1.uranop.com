# Migrasi HRIS — Multi-Tenancy → Multi-Instance (Isolated)

## Ringkasan

Aplikasi lama (`hris-system`) menggunakan model **multi-tenancy** (1 database, difilter `company_id` + `branch_id` via global scope).  
Aplikasi baru (`instance1.uranop.com`) menggunakan model **multi-instance terisolasi**:

```
Owner
  └── Company (header/grouping label saja)
        └── Branch = Instance (operasional aktual)
              └── 1 Instance = 1 Branch = 1 DB = 1 Deploy Laravel
```

### Perbedaan Kunci

| Aspek | Lama (hris-system) | Baru (instance1.uranop.com) |
|---|---|---|
| **Isolasi** | Global scope `company_id` + `branch_id` | 1 DB per instance, tidak perlu scope |
| **Tenancy** | Session-based company+branch switch | Tidak ada; instance terisolasi penuh |
| **Frontend** | Inertia.js 2 + Vue 3 | Vue 3 SPA (Vue Router), tanpa Inertia |
| **Backend** | Blade + Inertia hybrid | Pure REST API + Web catch-all SPA |
| **Backend Structure** | `app/Modules/*/` | `app/Modules/*/` (dipertahankan) |
| **Roles** | super_admin, hr_area, hr_branch, staff | superadmin, hrmanager, adm_manager, hr_ast, hrbranch |
| **Dashboard** | 1 dashboard (role-based views) | 2 dashboard: Admin + Supervisor |
| **Company/Branch** | `company_id` + `branch_id` di semua tabel | **Dihapus total.** Tabel companies & branches tetap ada sebagai data master, tanpa relasi ke tabel lain |
| **Instance** | Tidak ada | Tabel baru: `instances` |
| **Bahasa** | Mixed ID/EN | Indonesia (technical terms tetap EN: "generate", "import", dll) |
| **Arsitektur** | Web-only | Backend API + Desktop App (offline-first, sync) |

---

## Konsep Arsitektur

### Dua Mesin Aplikasi

Aplikasi ini memiliki **dua mesin perhitungan** yang berjalan di satu codebase:

| Mesin | Nama | Dashboard | Tujuan |
|---|---|---|---|
| **Mesin 1** | Aplikasi Utama | Admin Dashboard | Hitungan real — payroll, attendance, leave, dll |
| **Mesin 2** | Aplikasi Bayangan | Supervisor Dashboard | Hitungan khusus untuk external auditor/inspector |

- **Aplikasi Utama** menggunakan tabel-tabel utama (`pay_records`, `att_prepares`, dll)
- **Aplikasi Bayangan** menggunakan tabel-tabel khusus (`pay_audits`, `att_snapshots`, `emp_salary_breakdowns`, dll)
- Masing-masing memiliki **proses perhitungan sendiri** yang independen
- `audit_logs` mencatat perubahan di kedua konteks, difilter by `context` column

### Desktop Sync Architecture

Aplikasi ini berfungsi sebagai:
1. **Backend API** — endpoint utama untuk desktop app dan web
2. **Online Backup** — fallback jika desktop offline
3. **Sync Server** — desktop app (offline-first) sync data ke server ini

Untuk mendukung sync, semua tabel data utama memiliki:
- `uuid` — unique identifier untuk sync antar device
- `synced_at` — timestamp terakhir sync

> **Catatan:** Detail implementasi sync (conflict resolution, delta sync, dll) akan direncanakan terpisah setelah backend core selesai.

---

## Konvensi Global

### Naming Convention Tabel

| Aspek | Konvensi |
|---|---|
| **Prefix tabel** | Per module: `{module}_` (ex: `att_`, `pay_`, `lve_`, `sch_`, `emp_`, `org_`, `set_`) |
| **Singular/Plural** | **Plural** untuk tabel data, **singular** untuk config |
| **Soft delete** | `deleted_at` (semua tabel data) |
| **Timestamps** | `created_at`, `updated_at` (semua tabel) |
| **Foreign key** | `{name}_id` |
| **Pivot table** | `{a}_{b}` (singular) |
| **Sync fields** | `uuid` (char 36) + `synced_at` (nullable timestamp) pada tabel data utama |

### Naming Convention Permission

Format: `{verb} {resource}` — semua lowercase, pakai spasi.

```
✅ view companies
✅ create departments
✅ manage leave types
❌ manage_leave_types  (jangan pakai underscore)
❌ view salary_grades   (jangan pakai underscore di resource)
```

### Konvensi Bahasa

- **Menu & label**: Bahasa Indonesia
- **Technical terms**: Tetap EN jika terjemahan Indonesia ambigu
  - `generate` (bukan "generasi"), `import` (bukan "impor"), `export` (bukan "ekspor")
  - `attendance` → "Kehadiran"
  - `payroll` → "Generate Gaji" (label menu)
  - `leave` → "Cuti"
  - `approval` → "Approval"

### Effective Date Pattern

Data yang berubah seiring waktu disimpan dengan `effective_date`. Untuk mendapatkan data di bulan tertentu, ambil record terbaru sebelum tanggal tersebut.

```php
class Employee extends Model
{
    // $employee->activeContract()
    public function activeContract()
    {
        return $this->contracts()
            ->where('start_date', '<=', now())
            ->where(fn($q) => $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', now()))
            ->latest('start_date')
            ->first();
    }

    // $employee->baseSalary()          → gaji bulan ini
    // $employee->baseSalary('2026-03') → gaji bulan Maret 2026
    public function baseSalary(?string $period = null): float
    {
        $date = $period
            ? Carbon::parse($period . '-01')->endOfMonth()
            : now();

        return $this->salaries()
            ->where('effective_date', '<=', $date)
            ->latest('effective_date')
            ->value('base_salary') ?? 0;
    }

    // $employee->currentPosition()
    public function currentPosition()
    {
        return $this->positionHistories()
            ->where('effective_date', '<=', now())
            ->latest('effective_date')
            ->first();
    }
}
```

Dengan pattern ini, **tabel `employee_periodes` dihapus** — data bisa dihitung dari `emp_salaries`, `emp_contracts`, dan `emp_position_histories` menggunakan effective date.

---

## Fase Eksekusi

> **Catatan:** Setiap fase mencakup **backend + frontend** secara bersamaan.
> **Prioritas:** Admin Dashboard dikerjakan terlebih dahulu. Supervisor Dashboard menyusul setelah fase 9.

---

### Fase 0: Pondasi

**Tujuan:** Setup project, struktur modular, infrastruktur dasar frontend, shared traits, audit log.

#### 0.1 Module Structure

Struktur folder backend **wajib modular**:

```
app/Modules/{Module}/
├── Controllers/
│   ├── Api/
│   │   └── V1/
│   │       ├── {Module}ApiController.php
│   │       └── SubModule/
│   │           └── {SubModule}ApiController.php
│   └── Web/
│       └── {Module}WebController.php
├── Models/
│   └── {Model}.php
├── Resources/
│   └── {Module}Resource.php
├── Routes/
│   ├── web.php
│   └── api.php
├── Services/
│   └── {Module}Service.php
└── Providers/
    └── {Module}ServiceProvider.php
```

#### 0.2 Auto-Load Module Routes

```php
// bootstrap/app.php
->withRouting(
    ...
    then: function () {
        Route::prefix('api')
            ->middleware('api')
            ->group(function () {
                $modules = glob(app_path('Modules/*/Routes/api.php'));
                foreach ($modules as $routeFile) {
                    require $routeFile;
                }
            });
    },
)
```

#### 0.3 Shared Traits (Port dari hris-system)

Traits yang akan di-port dari `app/Modules/Shared/Traits/`:

| Trait | Fungsi | Status |
|---|---|---|
| `HasAuditLog` | Auto audit trail logging | `done` |
| `HasCache` | Caching helper | `done` |
| `HasEffectiveDate` | Effective date scopes & accessors | `done` |
| `HasExport` | Export helper (Excel/PDF) | `done` |
| `HasHierarchy` | Parent-child tree (departments, dll) | `done` |
| `HasSearch` | Search scope | `done` |
| `HasStatus` | Status scope/methods | `done` |
| `HasUserContext` | Auto-fill created_by/updated_by | `done` |

Traits yang **DIHAPUS** (tidak perlu di multi-instance):
- ~~`HasCompanyScope`~~ — tidak perlu, 1 instance = 1 DB
- ~~`HasBranchScope`~~ — tidak perlu, 1 instance = 1 branch

#### 0.4 Module AuditLog

Tabel `audit_logs` untuk mencatat perubahan data di kedua konteks (aplikasi utama & bayangan).

| Item | Status |
|---|---|
| Migration `audit_logs` | `done` |
| Model AuditLog | `done` |
| Controller AuditLogApiController | `done` |
| Routes AuditLog/Routes/api.php | `done` |

Struktur tabel:
```
audit_logs
├── id
├── context: enum('main', 'shadow')   ← pembeda aplikasi utama vs bayangan
├── user_id (FK → users)
├── action: string (create, update, delete)
├── auditable_type: string (model class)
├── auditable_id: unsignedBigInteger
├── old_values: json (nullable)
├── new_values: json (nullable)
├── ip_address: string(45)
├── user_agent: text
├── timestamps
```

- Di **Admin Dashboard**: tampil audit_log semua context
- Di **Supervisor Dashboard**: tampil audit_log hanya `context = 'shadow'`

#### 0.5 Checklist Fase 0

- [x] Laravel 13 project `instance1.uranop.com` terinisialisasi
- [x] Vue 3 SPA terinstall (Vue Router + Tailwind 4 + theme + dark mode)
- [x] API routes dasar + Sanctum terinstall
- [x] `.env` local & production terkonfigurasi
- [x] Auto-load module routes di `bootstrap/app.php`
- [x] Global Components: BaseButton, BaseModal, BaseCard, TextInput, SelectInput, Badge, ConfirmDialog, DataTable, Pagination, Dropdown, Icons (27 SVG), NotificationToast
- [x] Composables: useApi, useAuth, useTheme, useDate, useCurrency, usePagination, useNotification, usePermission
- [x] Pinia Stores: auth, permission, notification
- [x] Vue Router (index.js) dengan semua route definitions
- [x] Spatie Permission middleware aliases (`role`, `permission`, `role_or_permission`)
- [x] SPA catch-all route (`/{any}` → `welcome.blade.php`)
- [x] `config/instance.php` — konfigurasi instance ID, name, slug
- [x] Module Instance — migration `instances` + model + seeder (untuk management console multi-instance ke depan)
- [x] Setup folder structure untuk semua module (template kosong)
- [x] Shared Traits: port 8 traits dari hris-system
- [x] Module AuditLog: migration `audit_logs` + model + trait `HasAuditLog`

---

### Fase 1: Auth, Layout, Dashboard

**Tujuan:** Autentikasi, layout dasar aplikasi, halaman login, dashboard admin, notifications.
**Dependencies:** Fase 0

#### 1.1 Backend

| Item | Status | Keterangan |
|---|---|---|
| Migration users, password_reset_tokens, sessions | `done` | Custom fields: employee_number, phone, is_active, user_type (enum), softDeletes |
| Model User (HasApiTokens, HasRoles) | `done` | `app/Modules/Auth/Models/User.php` |
| Model UserPreference | `done` | `app/Modules/Auth/Models/UserPreference.php` |
| Controller AuthApiController (login, logout, user) | `done` | `app/Modules/Auth/Controllers/Api/V1/AuthApiController.php` |
| Routes Auth/Routes/api.php | `done` | POST /login, POST /logout, GET /user |
| AuthResource | `done` | `app/Modules/Auth/Resources/AuthResource.php` |
| AuthServiceProvider | `stub` | `app/Modules/Auth/Providers/AuthServiceProvider.php` — masih kosong |
| Migration `notifications` (Laravel standard) | `done` | UUID primary key, morphs notifiable, data text, read_at |
| Model Notification | `done` | |
| Controller NotificationApiController | `done` | |

#### 1.2 Frontend

| Item | Status | Keterangan |
|---|---|---|
| App.vue + app.js (Vue bootstrapper) | `done` | |
| GuestLayout.vue | `done` | |
| RootLayout.vue | `done` | Auth fetch on mount, landing page redirect |
| AuthenticatedLayout (AdminLayout) | `done` | `resources/js/Layouts/Admin/` — Sidebar, Topbar, Footer |
| Login.vue | `done` | Form login dengan validasi, error handling |
| Admin/Dashboard.vue | `done` | Stat cards, recent leave, contracts (mock data) |
| useAuth.js + auth store | `done` | Login/logout/user state + computed role checks |
| Router auth guard | `done` | `router.beforeEach` — redirect ke /login, role-based redirect |

#### 1.3 Checklist Fase 1

- [x] Auth backend (User, login, logout, user endpoint)
- [x] Auth frontend (Login page, useAuth, route guard)
- [x] Layout Admin (Sidebar, Topbar, Footer)
- [x] Admin Dashboard page (mock data)
- [x] AuthServiceProvider — isi dengan logic jika diperlukan
- [x] Migration + Model + Controller: Notifications
- [x] Frontend: Notification center page

---

### Fase 2: Organization, User Role & Permission

**Tujuan:** Manajemen organisasi (departemen, jabatan), serta sistem role & permission.
**Dependencies:** Fase 0, Fase 1

> **Catatan:** Salary Grades dipindahkan ke Fase 3 (Settings) karena bersifat opsional — tidak semua perusahaan menggunakan salary grade.

#### 2.1 Backend

| Item | Status | Keterangan |
|---|---|---|
| Migration permission_tables (Spatie) | `done` | roles, permissions, model_has_roles, dll |
| Seeder RolePermissionSeeder | `done` | 5 roles + permissions (lihat section Roles & Permissions) |
| Seeder UserSeeder | `done` | 3 default users (superadmin, hr, adm_manager) |
| Migration Organization (departments, positions) | `done` | Tanpa company_id/branch_id |
| Model Department, Position | `done` | |
| Controller OrganizationApiController (CRUD) | `done` | |
| Routes Organization/Routes/api.php | `done` | |
| Resources: DepartmentResource, PositionResource | `done` | |
| Seeder Organization (data default) | `done` | |

#### 2.2 Frontend

| Item | Status | Keterangan |
|---|---|---|
| Departments/Index.vue + Form | `done` | CRUD dengan mock data |
| Positions/Index.vue + Form | `done` | CRUD dengan mock data |
| SalaryGrades/Index.vue + Form | `done` | CRUD dengan mock data (pindah ke Settings UI) |

#### 2.3 Checklist Fase 2

- [x] Spatie permission tables migration
- [x] RolePermissionSeeder (5 roles + permissions)
- [x] UserSeeder (3 default users)
- [x] Frontend: Departments CRUD (mock data)
- [x] Frontend: Positions CRUD (mock data)
- [x] Frontend: SalaryGrades CRUD (mock data — akan masuk Settings)
- [x] Organization migration: departments, positions (tanpa company_id/branch_id)
- [x] Model: Department, Position
- [x] Controller: OrganizationApiController
- [x] Routes: Organization/Routes/api.php
- [x] Resources: DepartmentResource, PositionResource
- [x] Seeder: Organization default data
- [x] Frontend: Integrasi Department CRUD dengan API nyata
- [x] Frontend: Integrasi Position CRUD dengan API nyata

---

### Fase 3: Data Master, Config, Setting, Konstanta

**Tujuan:** Semua tabel config, setting, konstanta, salary grades (opsional), dan data master.
**Dependencies:** Fase 0, Fase 1, Fase 2

#### 3.1 Cakupan

Module **Settings** dan semua data master/config:

- System settings
- Salary grades + salary grade histories (opsional, dikonfigurasi via settings)
- Employee groups, employee titles
- Salary components
- BPJS configs, PPH configs, PTKP rates, TER rates, progressive rates
- Overtime rules (shared — dipakai attendance & payroll)
- Service year allowances

#### 3.2 Backend

| Item | Status |
|---|---|
| Migration system_settings | `not started` |
| Migration salary_grades, salary_grade_histories | `not started` |
| Migration employee_groups, employee_titles | `not started` |
| Migration salary_components | `not started` |
| Migration bpjs_configs, pph_configs, ptkp_rates, ter_rates, progressive_rates | `not started` |
| Migration overtime_rules (shared, satu tabel) | `not started` |
| Migration service_year_allowances | `not started` |
| Model: SystemSetting, SalaryGrade, SalaryGradeHistory, EmployeeGroup, EmployeeTitle | `not started` |
| Controller SettingsApiController | `not started` |
| Routes Settings/Routes/api.php | `not started` |

#### 3.3 Frontend

| Item | Status |
|---|---|
| Settings/Index.vue | `done` (mock data) |
| Payroll/Configs/Index.vue | `done` (mock data) |
| SalaryGrades/Index.vue + Form | `done` (mock data, dipindah dari Organization) |

#### 3.4 Checklist Fase 3

- [x] Frontend: Settings page (mock)
- [x] Frontend: Payroll Configs page (mock)
- [x] Frontend: SalaryGrades page (mock)
- [x] Migration + Model: system_settings
- [x] Migration + Model: salary_grades, salary_grade_histories
- [x] Migration + Model: employee_groups, employee_titles
- [x] Migration + Model: salary_components
- [x] Migration + Model: bpjs_configs, pph_configs, ptkp_rates, ter_rates, progressive_rates
- [x] Migration + Model: overtime_rules (shared)
- [x] Migration + Model: service_year_allowances
- [x] Controller: SettingsApiController
- [x] Routes: Settings/Routes/api.php
- [x] Frontend: Integrasi Settings dengan API nyata
- [x] Frontend: Integrasi Configs dengan API nyata
- [x] Frontend: Integrasi SalaryGrades dengan API nyata

---

### Fase 4: Employee / Karyawan

**Tujuan:** CRUD karyawan, kontrak, keluarga, dokumen, gaji (effective date pattern), terminasi, riwayat jabatan.
**Dependencies:** Fase 2 (Organization), Fase 3 (Data Master)

> **Catatan:** Tabel `employee_periodes` **dihapus**. Data per bulan dihitung menggunakan **Effective Date Pattern** dari `emp_salaries`, `emp_contracts`, dan `emp_position_histories`. Snapshot hanya dibuat saat generate payroll.

#### 4.1 Backend

| Item | Status |
|---|---|
| Migration employees (+ uuid, synced_at) | `not started` |
| Migration employee_contracts | `not started` |
| Migration employee_families | `not started` |
| Migration employee_documents | `not started` |
| Migration employee_salaries (+ effective_date) | `not started` |
| Migration employee_salary_components | `not started` |
| Migration employee_salary_breakdowns | `not started` |
| Migration employee_position_histories | `not started` |
| Migration employee_terminations | `not started` |
| Migration employee_bpjs | `not started` |
| Migration employee_thr | `not started` |
| Model: Employee (+ effective date accessors) | `not started` |
| Model: Contract, Family, Document, Salary, Termination, dll | `not started` |
| Controller EmployeeApiController (CRUD + import + export) | `not started` |
| Routes Employee/Routes/api.php | `not started` |
| Resources: EmployeeResource | `not started` |

#### 4.2 Frontend

| Item | Status |
|---|---|
| Employees/Index.vue | `done` (mock data) |
| Employees/Create.vue | `done` (mock data) |
| Employees/Show.vue | `done` (mock data) |
| Employees/Edit.vue | `done` (mock data) |

#### 4.3 Checklist Fase 4

- [x] Frontend: Employee Index (mock)
- [x] Frontend: Employee Show (mock)
- [x] Frontend: Employee Create (mock)
- [x] Frontend: Employee Edit (mock)
- [x] Migration: semua tabel employee (tanpa employee_periodes)
- [x] Model Employee: effective date accessors (baseSalary, activeContract, currentPosition)
- [x] Model: semua model employee
- [x] Controller: EmployeeApiController
- [x] Routes: Employee/Routes/api.php
- [x] Resources: EmployeeResource
- [x] Service: EmployeeService
- [x] Frontend: Integrasi Employee Index dengan API
- [x] Frontend: Integrasi Employee Create/Edit/Show dengan API
- [x] Frontend: Import Excel page terintegrasi (Submodule Import)
- [x] Frontend: Contract, Family, Document sub-pages terintegrasi
- [x] Test: import Excel karyawan

---

### Fase 5: Schedule / Jadwal Kerja

**Tujuan:** Work patterns, shift, roster, kalender kerja, hari libur.
**Dependencies:** Fase 2 (Organization), Fase 4 (Employee)

#### 5.1 Backend

| Item | Status |
|---|---|
| Migration work_pattern_types, work_patterns, work_pattern_details | `not started` |
| Migration shifts | `not started` |
| Migration employee_shift_rosters | `not started` |
| Migration working_calendars, holidays | `not started` |
| Model: WorkPattern, Shift, Roster, Calendar, Holiday | `not started` |
| Controller ScheduleApiController | `not started` |
| Routes Schedule/Routes/api.php | `not started` |

#### 5.2 Frontend

| Item | Status |
|---|---|
| Schedule/WorkPatterns/Index.vue | `done` (mock data) |
| Schedule/Shifts/Index.vue | `done` (mock data) |
| Schedule/Calendars/Index.vue | `done` (mock data) |
| Schedule/Roster/Index.vue | `done` (mock data) |

#### 5.3 Checklist Fase 5

- [x] Frontend: WorkPatterns page (mock)
- [x] Frontend: Shifts page (mock)
- [x] Frontend: Calendars page (mock)
- [x] Frontend: Roster page (mock)
- [ ] Migration: semua tabel schedule
- [ ] Model: semua model schedule
- [ ] Controller: ScheduleApiController
- [ ] Routes: Schedule/Routes/api.php
- [ ] Resources: Schedule resources
- [ ] Frontend: Integrasi semua halaman schedule dengan API

---

### Fase 6: Leave / Cuti dan Izin

**Tujuan:** Pengajuan cuti & izin, approval, tipe cuti, kebijakan, entitlement, saldo cuti.
**Dependencies:** Fase 4 (Employee)

> **Catatan:** Izin (permit) ditangani melalui tabel `leave_types` dengan kolom `category`. Tidak ada tabel `permit_requests` terpisah.

#### 6.1 Penanganan Izin via Leave System

Di tabel `lve_types`, kolom `category` membedakan jenis:

| Category | Contoh |
|---|---|
| `leave` | Cuti tahunan, cuti bersama |
| `permit` | Izin pulang awal, izin terlambat, izin tidak masuk |
| `sick` | Sakit, sakit berkepanjangan |
| `special` | Cuti menikah, melahirkan, kematian keluarga |

#### 6.2 Backend

| Item | Status |
|---|---|
| Migration leave_types (+ category enum) | `not started` |
| Migration leave_policies | `not started` |
| Migration leave_period_configs | `not started` |
| Migration leave_periods | `not started` |
| Migration leave_requests, leave_documents | `not started` |
| Migration leave_entitlements (+ leave_period_id FK), leave_balances | `not started` |
| Model: semua model leave | `not started` |
| Controller LeaveApiController | `not started` |
| Routes Leave/Routes/api.php | `not started` |

#### 6.3 Frontend

| Item | Status |
|---|---|
| Leave/Index.vue | `done` (mock data) |
| Leave/Approvals.vue | `done` (mock data) |
| Leave/Settings.vue | `done` (mock data) |

#### 6.4 Checklist Fase 6

- [x] Frontend: Leave Index (mock)
- [x] Frontend: Leave Approvals (mock)
- [x] Frontend: Leave Settings (mock)
- [ ] Migration: semua tabel leave (termasuk category di leave_types)
- [ ] Model: semua model leave
- [ ] Controller: LeaveApiController
- [ ] Routes: Leave/Routes/api.php
- [ ] Resources: Leave resources
- [ ] Service: LeaveService (approval logic, balance calc)
- [ ] Frontend: Integrasi semua halaman leave dengan API

---

### Fase 7: Attendance / Kehadiran

**Tujuan:** Absensi, log import fingerprint, roster, lembur, snapshot, rekap.
**Dependencies:** Fase 4 (Employee), Fase 5 (Schedule)

> **Catatan:** `overtime_rules` sudah dibuat di Fase 3 (shared). `permit_requests` dihapus — izin ditangani lewat Leave module.

#### 7.1 Backend

| Item | Status |
|---|---|
| Migration raw_logs (data fingerprint mentah) | `not started` |
| Migration attendance_logs, attendance_autologs | `not started` |
| Migration attendance_prepares, attendance_records | `not started` |
| Migration attendance_snapshots, attendance_summaries | `not started` |
| Migration attendance_consecutive_days, attendance_configs | `not started` |
| Migration scan_detection_configs | `not started` |
| Migration overtimes | `not started` |
| Model: semua model attendance | `not started` |
| Controller AttendanceApiController | `not started` |
| Routes Attendance/Routes/api.php | `not started` |
| Service: FingerprintBinParser (port dari hris-system) | `not started` |

#### 7.2 Frontend

| Item | Status |
|---|---|
| Attendance/Index.vue | `done` (mock data) |
| Attendance/LogImport.vue | `done` (mock data) |
| Attendance/Roster.vue | `done` (mock data) |
| Attendance/Overtime/Index.vue | `done` (mock data) |

#### 7.3 Checklist Fase 7

- [x] Frontend: Attendance Index (mock)
- [x] Frontend: Log Import (mock)
- [x] Frontend: Roster (mock)
- [x] Frontend: Overtime (mock)
- [ ] Migration: semua tabel attendance (tanpa overtime_rules, tanpa permit_requests)
- [ ] Model: semua model attendance
- [ ] Controller: AttendanceApiController
- [ ] Routes: Attendance/Routes/api.php
- [ ] Resources: Attendance resources
- [ ] Service: AttendanceService (auto-proses, import logic)
- [ ] Service: FingerprintBinParser (parsing file .bin mesin fingerprint)
- [ ] Frontend: Integrasi semua halaman attendance dengan API
- [ ] Test: import Excel/bin absensi

---

### Fase 8: Payroll

**Tujuan:** Penggajian, periode, generate payroll, komponen gaji, BPJS, PPH, THR.
**Dependencies:** Fase 4 (Employee), Fase 6 (Leave), Fase 7 (Attendance)

> **Catatan:** Struktur payroll disederhanakan. Snapshot karyawan di-embed langsung ke `pay_records`. Tabel `payroll_results`, `payroll_breakdowns`, `payslips`, `payroll_component_snapshots`, `payroll_employee_snapshots` **dihapus**.

#### 8.1 Struktur Tabel Payroll (Disederhanakan)

| Tabel Baru | Fungsi |
|---|---|
| `pay_periods` | Periode payroll |
| `pay_records` | Payroll utama per karyawan per periode (+ snapshot data karyawan embed) |
| `pay_component_values` | Nilai komponen per payroll record |
| `pay_configs` | Konfigurasi payroll |
| `pay_settings` | Settings payroll |
| `pay_audits` | Versi aplikasi bayangan (supervisor) — perhitungan terpisah |

Tabel yang **dihapus/digabung**:
- ~~`payroll_results`~~ → sudah ada di `pay_records`
- ~~`payroll_breakdowns`~~ → JSON fields di `pay_records` (earnings_breakdown, attendance_breakdown)
- ~~`payslips`~~ → di-generate/render dari `pay_records`, bukan tabel
- ~~`payroll_component_snapshots`~~ → data komponen ada di `pay_component_values`
- ~~`payroll_employee_snapshots`~~ → snapshot embed di kolom-kolom `pay_records`

#### 8.2 Backend

| Item | Status |
|---|---|
| Migration pay_settings, pay_configs | `not started` |
| Migration pay_periods | `not started` |
| Migration pay_records (+ snapshot embed) | `not started` |
| Migration pay_component_values | `not started` |
| Migration pay_audits | `not started` |
| Model: semua model payroll | `not started` |
| Controller PayrollApiController | `not started` |
| Routes Payroll/Routes/api.php | `not started` |
| Service: PayrollCalculator | `not started` |

#### 8.3 Frontend

| Item | Status |
|---|---|
| Payroll/Periods/Index.vue | `done` (mock data) |
| Payroll/Periods/Detail.vue | `done` (mock data) |
| Payroll/Configs/Index.vue | `done` (mock data) |
| Payroll/Thr.vue | `done` (mock data) |

#### 8.4 Checklist Fase 8

- [x] Frontend: Payroll Periods Index (mock)
- [x] Frontend: Payroll Period Detail (mock)
- [x] Frontend: Payroll Configs (mock)
- [x] Frontend: Payroll THR (mock)
- [ ] Migration: semua tabel payroll (struktur disederhanakan)
- [ ] Model: semua model payroll
- [ ] Controller: PayrollApiController
- [ ] Routes: Payroll/Routes/api.php
- [ ] Resources: Payroll resources
- [ ] Service: PayrollCalculator (port logic dari hris-system)
- [ ] Frontend: Integrasi semua halaman payroll dengan API
- [ ] Test: generate payroll
- [ ] Test: export Excel/PDF payslip

---

### Fase 9: Laporan

**Tujuan:** Semua laporan (absensi, payroll, pajak, BPJS, THR, leave).
**Dependencies:** Semua fase sebelumnya

#### 9.1 Backend

| Item | Status |
|---|---|
| Controller ReportApiController (aggregation endpoints) | `not started` |
| Routes Reports/Routes/api.php | `not started` |

#### 9.2 Frontend

| Item | Status |
|---|---|
| Reports/Index.vue (6 tipe laporan) | `done` (mock data) |

#### 9.3 Checklist Fase 9

- [x] Frontend: Reports Index (mock)
- [ ] Controller: ReportApiController
- [ ] Routes: Reports/Routes/api.php
- [ ] Service: ReportService
- [ ] Frontend: Integrasi laporan dengan API nyata
- [ ] Frontend: Export Excel/PDF buttons terintegrasi

---

## Supervisor Dashboard (Aplikasi Bayangan)

> **Catatan:** Supervisor Dashboard adalah **aplikasi bayangan** untuk external auditor/inspector. Memiliki perhitungan sendiri yang independen dari aplikasi utama. Akan dikerjakan setelah Fase 9 selesai.

### Cakupan AuditSection

Module AuditSection di aplikasi lama sangat kompleks. Berikut mapping ke aplikasi baru:

| Fungsi Lama (hris-system) | Controller Lama | Fungsi Baru |
|---|---|---|
| Salary breakdown management | SalaryBreakdownController (52KB) | Supervisor: salary breakdown CRUD |
| Staff overtime management | StaffOvertimeController (36KB) | Supervisor: overtime management |
| Attendance autolog processing | AttendanceAutologController (34KB) | Supervisor: attendance processing |
| Attendance snapshot management | AttendanceSnapshotController (29KB) | Supervisor: attendance snapshot |
| Salary management | PengelolaanGajiController (24KB) | Supervisor: payroll management |
| PPH tax management | PphManagementController (14KB) | Supervisor: PPH management |
| Attendance recap | RekapAbsensiController (14KB) | Supervisor: attendance recap |
| THR management | ThrManagementController (14KB) | Supervisor: THR management |
| BPJS management | BpjsManagementController (12KB) | Supervisor: BPJS management |
| Attendance audit | AttendanceAuditController (8KB) | Supervisor: attendance audit |
| Raw log management | RawLogController (6KB) | Supervisor: raw log viewer |
| File upload management | FileManagerController (6KB) | Supervisor: file manager |
| Scan detection config | ScanDetectionConfigController (1KB) | Supervisor: scan config |

Services yang akan di-port:
- **AuditorLogService** (13KB) — logic audit/supervisor
- **FingerprintBinParser** (7KB) — parsing file .bin fingerprint
- **AttendanceOvertimeSyncService** (3KB) — sync overtime data

Export/Import classes:
- **RekapAbsensiExport** (13KB)
- **SalaryBreakdownExport**
- **AttendanceDataFixImport** (22KB)
- **ImportAttendanceData** action

### Halaman Frontend Supervisor

| Halaman | Status |
|---|---|
| Supervisor/Dashboard.vue | `done` (mock data) |
| Supervisor/Attendance/Index.vue | `done` (mock data) |
| Supervisor/Attendance/Roster/Index.vue | `done` (mock data) |
| Supervisor/Payroll/Index.vue | `done` (mock data) |
| Supervisor/Payroll/Thr.vue | `done` (mock data) |
| Supervisor/Leave/Index.vue | `done` (mock data) |
| Supervisor/Employee/Index.vue | `done` (mock data) |
| Supervisor/Reports/Index.vue | `done` (mock data) |
| Supervisor Layout (Sidebar, Topbar, Footer) | `done` |

---

## Arsitektur Aplikasi

### Roles & Permissions

#### Mapping Roles Lama → Baru

| Role Lama | Role Baru | Dashboard | Deskripsi |
|---|---|---|---|
| `super_admin` | **superadmin** | Admin + Supervisor | Akses penuh ke semua dashboard |
| `hr_area` | **hrmanager** | Admin | HR Manager — kelola karyawan, approval, payroll |
| — | **adm_manager** | Supervisor (full) | Admin Manager — bisa export, manage supervisor |
| `staff` | **hr_ast** | Admin | HR Assistant — view & input terbatas |
| `hr_branch` | **hrbranch** | Admin (view-only) | HR Branch — view organisasi & karyawan |

#### Aturan Akses Dashboard

| Role | Dashboard Admin | Dashboard Supervisor |
|---|---|---|
| **superadmin** | Full access | Full access |
| **hrmanager** | Full access | No access |
| **adm_manager** | No access | Full access (CRUD + Export) |
| **hrbranch** | View-only | No access |
| **hr_ast** | View + Input | No access |

#### Daftar Permission

```
# Organization
view companies, create companies, edit companies, delete companies
view branches, create branches, edit branches, delete branches
view departments, create departments, edit departments, delete departments
view positions, create positions, edit positions, delete positions
view salary grades, create salary grades, edit salary grades, delete salary grades

# Employee
view employees, create employees, edit employees, delete employees
import employees, export employees
terminate employees

# Attendance
view attendances, import attendances, edit attendances
manage overtime, approve overtime

# Payroll
view payroll, generate payroll, lock payroll, close payroll
export payroll, print payslip

# Leave
view leave, manage leave, approve leave
manage leave types, manage leave settings

# Approval
approve requests, reject requests

# Supervisor
view supervisor dashboard
manage supervisor data
export supervisor data
```

#### Role ↔ Permission Matrix

| Permission Group | superadmin | hrmanager | adm_manager | hrbranch | hr_ast |
|---|---|---|---|---|---|
| **Organization** | CRUD | View | — | View | — |
| **Employee** | CRUD | CRUD+Import+Export | — | View | View+Create+Edit |
| **Attendance** | All | View+Import+Edit | — | View | View |
| **Payroll** | All | Generate+View+Export | — | — | — |
| **Leave** | All | Approve+Manage | — | View | View |
| **Approval** | All | Approve+Reject | — | — | — |
| **Supervisor View** | All | — | All | — | — |
| **Supervisor Manage** | All | — | All | — | — |
| **Supervisor Export** | All | — | All | — | — |

#### Seeder Default

```
Super Admin  → superadmin@uranop.com  → role: superadmin
HR Manager   → hr@uranop.com          → role: hrmanager
Admin Manager → adm@uranop.com        → role: adm_manager
```

### Aturan Khusus Employee Group

`EmployeeGroup` memiliki **2 Mode / Peran Ganda** yang sangat fleksibel:
1. **Mode Grouping (Metadata Murni):** Hanya untuk data demografi/informasi karyawan tanpa memengaruhi fitur operasional (misal: Golongan Darah, Ukuran Seragam).
2. **Mode Relasi (Functional Mapping):** Sebagai jembatan relasi dinamis karyawan dengan tabel sistem operasional (misal: `shifts`, `work_patterns`) melalui kolom `reference_code`. 
> **Aturan AI (CRITICAL):** DILARANG menambahkan kolom hardcoded seperti `shift_id`, `work_pattern_id`, dll. secara langsung di tabel `employees`. Selalu gunakan `EmployeeGroup` dan kolom `reference_code` untuk *Soft Relationship* (Dictionary-driven Relationships) antar modul. Untuk mengakses data, buatlah Accessor di model `Employee` (contoh: `getShiftGroupAttribute()`) alih-alih mencoba query relasi secara langsung.

### Backend — API Structure

```
/api
├── POST   /login                  → Auth
├── POST   /logout                 → Auth (auth:sanctum)
├── GET    /user                   → Auth (auth:sanctum)
├── GET    /                       → API info
│
├── /organization                  → app/Modules/Organization/Routes/api.php
├── /employees                     → app/Modules/Employee/Routes/api.php
├── /attendance                    → app/Modules/Attendance/Routes/api.php
├── /leave                         → app/Modules/Leave/Routes/api.php
├── /payroll                       → app/Modules/Payroll/Routes/api.php
├── /schedule                      → app/Modules/Schedule/Routes/api.php
├── /settings                      → app/Modules/Settings/Routes/api.php
├── /reports                       → app/Modules/Reports/Routes/api.php
├── /supervisor                    → app/Modules/AuditSection/Routes/api.php
├── /notifications                 → app/Modules/Notification/Routes/api.php
└── /audit-logs                    → app/Modules/AuditLog/Routes/api.php
```

### Frontend — Struktur Folder

```
resources/js/
├── App.vue
├── app.js
├── Components/           ← Global UI Components (12 komponen)
├── Layouts/
│   ├── Admin/            ← Sidebar, Topbar, Footer
│   ├── Supervisor/       ← Sidebar, Topbar, Footer
│   ├── RootLayout.vue
│   ├── AuthenticatedLayout.vue
│   └── GuestLayout.vue
├── Pages/
│   ├── Auth/
│   ├── Admin/            ← 19 halaman (mock data)
│   └── Supervisor/       ← 8 halaman (mock data)
├── Composables/          ← 8 composables
├── Stores/               ← 3 pinia stores
└── router/
    └── index.js
```

---

## Rencana Renaming & Remapping Tabel

> **Catatan:** Setelah semua tabel di-port, akan dilakukan **renaming dan remapping** tabel secara bertahap per fase.
> Tujuannya: menyederhanakan nama, konsistensi naming convention, dan menyesuaikan dengan struktur modul baru.

### Attendance Module (`att_`)

| Nama Lama | Nama Baru |
|---|---|
| `raw_logs` / `attendances` (refactored) | `att_raw_logs` |
| `attendance_logs` | `att_logs` |
| `attendance_autologs` | `att_autologs` |
| `attendance_prepares` | `att_prepares` |
| `attendance_records` | `att_records` |
| `attendance_snapshots` | `att_snapshots` |
| `attendance_summaries` | `att_summaries` |
| `attendance_consecutive_days` | `att_consecutive_days` |
| `attendance_configs` | `att_configs` |
| `scan_detection_configs` | `att_scan_configs` |
| `overtime_rules` | `att_overtime_rules` (shared, satu tabel) |
| `overtimes` | `att_overtimes` |

### Schedule Module (`sch_`)

| Nama Lama | Nama Baru |
|---|---|
| `work_pattern_types` | `sch_pattern_types` |
| `work_patterns` | `sch_patterns` |
| `work_pattern_details` | `sch_pattern_details` |
| `shifts` | `sch_shifts` |
| `employee_shift_rosters` | `sch_rosters` |
| `working_calendars` | `sch_calendars` |
| `holidays` | `sch_holidays` |

### Leave Module (`lve_`)

| Nama Lama | Nama Baru |
|---|---|
| `leave_period_configs` | `lve_period_configs` |
| `leave_periods` | `lve_periods` |
| `leave_types` | `lve_types` (+ category column) |
| `leave_policies` | `lve_policies` |
| `leave_requests` | `lve_requests` |
| `leave_documents` | `lve_documents` |
| `leave_entitlements` | `lve_entitlements` |
| `leave_balances` | `lve_balances` |

### Payroll Module (`pay_`)

| Nama Lama | Nama Baru | Catatan |
|---|---|---|
| `payroll_settings` | `pay_settings` | |
| `payroll_configs` | `pay_configs` | |
| `payroll_periods` | `pay_periods` | |
| `payrolls` | `pay_records` | + snapshot embed |
| `payroll_audits` | `pay_audits` | Aplikasi bayangan |
| `payroll_component_values` | `pay_component_values` | |
| `salary_components` | `pay_components` | |
| `bpjs_configs` | `pay_bpjs_configs` | |
| `pph_configs` | `pay_pph_configs` | |
| `ptkp_rates` | `pay_ptkp_rates` | |
| `ter_rates` | `pay_ter_rates` | |
| `progressive_rates` | `pay_progressive_rates` | |
| `service_year_allowances` | `pay_service_allowances` | |

Tabel payroll yang **DIHAPUS**:
- ~~`payroll_results`~~ → digabung ke `pay_records`
- ~~`payroll_breakdowns`~~ → JSON di `pay_records`
- ~~`payslips`~~ → generated dari `pay_records`
- ~~`payroll_component_snapshots`~~ → di `pay_component_values`
- ~~`payroll_employee_snapshots`~~ → embed di `pay_records`
- ~~`pay_overtime_rules`~~ → cukup `att_overtime_rules` (shared)

### Employee Module (`emp_`)

| Nama Lama | Nama Baru | Catatan |
|---|---|---|
| `employees` | Tetap | + uuid, synced_at |
| `employee_groups` | `emp_groups` | |
| `employee_titles` | `emp_titles` | |
| `employee_contracts` | `emp_contracts` | |
| `employee_position_histories` | `emp_position_histories` | |
| `employee_families` | `emp_families` | |
| `employee_documents` | `emp_documents` | |
| `employee_salaries` | `emp_salaries` | + effective_date |
| `employee_salary_components` | `emp_salary_components` | |
| `employee_salary_breakdowns` | `emp_salary_breakdowns` | |
| `employee_terminations` | `emp_terminations` | |
| `employee_bpjs` | `emp_bpjs` | |
| `employee_thr` | `emp_thr` | |

Tabel employee yang **DIHAPUS**:
- ~~`employee_periodes`~~ → diganti effective date pattern

### Organization Module (`org_`)

| Nama Lama | Nama Baru |
|---|---|
| `companies` | Tetap (data referensi) |
| `branches` | Tetap (data referensi) |
| `departments` | `org_departments` |
| `positions` | `org_positions` |

Tabel organization yang **DIHAPUS**:
- ~~`company_settings`~~ → digabung ke `system_settings`
- ~~`branch_settings`~~ → digabung ke `system_settings`

### Settings Module (`set_`)

| Nama Lama | Nama Baru |
|---|---|
| `system_settings` | `set_system` |
| `salary_grades` | `set_salary_grades` |
| `salary_grade_histories` | `set_salary_grade_histories` |

### User & Auth

| Nama Lama | Nama Baru | Catatan |
|---|---|---|
| `users` | Tetap | |
| `user_preferences` | Tetap | |
| `personal_access_tokens` | Tetap | |
| `audit_logs` | Tetap | + context column |
| `notifications` | Tetap | Laravel standard |

Tabel auth yang **DIHAPUS**:
- ~~`user_branches`~~ → tidak perlu (1 instance = 1 branch)

---

## Daftar Module & Prioritas

| # | Module | Fase | Kompleksitas | Ketergantungan | Status Backend | Status Frontend |
|---|---|---|---|---|---|---|
| 0 | **Shared** | Fase 0 | Rendah | — | ❌ Belum | — |
| 0 | **AuditLog** | Fase 0 | Rendah | — | ❌ Belum | — |
| 1 | **Auth** | Fase 1 | Rendah | — | ✅ Complete | ✅ Complete |
| 1 | **Notification** | Fase 1 | Rendah | Auth | ❌ Belum | ❌ Belum |
| 2 | **Organization** | Fase 2 | Rendah | Fase 1 | ❌ Belum | ✅ Mock |
| 3 | **Settings** | Fase 3 | Rendah | Fase 1, 2 | ❌ Belum | ✅ Mock |
| 4 | **Employee** | Fase 4 | Tinggi | Fase 2, 3 | ❌ Belum | ✅ Mock |
| 5 | **Schedule** | Fase 5 | Sedang | Fase 2, 4 | ❌ Belum | ✅ Mock |
| 6 | **Leave** | Fase 6 | Sedang | Fase 4 | ❌ Belum | ✅ Mock |
| 7 | **Attendance** | Fase 7 | Tinggi | Fase 4, 5 | ❌ Belum | ✅ Mock |
| 8 | **Payroll** | Fase 8 | Sangat Tinggi | Fase 4, 6, 7 | ❌ Belum | ✅ Mock |
| 9 | **Reports** | Fase 9 | Rendah | Semua module | ❌ Belum | ✅ Mock |
| 10 | **AuditSection** | Supervisor | Sangat Tinggi | Fase 8, 7 | ❌ Belum | ✅ Mock |

---

## Daftar Module Backend

| Module | Path | Deskripsi | Status |
|---|---|---|---|
| **Shared** | `app/Modules/Shared/` | Traits, helpers, base classes | ❌ Belum |
| **AuditLog** | `app/Modules/AuditLog/` | Audit trail logging (main + shadow) | ❌ Belum |
| **Auth** | `app/Modules/Auth/` | Autentikasi, login, user management | ✅ Complete |
| **Notification** | `app/Modules/Notification/` | Notifikasi | ❌ Belum |
| **Organization** | `app/Modules/Organization/` | Department, Position | ❌ Belum |
| **Settings** | `app/Modules/Settings/` | SystemSettings, SalaryGrade, configs | ❌ Belum |
| **Employee** | `app/Modules/Employee/` | Karyawan, kontrak, keluarga, dokumen, terminasi | ❌ Belum |
| **Schedule** | `app/Modules/Schedule/` | WorkPattern, Shift, Roster, Calendar, Holiday | ❌ Belum |
| **Attendance** | `app/Modules/Attendance/` | Absensi, log import, lembur | ❌ Belum |
| **Leave** | `app/Modules/Leave/` | Cuti & izin, tipe, kebijakan, approval | ❌ Belum |
| **Payroll** | `app/Modules/Payroll/` | Penggajian, BPJS, PPH, THR | ❌ Belum |
| **AuditSection** | `app/Modules/AuditSection/` | Dashboard Supervisor (aplikasi bayangan) | ❌ Belum |
| **Reports** | `app/Modules/Reports/` | Laporan-laporan | ❌ Belum |

---

## Checklist Global

### Testing & QA

- [ ] Test semua API endpoint via Bruno/Postman
- [ ] Test UI flow: login → dashboard → CRUD → logout
- [ ] Test role-based access (login as 5 different roles)
- [ ] Test dark/light mode toggle
- [ ] Test responsive layout (mobile sidebar collapse)
- [ ] Test error handling (invalid login, 401, 403, 422, 500)
- [ ] Test import Excel (karyawan, absensi)
- [ ] Test export Excel/PDF

### Deploy

- [ ] Push code ke repository
- [ ] Setup database MySQL di VPS: `CREATE DATABASE instance1_uranop`
- [ ] Copy `.env.production` → `.env` di VPS
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan key:generate`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=RolePermissionSeeder`
- [ ] `php artisan db:seed --class=UserSeeder`
- [ ] `npm ci && npm run build`
- [ ] Set document root ke `public/`
- [ ] Setup SSL via CloudPanel (Let's Encrypt)
- [ ] Konfigurasi Nginx: SPA fallback
- [ ] Setup cron job: `* * * * * php artisan schedule:run`
- [ ] Setup queue worker: `php artisan queue:work --daemon`

---

## Pertanyaan Terjawab

| # | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Tabel instances sekarang atau nanti? | **Sekarang.** Dibuat lengkap dengan model, migration, seeder, config |
| 2 | Struktur backend? | **Tetap modular** `app/Modules/{Module}/...` seperti hris-system |
| 3 | Company/branch tetap ada? | **Ya**, sebagai tabel referensi data, tanpa global scope |
| 4 | Role hrbranch? | **Terpisah**: hrbranch ke Admin dashboard (view-only). Superadmin bisa akses **keduanya** |
| 5 | Modul Supervisor? | **Tampilan sama** dengan Admin tapi **isi berbeda** (tabel & perhitungan berbeda) — "aplikasi bayangan" |
| 6 | Import fingerprint? | **Tetap di module Attendance** + port FingerprintBinParser |
| 7 | Renaming & remapping tabel? | **Ya, bertahap per fase.** Semua tabel akan di-rename dengan prefix module |
| 8 | THR di dashboard mana? | **Kedua dashboard** (Admin + Supervisor) |
| 9 | Bahasa aplikasi? | **Bahasa Indonesia** untuk menu. Technical terms tetap EN (generate, import, export) |
| 10 | Enum di handle bagaimana? | **Enum dibuat tabel** di Fase 3 (Data Master, Config, Setting, Konstanta) |
| 11 | Guard Sanctum vs Web? | **Tetap `web`** — Sanctum SPA mode menggunakan session (guard `web`) |
| 12 | audit_logs? | **Satu tabel** dengan kolom `context: enum('main', 'shadow')`. Module sendiri `app/Modules/AuditLog/` |
| 13 | Notifications? | **Masuk Fase 1** bersamaan Auth & Layout |
| 14 | overtime_rules ownership? | **Satu tabel: `att_overtime_rules`** (shared antara attendance & payroll) |
| 15 | payroll_results, payroll_breakdowns, payslips? | **Dihapus.** Sudah ada di `pay_records` (data + JSON breakdowns). Payslip di-render, bukan tabel |
| 16 | payroll_component_snapshots? | **Dihapus.** Data komponen ada di `pay_component_values` |
| 17 | employee_periodes naming? | **Dihapus total.** Diganti effective date pattern pada `emp_salaries`, `emp_contracts`, `emp_position_histories` |
| 18 | salary_grade_histories module? | **Settings module (Fase 3)** — bersifat opsional, tidak semua perusahaan pakai |
| 19 | permit_requests? | **Tidak ada tabel terpisah.** Izin ditangani via `lve_types` dengan `category: 'permit'` |
| 20 | user_branches, company_settings, branch_settings? | **Semua dihapus.** 1 instance = 1 branch. Settings digabung ke `system_settings` |
| 21 | Sync desktop? | **Ya.** Tambah `uuid` + `synced_at` di tabel data utama. Detail sync direncanakan terpisah |
| 22 | Shared traits? | **Port 8 traits** dari hris-system. Hapus HasCompanyScope & HasBranchScope |
| 23 | Arsitektur dua mesin? | **Aplikasi Utama** (Admin) + **Aplikasi Bayangan** (Supervisor) — perhitungan independen |
| 24 | Fungsi Employee Group? | **2 Mode: Grouping murni & Relasi dinamis.** Jangan hardcode `shift_id` di employees. Gunakan `reference_code` di `employee_groups` untuk relasi ke modul lain. |
