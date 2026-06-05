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

    // --- SALARY CALCULATION METHODS ---
    // Gunakan fungsi-fungsi di bawah ini untuk mengambil komponen gaji per periode.
    // Jika $period tidak diberikan, akan mengambil data bulan ini (current).
    // Parameter $period berformat 'YYYY-MM' (contoh: '2026-05').
    
    // 1. Gaji Pokok
    // $employee->baseSalary('2026-05') atau $employee->gaji_pokok('2026-05')
    public function baseSalary(?string $period = null): float;
    public function gaji_pokok(?string $period = null): float;

    // 2. Premi / Bonus Tetap
    // $employee->premi('2026-05') atau $employee->premi_component('2026-05')
    public function premi(?string $period = null): float;
    public function premi_component(?string $period = null): float;

    // 3. Tunjangan Masa Kerja (dihitung dinamis berdasar join_date s.d. periode)
    // $employee->tunjanganMasaKerja('2026-05') atau $employee->tjMasaKerja('2026-05')
    public function tunjanganMasaKerja(?string $period = null): float;
    public function tunjangan_masa_kerja(?string $period = null): float;

    // 4. Tunjangan Tetap / Tunjangan Lainnya
    // $employee->tunjangan('2026-05') atau $employee->tunjangan_tetap('2026-05')
    public function tunjangan(?string $period = null): float;
    public function tunjangan_tetap(?string $period = null): float;

    // 5. Total Gaji (Gaji Pokok + Premi + Tunjangan Masa Kerja + Tunjangan)
    // $employee->totalGaji('2026-05')
    public function totalGaji(?string $period = null): float;

    // --- OTHER METHODS ---

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

**Daftar Fungsi Kalkulasi Siap Pakai:**
Gunakan metode di atas ketika membuat halaman Generate Payroll atau mencetak Rekap Gaji (Slip Gaji), **jangan melakukan hardcode query `employee_salaries` secara manual**. Fungsi di atas sudah dirancang untuk membaca history (ignoring `is_active`) dengan tepat sesuai parameter `$period` yang diminta.

Dengan pattern ini, **tabel `employee_periodes` dihapus** — data bisa dihitung dari `emp_salaries`, `emp_contracts`, dan `emp_position_histories` menggunakan effective date.

---

## Fase Eksekusi

> **Catatan:** Setiap fase mencakup **backend + frontend** secara bersamaan.
> **Prioritas:** Admin Dashboard dikerjakan terlebih dahulu. Supervisor Dashboard menyusul setelah fase 9.
> **Panduan UI Frontend:** Saat mengerjakan bagian frontend/UI di setiap fasenya, Anda **diperbolehkan langsung copy-paste** dari template/aplikasi lama (`hris-system`) atau melakukan *improvement*. Namun, pastikan Anda menyesuaikan link navigasi dan integrasi API (Vue Router & Pinia/composables), serta **wajib** menyelaraskan struktur class dengan aturan yang tertulis di `uistyle.md` agar tampilan UI seragam.

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
- Employee group masters, employee groups, employee titles
- Salary components
- BPJS configs, PPH configs, PTKP rates, TER rates, progressive rates
- Overtime rules (shared — dipakai attendance & payroll)
- Service year allowances

#### 3.2 Backend

| Item | Status |
|---|---|
| Migration system_settings | `not started` |
| Migration salary_grades, salary_grade_histories | `not started` |
| Migration employee_group_masters, employee_groups, employee_titles | `not started` |
| Migration salary_components | `not started` |
| Migration bpjs_configs, pph_configs, ptkp_rates, ter_rates, progressive_rates | `not started` |
| Migration overtime_rules (shared, satu tabel) | `not started` |
| Migration service_year_allowances | `not started` |
| Model: SystemSetting, SalaryGrade, SalaryGradeHistory, EmployeeGroupMaster, EmployeeGroup, EmployeeTitle | `not started` |
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
- [x] Migration + Model: employee_group_masters, employee_groups, employee_titles
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
| Migration employees (+ uuid, synced_at) | `done` |
| Migration employee_contracts | `done` |
| Migration employee_families | `done` |
| Migration employee_documents | `done` |
| Migration employee_salaries (+ effective_date) | `done` |
| Migration employee_salary_components | `done` |
| Migration employee_position_histories | `done` |
| Migration employee_terminations | `done` |
| Migration employee_bpjs | `done` |
| Migration employee_thr | `done` |
| Model: Employee (+ effective date accessors) | `done` |
| Model: Contract, Family, Document, Salary, Termination, dll | `done` |
| Controller EmployeeApiController (CRUD) | `done` |
| Submodule ImportApiController (Import Excel) | `done` |
| Routes Employee/Routes/api.php | `done` |
| Resources: EmployeeResource | `done` |

#### 4.2 Frontend

| Item | Status |
|---|---|
| Admin/Employees/Karyawan/Index.vue | `done` (API) |
| Admin/Employees/Karyawan/Create.vue | `done` (API) |
| Admin/Employees/Karyawan/Show.vue | `done` (API) |
| Admin/Employees/Karyawan/Edit.vue | `done` (API) |

#### 4.3 Checklist Fase 4

- [x] Frontend: Employee Index (API)
- [x] Frontend: Employee Show (API)
- [x] Frontend: Employee Create (API)
- [x] Frontend: Employee Edit (API)
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

**Tujuan:** Absensi, log import fingerprint, sync, lengkapi, hitung lembur, consecutive days, resume kehadiran untuk payroll.
**Dependencies:** Fase 4 (Employee), Fase 5 (Schedule)

> **Catatan:** `overtime_rules` sudah dibuat di Fase 3 (shared). `permit_requests` dihapus — izin ditangani lewat Leave module.
> **Carbon:** Semua pemanggilan `diffInMinutes()` WAJIB pakai parameter `true` (absolute): `$a->diffInMinutes($b, true)`.

---

#### 7.1 Struktur Menu & Submenu

```
Kehadiran
├── 1. Sync Kehadiran          ← Halaman utama: import, daftar att_prepares, filter
│   ├── 1a. Proses Sync        ← Raw logs → att_prepares (9 detector types)
│   ├── 1b. Proses Lengkapi    ← Auto-fill check_in/out kosong
│   ├── 1c. Proses Hitung Lembur ← Kalkulasi late, OT, LM per record
│   └── 1d. Lock/Unlock        ← Kunci/unlock data
├── 2. Consecutive             ← Deteksi hari kerja berturut-turut (seperti leave_request pattern)
└── 3. Resume Kehadiran        ← Rekap untuk input payroll (hari kerja, OT, LM, dll)
```

---

#### 7.2 Proses 1a: Sync (Raw Logs → att_prepares)

**Flow:** `att_raw_logs` + `sch_rosters` → **AttendanceSyncService** → `att_prepares`

**Tabel input:**
| Tabel | Fungsi |
|---|---|
| `att_raw_logs` | Data fingerprint mentah (PIN, scan_datetime, import_batch) |
| `sch_rosters` | Jadwal karyawan per tanggal (shift, work_pattern) |
| `sch_holidays` | Hari libur nasional |
| `lve_requests` | Cuti/izin yang approved |

**Proses Sync per karyawan per hari:**

1. Ambil roster karyawan dari `sch_rosters` untuk tanggal tersebut
2. Cek status cuti/izin dari `lve_requests` → kalau ada, langsung set status CUTI/IZIN/SAKIT
3. Ambil raw_logs berdasarkan `employee_code` (PIN/NIP)
4. Deteksi check_in & check_out berdasarkan **work pattern type**:

| Detector | Work Pattern | Cara Deteksi |
|---|---|---|
| `detectFixed` | FIXED | Scan pertama = check_in, scan terakhir = check_out |
| `detectShift` | SHIFT | Window matching: filter log by check_in_start..end & check_out_start..end |
| `detectFlexShift` | FLEX-SHIFT | Window matching + holiday config dari `sch_shifts.metadata` JSON |
| `detectLongshift` | LONGSHIFT | Window matching (sama dengan SHIFT) |
| `detectSplit` | SPLIT | Window matching |
| `detectFlexi` | FLEXI | Window matching (late dihandle calculator) |
| `detectHourly` | HOURLY | Window matching |
| `detectOnCall` | ON_CALL | Window matching |
| `detectSeasonal` | SEASONAL | Window matching |

5. Jika tidak ada log:
   - Holiday → status LIBUR
   - Minggu (is_sun) → status OFF
   - Hari kerja tanpa scan → status ABSENT

6. Simpan ke `att_prepares` (upsert by employee_id + date)

**Status di att_prepares:** `hadir`, `libur`, `off`, `absent`, `cuti`, `izin`, `sakit`

---

#### 7.3 Proses 1b: Lengkapi

**Flow:** `AttendanceService.autoLengkapi()` — isi check_in/check_out kosong.

**Rules:**
| Kondisi | Ada Scan | Tidak Ada Scan |
|---|---|---|
| **Holiday** | Isi check_in/out dari roster work_hour_start/end → status LIBUR | Status LIBUR |
| **Minggu** | Isi check_in/out dari roster → status LIBUR | Status OFF |
| **Cuti (approved)** | — | Status CUTI |
| **Hari Kerja** | Isi check_in/out kosong dari roster → status HADIR | Status ABSENT (atau HADIR jika fillAbsent=true) |

Parameter `fillAbsent=true`: mengisi check_in/out dari roster meskipun 0 scan (untuk karyawan tanpa fingerprint).

---

#### 7.4 Proses 1c: Hitung Lembur

**Flow:** `AttendanceCalculatorService.calculate()` — kalkulasi per record att_prepares.

**Output per record:**
| Field | Keterangan |
|---|---|
| `late_minutes` | Keterlambatan (check_in vs schedule_in - tolerance) |
| `overtime` | Menit lembur mentah (sebelum multiplier) |
| `overtime_count` | Menit lembur setelah multiplier (hari kerja) |
| `lm` | Menit LM mentah (hari libur/minggu) |
| `lm_count` | Menit LM setelah multiplier |
| `normal` | Menit kerja normal (`workEnd - workStart`) |

**Rules Overtime per Work Pattern:**

| Pattern | Holiday | Minggu | Sabtu | Hari Kerja |
|---|---|---|---|---|
| **SHIFT** | Full check_in→check_out, max 480m | 0 | **Flat 120m** | 0 |
| **FIXED** | Full, max 480m | Full, max 480m | — | Post-shift: `check_out - schedule_out` |
| **FLEX-SHIFT P** | Full, max 480m | Full, max 480m | — | Post-shift only |
| **FLEX-SHIFT S** | Full, max 480m | Full, max 480m | — | Aturan b.1/b.2/b.3 (lihat bawah) |

**FLEX-SHIFT S — Aturan b.1 / b.2 / b.3:**

`has_modifier` flag di `sch_shifts` hanya untuk FLEX-SHIFT + `ext_code=S`. Metadata JSON punya 5 field holiday config.

| Aturan | Kondisi | Overtime |
|---|---|---|
| **b.1** | check_in early > 30 menit sebelum schedule_in | Pre-shift: `schedule_in - check_in` |
| **b.2** | check_in early ≤ 30 menit, check_out > schedule_out | Post-shift: `check_out - schedule_out` |
| **b.3** | check_in > schedule_in + 2 jam | Post-shift: `check_out - schedule_out` |

**Effective Start per Pattern:**
| Pattern | effectiveStart |
|---|---|
| SHIFT | — (overtime hanya holiday/sabtu) |
| FIXED | `max(checkIn, workStart)` |
| FLEX-SHIFT | `checkIn` |

**Multiplier:**
- **Hari kerja (overtime):** Lookup dari `att_overtime_rules` (is_holiday=false). Fallback: 60m pertama ×1.5, sisanya ×2.
- **Hari libur (LM):** Lookup dari `att_overtime_rules` (is_holiday=true). Fallback: `(min(hours,8) - 1) × 2 × 60`.

**Rounding:** Overtime dibulatkan per 30 menit dengan threshold 5 menit. `floor((minutes + 5) / 30) * 30`.

---

#### 7.5 Proses 1d: Lock/Unlock

Hanya **superadmin** & **hrmanager** yang bisa lock/unlock.

- **Lock:** Record tidak bisa diedit (lengkapi, hitung lembur, manual edit)
- **Unlock:** Record bisa diedit kembali
- Support **bulk** lock/unlock

---

#### 7.6 Submenu 2: Consecutive

> **Tabel:** `att_consecutive_days`

Deteksi hari kerja berturut-turut. Pattern mirip leave_request — mendeteksi rentang tanggal di mana karyawan:
- Hadir berturut-turut (untuk perhitungan overtime khusus)
- Absen berturut-turut (untuk flag pemeriksaan)

---

#### 7.7 Submenu 3: Resume Kehadiran

> **Tabel:** `att_summaries`, `att_snapshots`

Rekap kehadiran per karyawan per periode untuk input ke **payroll** (Fase 8).

**Data yang dihasilkan:**
| Data | Sumber |
|---|---|
| Total hari kerja | att_prepares (status HADIR) |
| Total hari libur | att_prepares (status LIBUR) |
| Total hari off | att_prepares (status OFF) |
| Total absen | att_prepares (status ABSENT) |
| Total cuti | att_prepares (status CUTI) |
| Total izin | att_prepares (status IZIN) |
| Total sakit | att_prepares (status SAKIT) |
| Total overtime (count) | att_prepares.overtime_count |
| Total LM (count) | att_prepares.lm_count |
| Total late | att_prepares.late_minutes |

---

#### 7.8 Backend Status

| Item | Status | Keterangan |
|---|---|---|
| Migration `att_raw_logs` | `done` | Data fingerprint mentah |
| Migration `att_prepares` | `done` | Data kehadiran per karyawan per hari |
| Migration `att_logs`, `att_autologs` | `not started` | Log attendance (intermediate) |
| Migration `att_records` | `not started` | Record attendance final |
| Migration `att_snapshots` | `not started` | Snapshot untuk supervisor dashboard |
| Migration `att_summaries` | `not started` | Resume kehadiran per periode |
| Migration `att_consecutive_days` | `not started` | Hari berturut-turut |
| Migration `att_configs` | `not started` | Konfigurasi attendance |
| Migration `att_scan_configs` | `not started` | Konfigurasi scan detection |
| Migration `att_overtimes` | `not started` | Data overtime |
| Model `RawLog` | `done` | |
| Model `AttendancePrepare` | `done` | + status constants, review_status |
| Model lainnya | `not started` | AttendanceLog, AttendanceRecord, dll |
| Controller `AttendanceApiController` | `done` | Full CRUD + sync + lengkapi + hitung lembur |
| Routes `Attendance/Routes/api.php` | `done` | Semua endpoint terdefinisi |
| Resources: `RawLogResource`, `AttendanceRecordResource`, `OvertimeResource`, `AttendanceSummaryResource` | `done` | |
| Service `AttendanceService` | `done` | Lengkapi, autoLengkapi, hitungLembur, bulkHitungLembur, lock/unlock |
| Service `AttendanceSyncService` | `done` | Sync raw_logs → att_prepares (892 baris, 9 detector) |
| Service `AttendanceCalculatorService` | `done` | Kalkulasi late, OT, LM (373 baris) |
| Service `FingerprintBinParser` | `done` | Parsing file .bin mesin fingerprint |
| Job `ProcessAttendanceLogImport` | `done` | Queue job untuk import |
| Import `AttendanceLogImport` | `done` | Excel import handler |

#### 7.9 Frontend Status

| Item | Status | Keterangan |
|---|---|---|
| `Attendance/SyncKehadiran/Index.vue` | `done` (mock) | Halaman utama: import, daftar, filter |
| `Attendance/LogImport.vue` | `done` (mock) | Import Excel/bin |
| `Attendance/Roster.vue` | `done` (mock) | Roster view |
| `Attendance/Overtime/Index.vue` | `done` (mock) | Overtime manual |
| `Attendance/Consecutive/Index.vue` | `not started` | |
| `Attendance/Resume/Index.vue` | `not started` | |

#### 7.10 Checklist Fase 7

- [x] Migration: `att_raw_logs`, `att_prepares`
- [x] Model: `RawLog`, `AttendancePrepare`
- [x] Controller: `AttendanceApiController`
- [x] Routes: `Attendance/Routes/api.php`
- [x] Resources: RawLog, AttendanceRecord, Overtime, AttendanceSummary
- [x] Service: `AttendanceService` (lengkapi, autoLengkapi, hitungLembur, lock)
- [x] Service: `AttendanceSyncService` (sync, 9 detector types)
- [x] Service: `AttendanceCalculatorService` (late, OT, LM, multiplier, rounding)
- [x] Service: `FingerprintBinParser` (parsing .bin fingerprint)
- [x] Job: `ProcessAttendanceLogImport`
- [x] Import: `AttendanceLogImport`
- [x] Frontend: SyncKehadiran Index (mock)
- [x] Frontend: Log Import (mock)
- [x] Frontend: Overtime (mock)
- [ ] Migration: `att_logs`, `att_autologs`, `att_records`
- [ ] Migration: `att_snapshots`, `att_summaries`, `att_consecutive_days`
- [ ] Migration: `att_configs`, `att_scan_configs`, `att_overtimes`
- [ ] Model: AttendanceLog, AttendanceAutolog, AttendanceRecord, dll
- [ ] Frontend: Consecutive page
- [ ] Frontend: Resume Kehadiran page
- [ ] Frontend: Integrasi SyncKehadiran dengan API
- [ ] Frontend: Integrasi Overtime dengan API
- [ ] Test: import Excel/bin absensi
- [ ] Test: sync + lengkapi + hitung lembur full flow

---

### Fase 8: Payroll

**Tujuan:** Penggajian, generate payroll per periode, kalkulasi BPJS, PPh 21, THR, slip gaji.
**Dependencies:** Fase 4 (Employee), Fase 6 (Leave), Fase 7 (Attendance)

> **Catatan:** Struktur payroll disederhanakan. Snapshot karyawan di-embed langsung ke `pay_records`. Tabel `payroll_results`, `payroll_breakdowns`, `payslips`, `payroll_component_snapshots`, `payroll_employee_snapshots` **dihapus**.
>
> **PENTING:** Gunakan metode kalkulasi gaji dari model `Employee` (lihat Effective Date Pattern di atas). JANGAN hardcode query `emp_salaries` manual. Fungsi siap pakai: `baseSalary()`, `premi()`, `tunjanganMasaKerja()`, `tunjangan()`, `totalGaji()` — semua terima parameter `$period` ('YYYY-MM').

---

#### 8.1 Struktur Menu & Submenu

```
Payroll
├── 1. Gaji Karyawan          ← Generate payroll, lihat/edit pay_records, lock periode
│   ├── 1a. Pilih Periode     ← Pilih/buat pay_periods
│   ├── 1b. Generate Gaji     ← Kalkulasi & simpan pay_records
│   ├── 1c. Review & Edit     ← Lihat/ubah pay_records per karyawan
│   └── 1d. Lock Periode      ← Kunci periode (tidak bisa diedit)
├── 2. Perhitungan BPJS       ← Konfigurasi + kalkulasi BPJS
│   ├── 2a. BPJS Kesehatan    ← 4% (1% karyawan, 3% perusahaan)
│   └── 2b. BPJS TK           ← JKK, JKM, JHT, JP
├── 3. Perhitungan PPh        ← Konfigurasi + kalkulasi PPh 21
│   ├── 3a. TER Bulanan       ← Tarif Efektif Rata-rata per bulan
│   └── 3b. PPh 21 Tahunan    ← Rekonsiliasi akhir tahun
├── 4. Slip Gaji              ← Render payslip + export
│   ├── 4a. Individual        ← Slip per karyawan
│   └── 4b. Bulk Export       ← Export semua slip (PDF/Excel)
└── 5. Pengelolaan THR        ← Generate + export THR
    ├── 5a. Konfigurasi THR   ← Rules (masa kerja, proporsional)
    └── 5b. Generate THR      ← Kalkulasi + simpan ke emp_thr
```

---

#### 8.2 Submenu 1: Gaji Karyawan

##### 8.2.1 Flow Generate Gaji

```
Pilih Periode → Generate → Kalkulasi → Simpan pay_records → Review → Lock
```

**Step by step:**

1. **Pilih/Buat Periode** (`pay_periods`)
   - Format: `period_year` + `period_month`
   - Status: `draft` → `generated` → `locked`

2. **Generate — Ambil Data Input:**
   | Sumber | Data | Method |
   |---|---|---|
   | Employee | Gaji pokok | `$employee->baseSalary($period)` |
   | Employee | Premi/bonus tetap | `$employee->premi($period)` |
   | Employee | Tunjangan masa kerja | `$employee->tunjanganMasaKerja($period)` |
   | Employee | Tunjangan tetap | `$employee->tunjangan($period)` |
   | Attendance | Hari kerja, OT, LM, late | `att_summaries` (dari Fase 7 Resume) |
   | BPJS | Potongan BPJS | `pay_bpjs_configs` + kalkulasi |
   | PPh | Potongan PPh 21 | `pay_pph_configs` + TER |

3. **Kalkulasi per Karyawan:**
   ```
   GROSS = baseSalary + premi + tunjanganMasaKerja + tunjangan
   EARNINGS = GROSS + overtime_count × rate_per_hour + LM_count × rate_per_hour
   DEDUCTIONS = BPJS_Kesehatan + BPJS_TK + PPh21 + late_penalty + absent_penalty
   NET = EARNINGS - DEDUCTIONS
   ```

4. **Simpan ke `pay_records`** (satu record per karyawan per periode):
   - Earnings breakdown → JSON `earnings_breakdown`
   - Attendance breakdown → JSON `attendance_breakdown`
   - Deduction breakdown → JSON `deduction_breakdown`
   - Snapshot: salary_components, position, department, contract → embed di record

5. **Review & Edit:**
   - Tabel list semua pay_records dalam satu periode
   - Bisa edit manual overtime, bonus, potongan tambahan
   - Recalculate otomatis setelah edit

6. **Lock Periode:**
   - Status `locked` → tidak bisa generate ulang / edit
   - Hanya superadmin & hrmanager

##### 8.2.2 Tabel `pay_records` — Struktur

| Field | Tipe | Keterangan |
|---|---|---|
| `id` | bigint | PK |
| `uuid` | char(36) | Sync identifier |
| `pay_period_id` | FK | Periode payroll |
| `employee_id` | FK | Karyawan |
| `basic_salary` | decimal | Gaji pokok (snapshot) |
| `total_earnings` | decimal | Total pendapatan (gross + OT + LM) |
| `total_deductions` | decimal | Total potongan (BPJS + PPh + denda) |
| `net_pay` | decimal | Gaji bersih |
| `earnings_breakdown` | json | `{gaji_pokok, premi, tunjangan_masa_kerja, tunjangan, overtime, lm, bonus}` |
| `attendance_breakdown` | json | `{hadir, libur, off, absent, cuti, izin, sakit, overtime_count, lm_count, late}` |
| `deduction_breakdown` | json | `{bpjs_kes, bpjs_tk, pph21, late_penalty, absent_penalty, other}` |
| `salary_snapshot` | json | `{position, department, contract_type, join_date}` |
| `status` | enum | `draft`, `final`, `locked` |
| `notes` | text | Catatan |
| `created_by` | FK | User yang generate |
| `timestamps` | | |
| `deleted_at` | softDelete | |

---

#### 8.3 Submenu 2: Perhitungan BPJS

> **Tabel:** `pay_bpjs_configs`

**Komponen BPJS:**

| Jenis | Komponen | Karyawan | Perusahaan | Max Cap |
|---|---|---|---|---|
| **Kesehatan** | JKN | 1% | 4% | Rp 12.000.000 |
| **TK - JKK** | Kecelakaan Kerja | 0% | 0.24% - 1.74% | — |
| **TK - JKM** | Kematian | 0% | 0.3% | — |
| **TK - JHT** | Hari Tua | 2% | 3.7% | — |
| **TK - JP** | Pensiun | 1% | 2% | — |

**Config per komponen:**
- `component` (enum: jkn, jkk, jkm, jht, jp)
- `employee_rate` (decimal)
- `company_rate` (decimal)
- `max_cap` (decimal, nullable)
- `effective_date`

---

#### 8.4 Submenu 3: Perhitungan PPh

> **Tabel:** `pay_pph_configs`, `pay_ptkp_rates`, `pay_ter_rates`, `pay_progressive_rates`

##### 8.4.1 PTKP (Penghasilan Tidak Kena Pajak)

| Kategori | Kode | Nilai/Tahun |
|---|---|---|
| Tidak Kawin | TK/0 | Rp 54.000.000 |
| Tidak Kawin + 1 tanggungan | TK/1 | Rp 58.500.000 |
| Kawin | K/0 | Rp 58.500.000 |
| Kawin + 1 tanggungan | K/1 | Rp 63.000.000 |
| Kawin + 2 tanggungan | K/2 | Rp 67.500.000 |
| Kawin + 3 tanggungan | K/3 | Rp 72.000.000 |

##### 8.4.2 TER (Tarif Efektif Rata-rata) — Bulanan

| Kategori | TER A | TER B | TER C |
|---|---|---|---|
| **Range gaji** | s.d. 5.4jt | 5.4jt - 10.5jt | > 10.5jt |

> Lihat tabel lengkap di `pay_ter_rates` (PER-2/PJ/2024 untuk TER terbaru).

##### 8.4.3 Kalkulasi PPh 21 per Bulan

```
Gaji Bruto Sebulan = GROSS (dari pay_records)
PPh 21 = Gaji Bruto × TER% (sesuai kategori PTKP)
```

Rekonsiliasi tahunan (Desember): hitung ulang dengan tarif progressive, selisih kurang/lebih bayar.

**Tarif Progressive (Tahunan):**

| Lapisan | PKP | Tarif |
|---|---|---|
| I | s.d. 60jt | 5% |
| II | 60jt - 250jt | 15% |
| III | 250jt - 500jt | 25% |
| IV | 500jt - 5M | 30% |
| V | > 5M | 35% |

---

#### 8.5 Submenu 4: Slip Gaji

**Render dari `pay_records`**, bukan tabel terpisah.

**Format Slip Gaji:**
```
┌─────────────────────────────────────┐
│ SLIP GAJI — Periode: Mei 2026       │
│ Nama: Budi Setiawan                 │
│ Jabatan: Staff IT                   │
├─────────────────────────────────────┤
│ PENDAPATAN                          │
│   Gaji Pokok         10.000.000     │
│   Tunjangan Tetap     2.000.000     │
│   Premi                 500.000     │
│   Overtime (10h)      1.500.000     │
│   LM (8h)             1.200.000     │
│   Total Pendapatan   15.200.000     │
├─────────────────────────────────────┤
│ POTONGAN                            │
│   BPJS Kesehatan        100.000     │
│   BPJS TK - JHT          200.000    │
│   BPJS TK - JP           100.000    │
│   PPh 21                 350.000    │
│   Denda Keterlambatan     50.000    │
│   Total Potongan         800.000    │
├─────────────────────────────────────┤
│ GAJI BERSIH          14.400.000     │
└─────────────────────────────────────┘
```

**Export:**
- Individual PDF (download)
- Bulk PDF (zip per periode)
- Bulk Excel (rekap per periode)

---

#### 8.6 Submenu 5: Pengelolaan THR

> **Tabel:** `emp_thr` (dibuat di Fase 4)

**Rules THR (default, bisa dikonfigurasi):**
| Masa Kerja | THR |
|---|---|
| ≥ 12 bulan | 1 × gaji pokok |
| 1 - 12 bulan | Proporsional: (bulan_kerja / 12) × gaji pokok |
| < 1 bulan | Tidak dapat |

**Flow Generate THR:**
1. Pilih tahun/periode THR
2. Filter karyawan aktif per tanggal cutoff (biasanya H-7 Lebaran)
3. Kalkulasi THR per karyawan: `masa_kerja × gaji_pokok / 12` (proporsional)
4. Simpan ke `emp_thr`
5. Export/render slip THR

---

#### 8.7 Struktur Tabel Payroll (Disederhanakan)

| Tabel | Fungsi | Status |
|---|---|---|
| `pay_periods` | Periode payroll | ✅ done |
| `pay_records` | Data gaji per karyawan per periode (+ snapshot embed) | ❌ not started |
| `pay_component_values` | Nilai komponen per payroll record | ❌ not started |
| `pay_configs` | Konfigurasi payroll | ❌ not started |
| `pay_settings` | Settings payroll | ❌ not started |
| `pay_bpjs_configs` | Konfigurasi BPJS | ❌ not started |
| `pay_pph_configs` | Konfigurasi PPh | ❌ not started |
| `pay_ptkp_rates` | Tarif PTKP | ❌ not started |
| `pay_ter_rates` | Tarif TER | ❌ not started |
| `pay_progressive_rates` | Tarif progressive | ❌ not started |
| `pay_service_allowances` | Tunjangan masa kerja | ❌ not started |
| `pay_audits` | Aplikasi bayangan (Supervisor) — perhitungan terpisah | ❌ not started |

Tabel yang **dihapus/digabung**:
- ~~`payroll_results`~~ → digabung ke `pay_records`
- ~~`payroll_breakdowns`~~ → JSON fields di `pay_records`
- ~~`payslips`~~ → di-generate/render dari `pay_records`
- ~~`payroll_component_snapshots`~~ → di `pay_component_values`
- ~~`payroll_employee_snapshots`~~ → embed di `pay_records`

---

#### 8.8 Backend Status

| Item | Status | Keterangan |
|---|---|---|
| Migration `pay_periods` | `done` | Periode payroll (basic) |
| Migration `pay_records` | `not started` | Data gaji per karyawan per periode |
| Migration `pay_component_values` | `not started` | Nilai komponen per record |
| Migration `pay_configs` | `not started` | Konfigurasi payroll |
| Migration `pay_settings` | `not started` | Settings payroll |
| Migration `pay_bpjs_configs` | `not started` | Konfigurasi BPJS |
| Migration `pay_pph_configs` | `not started` | Konfigurasi PPh |
| Migration `pay_ptkp_rates` | `not started` | Tarif PTKP |
| Migration `pay_ter_rates` | `not started` | Tarif TER |
| Migration `pay_progressive_rates` | `not started` | Tarif progressive |
| Migration `pay_service_allowances` | `not started` | Tunjangan masa kerja |
| Migration `pay_audits` | `not started` | Supervisor dashboard |
| Model `PayPeriod` | `done` | Basic, akan di-expand |
| Model `PayRecord` | `not started` | |
| Model lainnya | `not started` | PayComponentValue, PayConfig, dll |
| Controller `PayPeriodApiController` | `done` | Basic CRUD |
| Routes `Payroll/Routes/api.php` | `done` | Basic resource routes |
| Service `PayrollCalculator` | `not started` | Port logic dari hris-system |
| Service `BpjsCalculator` | `not started` | |
| Service `PphCalculator` | `not started` | |
| Service `ThrCalculator` | `not started` | |

#### 8.9 Frontend Status

| Item | Status | Keterangan |
|---|---|---|
| `Payroll/GajiKaryawan/Index.vue` | `not started` | List periode + generate |
| `Payroll/GajiKaryawan/Detail.vue` | `done` (mock) | Lihat pay_records per periode |
| `Payroll/Bpjs/Index.vue` | `not started` | Konfigurasi + kalkulasi BPJS |
| `Payroll/Pph/Index.vue` | `not started` | Konfigurasi + kalkulasi PPh |
| `Payroll/SlipGaji/Index.vue` | `not started` | Render + export slip |
| `Payroll/Thr/Index.vue` | `done` (mock) | Generate + export THR |
| `Payroll/Configs/Index.vue` | `done` (mock) | Konfigurasi payroll |

#### 8.10 Checklist Fase 8

- [x] Migration: `pay_periods`
- [x] Model: `PayPeriod`
- [x] Controller: `PayPeriodApiController`
- [x] Routes: `Payroll/Routes/api.php`
- [x] Frontend: Payroll Period Detail (mock)
- [x] Frontend: Payroll THR (mock)
- [x] Frontend: Payroll Configs (mock)
- [ ] Migration: `pay_records`, `pay_component_values`, `pay_configs`, `pay_settings`
- [ ] Migration: `pay_bpjs_configs`, `pay_pph_configs`, `pay_ptkp_rates`, `pay_ter_rates`, `pay_progressive_rates`
- [ ] Migration: `pay_service_allowances`, `pay_audits`
- [ ] Model: `PayRecord`, `PayComponentValue`, `PayConfig`, `PaySetting`
- [ ] Model: `PayBpjsConfig`, `PayPphConfig`, `PayPtkpRate`, `PayTerRate`, `PayProgressiveRate`
- [ ] Service: `PayrollCalculator` (generate gaji, port dari hris-system)
- [ ] Service: `BpjsCalculator` (kalkulasi BPJS)
- [ ] Service: `PphCalculator` (kalkulasi PPh 21 TER + progressive)
- [ ] Service: `ThrCalculator` (kalkulasi THR)
- [ ] Frontend: Gaji Karyawan Index + Generate
- [ ] Frontend: Perhitungan BPJS
- [ ] Frontend: Perhitungan PPh
- [ ] Frontend: Slip Gaji (render + export)
- [ ] Frontend: Integrasi semua halaman dengan API
- [ ] Test: generate payroll full flow
- [ ] Test: export slip gaji PDF/Excel
- [ ] Test: generate THR

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

---

## Daftar Scope Employee

Berikut adalah daftar Custom Query Scopes yang terdapat di model `Employee` (`app/Modules/Employee/Models/Employee.php`) untuk mempermudah pengambilan data:

1. **`scopeActiveInPeriod($query, $startDate, $endDate)`**
   * **Fungsi:** Mengambil data karyawan yang aktif *di dalam* suatu periode (berguna untuk perhitungan *payroll*, dsb).
   * **Aturan:** 
     - Tanggal bergabung (`join_date`) harus `<= $endDate`.
     - Tidak memiliki tanggal keluar (`end_date` adalah `NULL`) **ATAU** tanggal keluarnya `>= $startDate`.
   * **Catatan:** Scope ini mengabaikan kolom `is_active` saat ini, sehingga karyawan yang sekarang sudah *resign* tetap bisa dipanggil jika mereka masih aktif pada periode lampau tersebut.

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


## Progress UI Upgrade (Premium Dashboard)

- [x] **Organization Module**: Departments, Positions, Salary Grades, Admin Settings (Index UI Upgraded ke desain premium)
- [x] **Employee Module**: Data Karyawan (Index UI Upgraded ke desain premium dengan grid card dan BoxIcons)
