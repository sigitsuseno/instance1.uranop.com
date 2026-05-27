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
| **Roles** | super_admin, hr_area, hr_branch, staff | superadmin, hrmanager, adm_manager, hr_ast, hrbranch, admin |
| **Dashboard** | 1 dashboard (role-based views) | 2 dashboard: Admin + Supervisor |
| **Company/Branch** | `company_id` + `branch_id` di semua tabel | **Dihapus total.** Tabel companies & branches tetap ada sebagai data master, tanpa relasi ke tabel lain |
| **Instance** | Tidak ada | Tabel baru: `instances` |
| **Bahasa** | Mixed ID/EN | Indonesia (technical terms tetap EN: "generate", "import", dll) |

---

## Fase 0: Struktur Modular Backend

### 0.1 Module Structure (Wajib)

Struktur folder backend **wajib modular**, sama seperti `hris-system`:

```
app/Modules/{Module}/
├── Controllers/
│   ├── Api/                     ← KHUSUS UNTUK API
│   │   └── V1/
│   │       ├── {Module}ApiController.php
│   │       └── SubModule/
│   │           └── {SubModule}ApiController.php
│   └── Web/                     ← Khusus untuk Controller Web/UI (catch-all SPA)
│       └── {Module}WebController.php
├── Models/
│   └── {Model}.php
├── Resources/                   ← Laravel API Resources (JsonResource)
│   └── {Module}Resource.php
├── Routes/                      ← File route dipindah ke dalam modul
│   ├── web.php                  ← Route-route Web/UI
│   └── api.php                  ← Route-route API
├── Services/
│   └── {Module}Service.php
└── Providers/
    └── {Module}ServiceProvider.php
```

### 0.2 Daftar Module

| Module | Path | Deskripsi |
|---|---|---|
| **Instance** | `app/Modules/Instance/` | Kelola instance (BARU) |
| **Auth** | `app/Modules/Auth/` | Autentikasi, login, user management |
| **Organization** | `app/Modules/Organization/` | Company, Branch, Department, Position, SalaryGrade |
| **Employee** | `app/Modules/Employee/` | Karyawan, kontrak, keluarga, dokumen, terminasi |
| **Schedule** | `app/Modules/Schedule/` | WorkPattern, Shift, Roster, Calendar, Holiday |
| **Attendance** | `app/Modules/Attendance/` | Absensi, log import, lembur |
| **Leave** | `app/Modules/Leave/` | Cuti, tipe cuti, kebijakan, approval |
| **Payroll** | `app/Modules/Payroll/` | Penggajian, BPJS, PPH, THR |
| **AuditSection** | `app/Modules/AuditSection/` | Dashboard Supervisor, audit payroll |
| **Reports** | `app/Modules/Reports/` | Laporan-laporan |
| **Settings** | `app/Modules/Settings/` | Pengaturan sistem |
| **Notification** | `app/Modules/Notification/` | Notifikasi |
| **Shared** | `app/Modules/Shared/` | Traits, helpers, base classes |

### 0.3 Auto-Load Module Routes

Di `bootstrap/app.php`, tambahkan auto-loader untuk route dari setiap module:

```php
// bootstrap/app.php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Auto-load module routes
            $modules = glob(app_path('Modules/*/Routes/api.php'));
            foreach ($modules as $routeFile) {
                Route::prefix('api')
                    ->middleware('api')
                    ->group($routeFile);
            }
        },
    )
```

> **Catatan:** Di Laravel 13, `then` callback tersedia di `->withRouting()` untuk mendaftarkan route tambahan.

### 0.4 Konvensi Route Module

Setiap module mendaftarkan route dengan prefix sesuai nama module (lowercase, kebab-case):

```php
// app/Modules/Employee/Routes/api.php
use Illuminate\Support\Facades\Route;
use App\Modules\Employee\Controllers\Api\V1\EmployeeApiController;

Route::prefix('employees')->group(function () {
    Route::get('/', [EmployeeApiController::class, 'index']);
    Route::post('/', [EmployeeApiController::class, 'store']);
    Route::get('/{id}', [EmployeeApiController::class, 'show']);
    Route::put('/{id}', [EmployeeApiController::class, 'update']);
    Route::delete('/{id}', [EmployeeApiController::class, 'destroy']);
});
```

---

## Fase 1: Struktur Database Baru

### 1.1 Tabel Instance (baru)

```sql
instances
  id                BIGINT PRIMARY KEY
  company_id        BIGINT FK → companies.id   (hanya di tabel instances)
  branch_id         BIGINT FK → branches.id    (hanya di tabel instances)
  name              VARCHAR(255)   — 'Uranop Instance 1'
  slug              VARCHAR(255)   — 'instance1'
  domain            VARCHAR(255)   — 'instance1.uranop.com'
  database_name     VARCHAR(255)   — 'instance1_uranop'
  db_connection     VARCHAR(100)   — 'sqlite' | 'mysql'
  is_active         BOOLEAN DEFAULT 1
  settings          JSON
  metadata          JSON
  created_at, updated_at, softDeletes
```

- Satu instance = satu branch = satu database = satu deploy Laravel.
- Untuk project ini (`instance1`), data instance di-seed melalui `InstanceSeeder`.
- Tabel ini akan dipakai kelak saat membuat management console multi-instance.

### 1.2 Hapus Global Scope & Kolom company_id / branch_id

Karena 1 instance = 1 DB (isolasi penuh), **kolom `company_id` dan `branch_id` dihapus total dari semua tabel**.  
Tidak ada lagi filter, tidak ada lagi auto-set, tidak ada lagi session.

**Langkah:**

1. Hapus trait `HasCompanyScope` — tidak dipakai lagi
2. Hapus trait `HasBranchScope` — tidak dipakai lagi
3. Hapus middleware `BranchContext`
4. Hapus session `company_id` dan `branch_id` saat login
5. **Hapus kolom `company_id` dan `branch_id` dari semua migration** (buat migration baru untuk drop column)
6. Hapus semua `$table->foreignId('company_id')` dan `$table->foreignId('branch_id')` dari migration

### 1.3 Config Instance

```php
// config/instance.php
return [
    'id' => env('INSTANCE_ID', 1),
    'name' => env('INSTANCE_NAME', 'Instance 1'),
    'slug' => env('INSTANCE_SLUG', 'instance1'),
];
```

```env
# .env
INSTANCE_ID=1
INSTANCE_NAME="Uranop Instance 1"
INSTANCE_SLUG=instance1
```

Karena `company_id` dan `branch_id` sudah dihapus dari semua tabel, config instance tidak perlu menyimpan referensi ke company/branch.

---

## Fase 2: Roles & Permissions

### 2.1 Mapping Roles Lama → Baru

| Role Lama | Role Baru | Dashboard | Deskripsi |
|---|---|---|---|
| `super_admin` | **superadmin** | Admin + Supervisor | Akses penuh ke semua dashboard |
| `hr_area` | **hrmanager** | Admin | HR Manager — kelola karyawan, approval, payroll |
| — | **adm_manager** | Supervisor (full) | Admin Manager — bisa export, manage supervisor |
| `staff` | **hr_ast** | Admin | HR Assistant — view & input terbatas |
| `hr_branch` | **hrbranch** | Admin (view-only) | HR Branch — view organisasi & karyawan |
| — | **admin** | Supervisor (view) | Admin — lihat dashboard supervisor |

### 2.2 Aturan Akses Dashboard

| Role | Dashboard Admin | Dashboard Supervisor |
|---|---|---|
| **superadmin** | Full access | Full access |
| **hrmanager** | Full access | No access |
| **adm_manager** | No access | Full access (CRUD + Export) |
| **hrbranch** | View-only | No access |
| **hr_ast** | View + Input | No access |
| **admin** | No access | View-only |

> **Catatan:** Dashboard Admin dan Supervisor **tampilannya sama** (UI layout, sidebar, theme)  
> tapi **isinya berbeda** — beberapa tabel berbeda, rumus perhitungan berbeda, data berbeda.

### 2.3 Permission Baru

```
# Organization
view companies, create companies, edit companies, delete companies
view branches, create branches, edit branches, delete branches
view departments, create departments, edit departments, delete departments
view positions, create positions, edit positions, delete positions
view salary_grades, create salary_grades, edit salary_grades, delete salary_grades

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

### 2.4 Role ↔ Permission Matrix

| Permission Group | superadmin | hrmanager | adm_manager | hrbranch | hr_ast | admin |
|---|---|---|---|---|---|---|
| **Organization** | CRUD | View | — | View | — | — |
| **Employee** | CRUD | CRUD+Import+Export | — | View | View+Create+Edit | — |
| **Attendance** | All | View+Import+Edit | — | View | View | — |
| **Payroll** | All | Generate+View+Export | — | — | — | — |
| **Leave** | All | Approve+Manage | — | View | View | — |
| **Approval** | All | Approve+Reject | — | — | — | — |
| **Supervisor View** | All | — | All | — | — | All |
| **Supervisor Manage** | All | — | All | — | — | — |
| **Supervisor Export** | All | — | All | — | — | — |

### 2.5 Seeder Default

```php
// Super Admin
name: 'Super Admin', email: 'superadmin@uranop.com', role: superadmin

// HR Manager
name: 'HR Manager', email: 'hr@uranop.com', role: hrmanager

// Admin Manager
name: 'Admin Manager', email: 'adm@uranop.com', role: adm_manager
```

---

## Fase 3: Backend — API Structure

### 3.1 Route API per Module

```
/api
├── POST   /login              → Auth Module
├── POST   /logout             → Auth Module (auth:sanctum)
├── GET    /user               → Auth Module (auth:sanctum)
├── GET    /                   → API info
│
├── /organization              → app/Modules/Organization/Routes/api.php
│   ├── GET    /departments
│   ├── POST   /departments
│   ├── PUT    /departments/{id}
│   ├── DELETE /departments/{id}
│   ├── GET    /positions
│   ├── GET    /salary-grades
│   └── ...
│
├── /employees                 → app/Modules/Employee/Routes/api.php
│   ├── GET    /
│   ├── POST   /
│   ├── GET    /{id}
│   ├── PUT    /{id}
│   ├── DELETE /{id}
│   ├── POST   /import
│   ├── GET    /export
│   └── /{id}/contracts, families, documents, salaries, ...
│
├── /attendance                → app/Modules/Attendance/Routes/api.php
├── /leave                     → app/Modules/Leave/Routes/api.php
├── /payroll                   → app/Modules/Payroll/Routes/api.php
├── /schedule                  → app/Modules/Schedule/Routes/api.php
├── /settings                  → app/Modules/Settings/Routes/api.php
├── /reports                   → app/Modules/Reports/Routes/api.php
├── /supervisor                → app/Modules/AuditSection/Routes/api.php
└── /notifications             → app/Modules/Notification/Routes/api.php
```

### 3.2 Konversi Controller — Inertia → API

**Contoh — EmployeeController::index():**

```php
// LAMA (Inertia)
public function index()
{
    $employees = Employee::with(['department', 'position'])->paginate(25);
    return Inertia::render('Employee/Index', ['employees' => $employees]);
}

// BARU (API) — app/Modules/Employee/Controllers/Api/V1/EmployeeApiController.php
public function index(Request $request)
{
    $employees = Employee::with(['department', 'position'])
        ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
        ->paginate($request->per_page ?? 25);

    return EmployeeResource::collection($employees);
}
```

### 3.3 API Resources (JsonResource)

Setiap module wajib punya Resource class untuk response konsisten:

```php
// app/Modules/Employee/Resources/EmployeeResource.php
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'position' => new PositionResource($this->whenLoaded('position')),
            'employment_status' => $this->employment_status,
            'join_date' => $this->join_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

### 3.4 Hapus Dependensi yang Tidak Diperlukan

```bash
composer remove inertiajs/inertia-laravel
npm remove @inertiajs/vue3
```

---

## Fase 4: Frontend — Vue 3 SPA

### 4.1 Struktur Folder Target

```
resources/js/
├── App.vue                          ← Layout root (sidebar + dark toggle)
├── app.js                            ← Vue bootstrapper
│
├── Components/                       ← GLOBAL UI COMPONENTS (Agnostik)
│   ├── BaseButton.vue
│   ├── BaseModal.vue
│   ├── BaseCard.vue
│   ├── TextInput.vue
│   ├── SelectInput.vue
│   ├── DatePicker.vue
│   ├── Badge.vue
│   ├── ConfirmDialog.vue
│   └── Table/
│       ├── DataTable.vue
│       └── Pagination.vue
│
├── Layouts/                          ← GLOBAL LAYOUTS
│   ├── AuthenticatedLayout.vue       ← Sidebar + header + main content
│   └── GuestLayout.vue               ← Login & public pages
│
├── Pages/                            ← HALAMAN PER MODUL
│   ├── Auth/
│   │   └── Login.vue
│   │
│   ├── Admin/                        ← Dashboard Admin
│   │   ├── Dashboard.vue
│   │   ├── Organization/
│   │   │   ├── Departments/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Components/
│   │   │   │       └── DepartmentForm.vue
│   │   │   ├── Positions/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Components/
│   │   │   │       └── PositionForm.vue
│   │   │   └── SalaryGrades/
│   │   │       ├── Index.vue
│   │   │       └── Components/
│   │   │           └── SalaryGradeForm.vue
│   │   ├── Employees/
│   │   │   ├── Index.vue
│   │   │   ├── Show.vue
│   │   │   ├── Create.vue
│   │   │   ├── Edit.vue
│   │   │   └── Components/
│   │   │       ├── EmployeeForm.vue
│   │   │       ├── ContractForm.vue
│   │   │       ├── FamilyForm.vue
│   │   │       └── DocumentUpload.vue
│   │   ├── Attendance/
│   │   │   ├── Index.vue
│   │   │   ├── LogImport.vue
│   │   │   ├── Roster.vue
│   │   │   ├── Overtime/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Components/
│   │   │   │       └── OvertimeForm.vue
│   │   │   └── Components/
│   │   ├── Leave/
│   │   │   ├── Index.vue
│   │   │   ├── Approvals.vue
│   │   │   ├── Settings.vue
│   │   │   └── Components/
│   │   │       └── LeaveRequestForm.vue
│   │   ├── Payroll/
│   │   │   ├── Periods/
│   │   │   │   ├── Index.vue
│   │   │   │   └── Detail.vue
│   │   │   ├── Configs/
│   │   │   │   └── Index.vue
│   │   │   └── Components/
│   │   │       ├── PayslipModal.vue
│   │   │       └── BpjsCard.vue
│   │   ├── Schedule/
│   │   │   ├── WorkPatterns/
│   │   │   │   └── Index.vue
│   │   │   ├── Shifts/
│   │   │   │   └── Index.vue
│   │   │   ├── Calendars/
│   │   │   │   └── Index.vue
│   │   │   └── Roster/
│   │   │       └── Index.vue
│   │   ├── Reports/
│   │   │   ├── Index.vue
│   │   │   └── Components/
│   │   └── Settings/
│   │       ├── Index.vue
│   │       └── Components/
│   │
│   └── Supervisor/                   ← Dashboard Supervisor
│       ├── Dashboard.vue
│       ├── Attendance/
│       │   ├── Index.vue
│       │   ├── Roster/
│       │   │   └── Index.vue
│       │   └── Components/
│       ├── Payroll/
│       │   ├── Index.vue
│       │   └── Components/
│       ├── Leave/
│       │   └── Index.vue
│       ├── Employee/
│       │   └── Index.vue
│       └── Reports/
│           └── Index.vue
│
├── Composables/                      ← GLOBAL VUE COMPOSABLES
│   ├── useApi.js                     ← Sudah ada
│   ├── useAuth.js                    ← Login/logout/user state
│   ├── usePermission.js              ← Permission checker
│   ├── useCurrency.js                ← Rupiah formatter
│   ├── useDate.js                    ← Date formatting
│   ├── useTheme.js                   ← Dark/light mode (sudah ada)
│   ├── usePagination.js              ← Pagination logic
│   └── useNotification.js            ← Toast notification
│
├── Stores/                           ← Pinia stores
│   ├── auth.js
│   ├── permission.js
│   └── notification.js
│
└── router/
    └── index.js                       ← Vue Router routes
```

### 4.2 Router Structure

```js
// resources/js/router/index.js

const routes = [
    // Guest
    { path: '/login', component: Login, meta: { guest: true, layout: 'guest' } },

    // Admin Dashboard (default) — superadmin, hrmanager, hrbranch, hr_ast
    {
        path: '/',
        component: AuthenticatedLayout,
        meta: { auth: true },
        children: [
            { path: '', name: 'admin.dashboard', component: AdminDashboard },
            { path: 'organization/departments', component: DepartmentsIndex },
            { path: 'organization/positions', component: PositionsIndex },
            { path: 'organization/salary-grades', component: SalaryGradesIndex },
            { path: 'employees', component: EmployeesIndex },
            { path: 'employees/:id', component: EmployeeShow },
            { path: 'employees/create', component: EmployeeCreate },
            { path: 'employees/:id/edit', component: EmployeeEdit },
            { path: 'attendance', component: AttendanceIndex },
            { path: 'attendance/import', component: LogImport },
            { path: 'attendance/roster', component: RosterIndex },
            { path: 'attendance/overtime', component: OvertimeIndex },
            { path: 'leave', component: LeaveIndex },
            { path: 'leave/approvals', component: LeaveApprovals },
            { path: 'leave/settings', component: LeaveSettings },
            { path: 'payroll', component: PayrollPeriodsIndex },
            { path: 'payroll/periods/:id', component: PayrollPeriodDetail },
            { path: 'schedule/work-patterns', component: WorkPatternsIndex },
            { path: 'schedule/shifts', component: ShiftsIndex },
            { path: 'schedule/calendars', component: CalendarsIndex },
            { path: 'reports', component: ReportsIndex },
            { path: 'settings', component: SettingsIndex },
        ],
    },

    // Supervisor Dashboard — adm_manager, admin, superadmin
    {
        path: '/supervisor',
        component: AuthenticatedLayout,
        meta: { auth: true, role: ['adm_manager', 'admin', 'superadmin'] },
        children: [
            { path: '', name: 'supervisor.dashboard', component: SupervisorDashboard },
            { path: 'attendance', component: SupervisorAttendance },
            { path: 'attendance/roster', component: SupervisorRoster },
            { path: 'payroll', component: SupervisorPayroll },
            { path: 'leave', component: SupervisorLeave },
            { path: 'employee', component: SupervisorEmployee },
            { path: 'reports', component: SupervisorReports },
        ],
    },
]
```

### 4.3 Sidebar Menu (Dynamic by Role)

```js
// Admin Dashboard — visible to: superadmin, hrmanager, hrbranch, hr_ast
const adminMenu = [
    { label: 'Dashboard', icon: 'home', route: 'admin.dashboard' },
    {
        label: 'Organisasi',
        icon: 'building',
        children: [
            { label: 'Departemen', route: '/organization/departments' },
            { label: 'Jabatan', route: '/organization/positions' },
            { label: 'Grade Gaji', route: '/organization/salary-grades' },
        ],
    },
    { label: 'Karyawan', icon: 'users', route: '/employees' },
    {
        label: 'Kehadiran',
        icon: 'calendar-check',
        children: [
            { label: 'Absensi', route: '/attendance' },
            { label: 'Import Log', route: '/attendance/import' },
            { label: 'Roster', route: '/attendance/roster' },
            { label: 'Lembur', route: '/attendance/overtime' },
        ],
    },
    {
        label: 'Cuti',
        icon: 'umbrella',
        children: [
            { label: 'Daftar Cuti', route: '/leave' },
            { label: 'Approval', route: '/leave/approvals' },
            { label: 'Pengaturan', route: '/leave/settings' },
        ],
    },
    { label: 'Generate Gaji', icon: 'file-invoice', route: '/payroll' },
    { label: 'THR', icon: 'gift', route: '/payroll/thr' },
    { label: 'Generate Jadwal', icon: 'clock', route: '/schedule/work-patterns' },
    { label: 'Laporan', icon: 'chart-bar', route: '/reports' },
];

// Supervisor Dashboard — visible to: adm_manager, admin, superadmin
const supervisorMenu = [
    { label: 'Dashboard', icon: 'home', route: 'supervisor.dashboard' },
    { label: 'Kehadiran', icon: 'calendar-check', route: '/supervisor/attendance' },
    { label: 'Roster', icon: 'user-clock', route: '/supervisor/attendance/roster' },
    { label: 'Generate Gaji', icon: 'file-invoice', route: '/supervisor/payroll' },
    { label: 'THR', icon: 'gift', route: '/supervisor/payroll/thr' },
    { label: 'Cuti', icon: 'umbrella', route: '/supervisor/leave' },
    { label: 'Karyawan', icon: 'users', route: '/supervisor/employee' },
    { label: 'Laporan', icon: 'chart-bar', route: '/supervisor/reports' },
];
```

User dengan role `superadmin` akan melihat **dua sidebar** (switch via dropdown/tab di header).  
User lain hanya melihat satu sidebar sesuai peran mereka.

### 4.4 Konvensi Bahasa

- **Menu & label**: Bahasa Indonesia
- **Technical terms**: Tetap EN jika terjemahan Indonesia ambigu
  - Contoh: `generate` (bukan "generasi"), `import` (bukan "impor"), `export` (bukan "ekspor")
  - `attendance` → "Kehadiran" (clear)
  - `payroll` → "Generate Gaji" (label menu)
  - `leave` → "Cuti"
  - `approval` → "Approval" (bukan "persetujuan" — terlalu panjang untuk menu)

---

## Rencana Renaming & Remapping Tabel

> **Catatan:** Setelah semua tabel di-port, akan dilakukan **renaming dan remapping** tabel secara bertahap per phase.  
> Tujuannya: menyederhanakan nama, konsistensi naming convention, dan menyesuaikan dengan struktur modul baru.

### Konvensi Naming Baru

| Aspek | Lama (hris-system) | Baru (instance1) |
|---|---|---|
| **Prefix tabel** | Tidak konsisten | Per module: `{module}_` (ex: `att_`, `pay_`, `lve_`) |
| **Singular/Plural** | Mixed | **Plural** untuk tabel data, **singular** untuk konfig |
| **Soft delete** | `deleted_at` (all) | Tetap |
| **Timestamps** | `created_at`, `updated_at` (all) | Tetap |
| **Foreign key** | `{name}_id` | Tetap |
| **Pivot table** | `{a}_{b}` (singular) | Tetap |

### Daftar Rencana Rename

> **Dikerjakan per phase, setelah module ter-port dan berfungsi.**

#### Phase Rename #1: Attendance Module
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `raw_logs` | `att_raw_logs` | Prefix module |
| `attendance_logs` | `att_logs` | Singkat, prefix att_ |
| `attendance_autologs` | `att_autologs` | Singkat |
| `attendance_prepares` | `att_prepares` | Singkat |
| `attendance_records` | `att_records` | Singkat |
| `attendance_snapshots` | `att_snapshots` | Singkat |
| `attendance_summaries` | `att_summaries` | Singkat |
| `attendance_consecutive_days` | `att_consecutive_days` | Singkat |
| `attendance_configs` | `att_configs` | Singkat |
| `scan_detection_configs` | `att_scan_configs` | Prefix module |
| `overtime_rules` | `att_overtime_rules` | Prefix module |
| `overtimes` | `att_overtimes` | Prefix module |
| `permit_requests` | `att_permits` | Singkat |

#### Phase Rename #2: Schedule Module
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `work_pattern_types` | `sch_pattern_types` | Prefix module |
| `work_patterns` | `sch_patterns` | Prefix module |
| `work_pattern_details` | `sch_pattern_details` | Prefix module |
| `shifts` | `sch_shifts` | Prefix module |
| `employee_shift_rosters` | `sch_rosters` | Singkat |
| `working_calendars` | `sch_calendars` | Prefix module |
| `holidays` | `sch_holidays` | Prefix module |

#### Phase Rename #3: Leave Module
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `leave_period_configs` | `lve_period_configs` | Prefix module |
| `leave_periods` | `lve_periods` | Prefix module |
| `leave_types` | `lve_types` | Prefix module |
| `leave_policies` | `lve_policies` | Prefix module |
| `leave_requests` | `lve_requests` | Prefix module |
| `leave_documents` | `lve_documents` | Prefix module |
| `leave_entitlements` | `lve_entitlements` | Prefix module |
| `leave_balances` | `lve_balances` | Prefix module |

#### Phase Rename #4: Payroll Module
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `payroll_settings` | `pay_settings` | Singkat |
| `payroll_configs` | `pay_configs` | Singkat |
| `payroll_periods` | `pay_periods` | Singkat |
| `payrolls` | `pay_records` | Hindari plural ambiguity |
| `payroll_audits` | `pay_audits` | Singkat |
| `payroll_component_values` | `pay_component_values` | Singkat |
| `payroll_employee_snapshots` | `pay_emp_snapshots` | Singkat |
| `payroll_component_snapshots` | `pay_comp_snapshots` | Singkat |
| `payroll_results` | `pay_results` | Singkat |
| `payroll_breakdowns` | `pay_breakdowns` | Singkat |
| `payslips` | `pay_slips` | Prefix module |
| `salary_components` | `pay_components` | Pindah ke module payroll |
| `bpjs_configs` | `pay_bpjs_configs` | Prefix module |
| `pph_configs` | `pay_pph_configs` | Prefix module |
| `ptkp_rates` | `pay_ptkp_rates` | Prefix module |
| `ter_rates` | `pay_ter_rates` | Prefix module |
| `progressive_rates` | `pay_progressive_rates` | Prefix module |
| `overtime_rules` (payroll) | `pay_overtime_rules` | Prefix module |
| `service_year_allowances` | `pay_service_allowances` | Prefix module |

#### Phase Rename #5: Employee Module
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `employees` | Tetap | Sudah baik |
| `employee_groups` | `emp_groups` | Singkat |
| `employee_titles` | `emp_titles` | Singkat |
| `employee_periodes` | `emp_periodes` | Singkat |
| `employee_contracts` | `emp_contracts` | Singkat |
| `employee_position_histories` | `emp_position_histories` | Singkat |
| `employee_families` | `emp_families` | Singkat |
| `employee_documents` | `emp_documents` | Singkat |
| `employee_salaries` | `emp_salaries` | Singkat |
| `employee_salary_components` | `emp_salary_components` | Singkat |
| `employee_salary_breakdowns` | `emp_salary_breakdowns` | Singkat |
| `employee_terminations` | `emp_terminations` | Singkat |
| `employee_bpjs` | `emp_bpjs` | Singkat |
| `employee_thr` | `emp_thr` | Singkat |

#### Phase Rename #6: Organization Module
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `companies` | Tetap | Sudah baik |
| `company_settings` | `org_company_settings` | Prefix module |
| `branches` | Tetap | Sudah baik |
| `branch_settings` | `org_branch_settings` | Prefix module |
| `departments` | `org_departments` | Prefix module |
| `positions` | `org_positions` | Prefix module |
| `salary_grades` | `org_salary_grades` | Prefix module |
| `salary_grade_histories` | `org_salary_grade_histories` | Prefix module |

#### Phase Rename #7: User & Auth
| Nama Lama | Nama Baru | Alasan |
|---|---|---|
| `users` | Tetap | Sudah baik |
| `user_branches` | Tetap | Sudah baik |
| `user_preferences` | Tetap | Sudah baik |
| `personal_access_tokens` | Tetap | Sanctum default |

---

## Fase 5: Step-by-Step Eksekusi

### Step 1 — Setup Foundation (IN PROGRESS)

- [x] Laravel 13 project `instance1.uranop.com` terinisialisasi
- [x] Vue 3 SPA terinstall (Vue Router + Tailwind 4 + theme + dark mode)
- [x] API routes dasar + Sanctum terinstall
- [x] `.env` local & production terkonfigurasi
- [x] Symlink ke `hris-system` untuk referensi
- [ ] Buat tabel `instances` migration + model + seeder
- [ ] Buat `config/instance.php`
- [ ] Restruktur backend ke `app/Modules/...`
- [ ] Setup auto-load module routes di `bootstrap/app.php`

### Step 2 — Port Migrations & Models (P0 → P1)

**Module Instance (BARU):**
- [ ] `app/Modules/Instance/Models/Instance.php`
- [ ] `app/Modules/Instance/database/migrations/*_create_instances_table.php`
- [ ] `app/Modules/Instance/database/seeders/InstanceSeeder.php`

**Module Auth:**
- [ ] Copy & modifikasi migration: users, password_reset_tokens, sessions
- [ ] Copy model: `User.php` → hapus `HasCompanyScope`, tambah `HasApiTokens`
- [ ] Copy Spatie permissions tables (roles, permissions, model_has_roles, dll)
- [ ] Buat `RolePermissionSeeder` dengan mapping role baru
- [ ] Buat `UserSeeder` dengan default user (superadmin, hrmanager, adm_manager)
- [ ] Copy model: `UserPreference.php`

**Module Organization:**
- [ ] Copy migration: companies, branches, company_settings, branch_settings, departments, positions, salary_grades, salary_grade_histories
- [ ] **Modifikasi migration:** Hapus semua kolom `company_id` dan `branch_id` dari migration
- [ ] Copy semua model → hapus `HasCompanyScope`, `HasBranchScope`, hapus relasi `company()` dan `branch()`
- [ ] Copy seeder: Company, OrganizationMaster, dll → sesuaikan data

**Module Employee:**
- [ ] Copy migration: employees, employee_contracts, employee_families, employee_documents, employee_salaries, employee_position_histories, employee_terminations, employee_groups, employee_titles, employee_periodes, employee_salary_components, employee_salary_breakdowns
- [ ] Copy semua model → hapus global scope traits, hapus relasi company/branch
- [ ] **Modifikasi migration:** Hapus semua kolom `company_id` dan `branch_id`

### Step 3 — Port Controllers → API (P1 → P2)

**Setiap module, lakukan:**
- [ ] Copy controller dari `hris-system/app/Modules/{Module}/Controllers/`
- [ ] Konversi ke API controller:
  - `Inertia::render()` → `response()->json()` atau `new Resource()`
  - `redirect()->route()` → return JSON response
  - `request()->validate()` → tetap (Laravel auto-return 422 JSON)
- [ ] Buat `Resources/{Module}Resource.php` untuk setiap model utama
- [ ] Buat `Routes/api.php` di setiap module
- [ ] Test endpoint via Postman/Bruno

**Module yang perlu prioritas controller:**
1. Auth (login, logout, user)
2. Organization (departments, positions, salary-grades — sering dipakai dropdown)
3. Employee (CRUD + import/export)
4. Schedule (work patterns, shifts, calendars)
5. Attendance (logs, roster, overtime)
6. Leave (requests, approvals)
7. Payroll (periods, generation, bpjs, pph)
8. AuditSection/Supervisor
9. Reports
10. Settings

### Step 4 — Build UI (Vue SPA) (P1 → P4)

**Komponen Global:**
- [ ] `BaseButton.vue` — button dengan variant (primary, secondary, danger, ghost)
- [ ] `BaseModal.vue` — modal dialog
- [ ] `BaseCard.vue` — card container
- [ ] `TextInput.vue` — text input dengan label, error, icon
- [ ] `SelectInput.vue` — select dropdown
- [ ] `DatePicker.vue` — date input
- [ ] `Badge.vue` — status badge
- [ ] `ConfirmDialog.vue` — konfirmasi delete/action
- [ ] `DataTable.vue` — tabel dengan sort, search, column toggle
- [ ] `Pagination.vue` — pagination

**Layouts:**
- [ ] `AuthenticatedLayout.vue` — sidebar dinamis + header + main slot
- [ ] `GuestLayout.vue` — centered card untuk login

**Auth:**
- [ ] `Login.vue` — form login dengan validasi, error handling
- [ ] `useAuth.js` composable — login, logout, get user, token management

**Admin Pages:**
- [ ] `Dashboard.vue` — stat cards (total karyawan, hadir hari ini, dll)
- [ ] `Employees/Index.vue` — datatable + search + filter + create button
- [ ] `Employees/Show.vue` — detail karyawan + tabs (contract, family, docs)
- [ ] `Employees/Create.vue` — form wizard
- [ ] `Attendance/Index.vue` — kalender absensi + tabel
- [ ] `Leave/Index.vue` — tabel pengajuan cuti
- [ ] `Payroll/Periods/Index.vue` — daftar periode + generate

**Supervisor Pages:**
- [ ] `Dashboard.vue` — stat cards khusus supervisor
- [ ] `Attendance/Index.vue` — absensi + roster
- [ ] `Payroll/Index.vue` — hasil generate + export
- [ ] `Reports/Index.vue` — laporan

### Step 5 — Testing & QA

- [ ] Test semua API endpoint via Bruno/Postman
- [ ] Test UI flow: login → dashboard → CRUD → logout
- [ ] Test role-based access (login as 6 different roles)
- [ ] Test dark/light mode toggle
- [ ] Test responsive layout (mobile sidebar collapse)
- [ ] Test error handling (invalid login, 401, 403, 422, 500)
- [ ] Test import Excel (karyawan, absensi)
- [ ] Test export Excel/PDF

### Step 6 — Deploy ke VPS (CloudPanel / Ubuntu 24)

- [ ] Push code ke repository
- [ ] Setup database MySQL di VPS: `CREATE DATABASE instance1_uranop`
- [ ] Copy `.env.production` → `.env` di VPS, isi semua credentials
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan key:generate`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=InstanceSeeder`
- [ ] `php artisan db:seed --class=RolePermissionSeeder`
- [ ] `php artisan db:seed --class=UserSeeder`
- [ ] `npm ci && npm run build`
- [ ] Set document root ke `public/`
- [ ] Setup SSL via CloudPanel (Let's Encrypt)
- [ ] Konfigurasi Nginx: redirect semua request ke `index.php` (SPA fallback)
- [ ] Setup cron job: `* * * * * php artisan schedule:run`
- [ ] Setup queue worker: `php artisan queue:work --daemon`

---

## Daftar Module & Prioritas Migrasi

| # | Module | Tabel | Prioritas | Kompleksitas | Ketergantungan |
|---|---|---|---|---|---|
| 1 | **Instance** | instances (BARU) | **P0** | Rendah | — |
| 2 | **Auth + User** | users, roles, permissions, user_branches, user_preferences | **P0** | Rendah | Instance |
| 3 | **Organization** | companies, branches, departments, positions, salary_grades | **P1** | Rendah | Instance |
| 4 | **Employee** | employees, employee_contracts, employee_families, employee_documents, employee_salaries, employee_terminations, employee_groups | **P1** | Tinggi | Organization |
| 5 | **Schedule** | work_patterns, shifts, employee_shift_rosters, working_calendars, holidays | **P1** | Sedang | Organization |
| 6 | **Attendance** | attendance_logs, attendance_prepares, attendance_records, attendance_autologs, overtime_rules, raw_logs | **P2** | Tinggi | Employee, Schedule |
| 7 | **Leave** | leave_types, leave_policies, leave_requests, leave_periods, leave_entitlements, leave_balances | **P2** | Sedang | Employee |
| 8 | **Payroll** | payroll_periods, payrolls, salary_components, bpjs_configs, pph_configs, ter_rates, ptkp_rates | **P3** | Sangat Tinggi | Employee, Attendance, Leave |
| 9 | **AuditSection** | payroll_audits, attendance_snapshots, employee_thr, employee_salary_breakdowns | **P3** | Sedang | Payroll, Attendance |
| 10 | **Reports** | (aggregasi dari module lain) | **P4** | Rendah | Semua module |
| 11 | **Settings** | system_settings, employee_groups, employee_titles | **P4** | Rendah | — |

---

## Lampiran: Checklist Module Completion

Salin checklist ini ke tracking tool (Linear/Notion/GitHub Projects):

```
### Fase 0: Foundation
- [ ] Module Instance: migration + model + seeder + config
- [ ] Auto-load module routes dari bootstrap/app.php
- [ ] Setup module folder structure untuk semua module (template kosong)

### Fase P0: Auth
- [ ] Migration: users, roles, permissions, user_branches, user_preferences
- [ ] Model: User (dengan HasApiTokens, HasRoles)
- [ ] Seeder: RolePermissionSeeder, UserSeeder
- [ ] Controller: AuthApiController (login, logout, user)
- [ ] Routes: Auth/Routes/api.php
- [ ] Frontend: Login page, useAuth composable
- [ ] Frontend: Auth route guard (redirect ke /login jika belum login)

### Fase P1: Organization
- [ ] Migration: companies, branches, departments, positions, salary_grades
- [ ] Model: Company, Branch, Department, Position, SalaryGrade
- [ ] Controller: OrganizationApiController (CRUD departments, positions, grades)
- [ ] Routes: Organization/Routes/api.php
- [ ] Resources: DepartmentResource, PositionResource, SalaryGradeResource
- [ ] Frontend: Departments CRUD pages
- [ ] Frontend: Positions CRUD pages
- [ ] Frontend: SalaryGrades CRUD pages

### Fase P1: Employee
- [ ] Migration: semua tabel employee
- [ ] Model: semua model employee
- [ ] Controller: EmployeeApiController (CRUD + import + export)
- [ ] Routes: Employee/Routes/api.php
- [ ] Resources: EmployeeResource
- [ ] Frontend: Employee Index (datatable)
- [ ] Frontend: Employee Show (detail + tabs)
- [ ] Frontend: Employee Create/Edit (form)
- [ ] Frontend: Import Excel page
- [ ] Frontend: Contract, Family, Document sub-pages

### Fase P1: Schedule
- [ ] Migration: work_patterns, shifts, rosters, calendars, holidays
- [ ] Model: semua model schedule
- [ ] Controller: ScheduleApiController
- [ ] Routes: Schedule/Routes/api.php
- [ ] Frontend: WorkPatterns Index
- [ ] Frontend: Shifts Index
- [ ] Frontend: Calendars Index
- [ ] Frontend: Roster page

### Fase P2: Attendance
- [ ] Migration: semua tabel attendance
- [ ] Model: semua model attendance
- [ ] Controller: AttendanceApiController
- [ ] Routes: Attendance/Routes/api.php
- [ ] Frontend: Attendance Index (kalender + tabel)
- [ ] Frontend: Log Import page
- [ ] Frontend: Roster page
- [ ] Frontend: Overtime management

### Fase P2: Leave
- [ ] Migration: leave_types, leave_policies, leave_requests, leave_periods, etc.
- [ ] Model: semua model leave
- [ ] Controller: LeaveApiController
- [ ] Routes: Leave/Routes/api.php
- [ ] Frontend: Leave Index (daftar pengajuan)
- [ ] Frontend: Leave Approval page
- [ ] Frontend: Leave Settings page

### Fase P3: Payroll
- [ ] Migration: payroll_periods, payrolls, salary_components, configs
- [ ] Model: semua model payroll
- [ ] Controller: PayrollApiController
- [ ] Routes: Payroll/Routes/api.php
- [ ] Service: PayrollCalculator (port logic dari hris-system)
- [ ] Frontend: Payroll Periods Index
- [ ] Frontend: Payroll Period Detail (hasil generate)
- [ ] Frontend: BPJS Config page
- [ ] Frontend: PPH Config page

### Fase P3: AuditSection (Supervisor)
- [ ] Migration: payroll_audits, employee_thr, employee_salary_breakdowns
- [ ] Migration: attendance_snapshots (supervisor version)
- [ ] Model: semua model audit
- [ ] Controller: AuditApiController (endpoint supervisor)
- [ ] Routes: AuditSection/Routes/api.php
- [ ] Frontend: Supervisor Dashboard
- [ ] Frontend: Supervisor Attendance + Roster
- [ ] Frontend: Supervisor Payroll (generate + export)
- [ ] Frontend: Supervisor Employee list
- [ ] Frontend: Supervisor Reports

### Fase P4: Reports
- [ ] Controller: ReportApiController (aggregation endpoints)
- [ ] Routes: Reports/Routes/api.php
- [ ] Frontend: Attendance Report
- [ ] Frontend: Payroll Report
- [ ] Frontend: Tax Report
- [ ] Frontend: Export Excel/PDF buttons

### Fase P4: Settings
- [ ] Migration: system_settings
- [ ] Model: SystemSetting
- [ ] Controller: SettingsApiController
- [ ] Routes: Settings/Routes/api.php
- [ ] Frontend: Settings page

### Deploy
- [ ] Setup VPS database + env
- [ ] Run migrations + seeders
- [ ] Build frontend
- [ ] SSL + Nginx config
- [ ] Smoke test semua endpoint + UI
```

---

## Pertanyaan Terjawab

| # | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Tabel instances sekarang atau nanti? | **Sekarang.** Dibuat lengkap dengan model, migration, seeder, config |
| 2 | Struktur backend? | **Tetap modular** `app/Modules/{Module}/...` seperti hris-system |
| 3 | Company/branch tetap ada? | **Ya**, sebagai tabel referensi data, tanpa global scope |
| 4 | Role hrbranch & admin? | Masing-masing **terpisah**: hrbranch ke Admin dashboard, admin ke Supervisor dashboard. Superadmin bisa akses **keduanya** |
| 5 | Modul Supervisor? | **Tampilan sama** dengan Admin tapi **isi berbeda** (tabel & perhitungan berbeda) |
| 6 | Import fingerprint? | **Tetap di module Attendance** |
| 7 | Renaming & remapping tabel? | **Ya, bertahap per phase.** Semua tabel akan di-rename dengan prefix module (lihat bagian Rencana Renaming) |
| 8 | THR di dashboard mana? | **Kedua dashboard** (Admin + Supervisor) |
| 9 | Bahasa aplikasi? | **Bahasa Indonesia** untuk menu. Technical terms tetap EN (generate, import, export) |
