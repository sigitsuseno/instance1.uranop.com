# Rencana Pengerjaan Modul Auth — instance1.uranop.com

> **Sumber referensi utama:** `hris-system` (D:\laragon\www\hris-system\)  
> **Target:** `instance1.uranop.com` (H:\laragon\www\instance1.uranop.com\)  
> **Fase:** P0 (Auth + User) sesuai MIGRATION_GUIDE.md

---

## Ikhtisar

Modul Auth mencakup:

| Kategori | Sub-item |
|---|---|
| **Backend** | Install Spatie, migration users/roles/permissions, model User, AuthApiController, route module, seeder |
| **Frontend** | Landing page, perbaikan Login, perbaikan Layout (Sidebar/Topbar/Footer), Dropdown component, hubungkan auth store ke API, route guard |

---

## A. BACKEND

### A1. Install Spatie Laravel-Permission

```bash
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### A2. Buat Struktur Module Auth

```
app/Modules/Auth/
├── Controllers/
│   └── Api/
│       └── V1/
│           └── AuthApiController.php   ← login, logout, user
├── Models/
│   ├── User.php                        ← HasApiTokens + HasRoles
│   └── UserPreference.php
├── Resources/
│   └── AuthResource.php                ← JsonResource untuk user response
├── Routes/
│   └── api.php                         ← POST /login, POST /logout, GET /user
└── Providers/
    └── AuthServiceProvider.php
```

### A3. Auto-Load Module Routes

Edit `bootstrap/app.php`, tambahkan auto-loader:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::prefix('api')
            ->middleware('api')
            ->group(function () {
                // Core auth routes (login/logout/user)
                require app_path('Modules/Auth/Routes/api.php');
            });
    },
)
```

### A4. Migrations

#### `database/migrations/xxxx_xx_xx_000001_create_users_table.php`

Kolom users (TANPA company_id, TANPA branch_id):

```
id, name, email, email_verified_at, password, remember_token,
employee_number (nullable unique), phone (nullable),
is_active (default true), last_login_at (nullable), last_login_ip (nullable),
user_type enum('superadmin','hrmanager','adm_manager','hrbranch','hr_ast','admin') default 'hr_ast',
created_at, updated_at, softDeletes
```

> **Tidak ada company_id dan branch_id** (sesuai Fase 1 MIGRATION_GUIDE.md)

#### `database/migrations/xxxx_xx_xx_000002_create_user_preferences_table.php`

```
id, user_id (FK), theme, language, date_format, time_format, timezone,
notify_email, notify_in_app, notify_whatsapp,
default_dashboard, dashboard_layout (JSON), updated_by (FK users),
created_at, updated_at
```

#### `database/migrations/xxxx_xx_xx_000003_create_user_branches_table.php`

```
id, user_id (FK), branch_id (FK), is_default,
created_at, updated_at,
unique(user_id, branch_id)
```

> **Catatan:** Tabel `branches` belum ada, tapi FK tetap dibuat. Migration akan berhasil jika tabel branches dibuat duluan (di fase Organization P1). Untuk sementara, jalankan migration ini setelah Organization.

#### `database/migrations/xxxx_xx_xx_000004_create_permission_tables.php`

Spatie auto-generated migration.

### A5. Models

#### `app/Modules/Auth/Models/User.php`

```php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'employee_number', 'phone',
        'is_active', 'last_login_at', 'last_login_ip', 'user_type',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
```

> Tidak ada trait HasCompanyScope / HasBranchScope (dihapus total).

#### `app/Modules/Auth/Models/UserPreference.php`

```php
class UserPreference extends Model
{
    protected $fillable = [
        'user_id', 'theme', 'language', 'date_format', 'time_format',
        'timezone', 'notify_email', 'notify_in_app', 'notify_whatsapp',
        'default_dashboard', 'dashboard_layout', 'updated_by',
    ];

    protected $casts = [
        'notify_email' => 'boolean',
        'notify_in_app' => 'boolean',
        'notify_whatsapp' => 'boolean',
        'dashboard_layout' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### A6. Resources

#### `app/Modules/Auth/Resources/AuthResource.php`

```php
class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_number' => $this->employee_number,
            'phone' => $this->phone,
            'roles' => $this->roles->pluck('name'),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'is_active' => $this->is_active,
        ];
    }
}
```

### A7. Controller

#### `app/Modules/Auth/Controllers/Api/V1/AuthApiController.php`

**login():** Validasi email/password → cek user + password → buat Sanctum token → return `{ token, user: AuthResource }` → catat last_login_at/ip

**logout():** (auth:sanctum) Delete currentAccessToken → return `{ message: 'Logged out' }`

**user():** (auth:sanctum) Return `AuthResource` dengan load roles & permissions

### A8. Routes Module

#### `app/Modules/Auth/Routes/api.php`

```php
Route::post('/login', [AuthApiController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthApiController::class, 'logout']);
    Route::get('/user', [AuthApiController::class, 'user']);
});
```

### A9. Seeders

#### `database/seeders/RolePermissionSeeder.php`

**6 role baru:**
- `superadmin` — semua permission
- `hrmanager` — Admin dashboard full
- `adm_manager` — Supervisor dashboard full
- `hrbranch` — Admin view-only
- `hr_ast` — Admin view + input
- `admin` — Supervisor view-only

**Permissions** (sesuai MIGRATION_GUIDE.md section 2.3):
- Organization: view/create/edit/delete companies/branches/departments/positions/salary_grades
- Employee: view/create/edit/delete/import/export/terminate employees
- Attendance: view/import/edit attendances, manage/approve overtime
- Payroll: view/generate/lock/close/export payroll, print payslip
- Leave: view/manage/approve leave, manage leave types/settings
- Approval: approve/reject requests
- Supervisor: view/manage/export supervisor data

**Permission → Role mapping:** (sesuai matrix di MIGRATION_GUIDE.md section 2.4)

#### `database/seeders/UserSeeder.php`

**3 default users (sementara, organization belum ada):**
| Name | Email | Role | Password |
|---|---|---|---|
| Super Admin | superadmin@uranop.com | superadmin | PassTersulit2026 |
| HR Manager | hr@uranop.com | hrmanager | PassTersulit2026 |
| Admin Manager | adm@uranop.com | adm_manager | PassTersulit2026 |

> Tidak perlu CompanySeeder dulu karena company_id sudah dihapus dari users.

### A10. Hapus Route API Lama

Hapus route closure di `routes/api.php` — diganti auto-load dari module Auth.

---

## B. FRONTEND

### B1. Landing Page (`/` — public, guest)

**File baru:** `resources/js/Pages/Index.vue`

Tiru persis dari `hris-system/resources/js/Pages/Index.vue` dengan penyesuaian:
- Ganti `Link` Inertia → `router-link` Vue Router
- Ganti `usePage()` → data statis / env config (karena tidak ada shared props Inertia)
- Gunakan `GuestLayout` yang sudah ada sebagai wrapper
- Konten: Hero section, logo, "Masuk ke Dashboard" button, footer, privacy/terms modal
- Background dark `bg-slate-950`, gradient text blue→emerald

### B2. Perbaiki Login Page

**File:** `resources/js/Pages/Auth/Login.vue`

Tiru persis dari `hris-system/resources/js/Pages/Auth/Login.vue` dengan penyesuaian:

**Yang dipertahankan:**
- Logo + app name di header
- Card login dengan email, password, remember me
- **Show/hide password** (ikon `bx bx-show` / `bx bx-hide` yang toggle type input)
- Loading state saat submit (spinner + "Authenticating...")
- Error handling (field errors + general error banner)
- Security notice di bawah

**Yang dihapus:**
- Import Inertia (`@inertiajs/vue3`) → ganti dengan composable `useAuth()`
- Session company_id / branch_id handling
- BranchSwitcher / CompanySwitcher (tidak ada di instance1)

**Yang diubah:**
- `router.post('/login', ...)` → `authStore.login(form)` (panggil API via useApi)
- `defineOptions({ layout: AuthLayouts })` → gunakan `GuestLayout` yang sudah ada
- Submit call: POST `/api/login` via `useApi.js`, simpan token + user ke auth store
- Redirect setelah login: jika user punya `canAccessAdmin` → `/`, jika `canAccessSupervisor` → `/supervisor`

### B3. Perbaiki Layouts

#### B3a. `GuestLayout.vue` — perbaiki

Tiru dari `hris-system/resources/js/Layouts/AuthLayouts.vue`:
- `min-h-screen bg-[--bg-main] text-[--text-main]`
- Check theme di `onMounted`

#### B3b. Buat `Components/Dropdown.vue`

Tiru persis dari `hris-system/resources/js/Components/Dropdown.vue`:
- Props: `position` (left/right), `width`, `closeOnClickOutside`
- Slots: `trigger`, `content`
- Click outside + Escape key handler
- Transition scale-95
- `defineExpose({ open, close, toggle, isOpen })`

#### B3c. Buat `Layouts/Partial/Sidebar.vue`

Tiru struktur dari `hris-system/resources/js/Layouts/Partial/Sidebar.vue`:
- Fixed left sidebar dengan nav menu
- Logo + app name
- Menu items (role-based visibility) dengan icon + label + submenu
- Collapse/expand toggle
- Tooltip saat collapsed
- Active route highlighting
- Ganti `Link` Inertia → `router-link` Vue Router

**Menu yang digunakan (dari MIGRATION_GUIDE.md section 4.3):**

Admin menu (superadmin, hrmanager, hrbranch, hr_ast):
```
Dashboard → /
Organisasi
  └── Departemen → /organization/departments
  └── Jabatan → /organization/positions
  └── Grade Gaji → /organization/salary-grades
Karyawan → /employees
Kehadiran
  └── Absensi → /attendance
  └── Import Log → /attendance/import
  └── Roster → /attendance/roster
  └── Lembur → /attendance/overtime
Cuti
  └── Daftar Cuti → /leave
  └── Approval → /leave/approvals
  └── Pengaturan → /leave/settings
Generate Gaji → /payroll
THR → /payroll/thr
Generate Jadwal → /schedule/work-patterns
Laporan → /reports
```

Supervisor menu (adm_manager, admin, superadmin):
```
Dashboard → /supervisor
Kehadiran → /supervisor/attendance
Roster → /supervisor/attendance/roster
Generate Gaji → /supervisor/payroll
THR → /supervisor/payroll/thr
Cuti → /supervisor/leave
Karyawan → /supervisor/employee
Laporan → /supervisor/reports
```

> Superadmin melihat DUA sidebar (switch via dropdown/tab di header)

#### B3d. Buat `Layouts/Partial/Topbar.vue`

Tiru dari `hris-system/resources/js/Layouts/Partial/Topbar.vue`:
- Title halaman
- Theme toggle (moon/sun icon)
- Notifications dropdown (bell icon)
- User menu dropdown (avatar initial + name + roles + logout)
- Mobile menu toggle button
- Ganti `Link` Inertia → `router-link` / `router.push()`

**Yang dihapus:** Branch info (karena tidak ada multi-tenant)

#### B3e. Buat `Layouts/Partial/Footer.vue`

Tiru dari `hris-system/resources/js/Layouts/Partial/Footer.vue`:
- Copyright tahun
- Company name
- Email contact

#### B3f. Perbaiki `AuthenticatedLayout.vue`

Refactor total — tiru dari `hris-system/resources/js/Layouts/DashLayouts.vue`:
- Import Sidebar, Topbar, Footer
- Props: `title` (default "Dashboard")
- Sidebar collapsed state
- Dark mode state  
- Theme toggle handler
- `ml-20` / `ml-55` transition
- `min-h-[calc(100vh-101px)]` untuk main content

> Yang ada sekarang sudah cukup mirip tapi perlu diselaraskan dengan Partial components baru.

### B4. Hubungkan Auth Store ke API Nyata

**File:** `resources/js/Stores/auth.js`

- `login()` → panggil `POST /api/login` via `useApi`
- `fetchUser()` → panggil `GET /api/user` via `useApi`
- `logout()` → panggil `POST /api/logout` via `useApi`
- Handle error (hapus token jika 401)

### B5. Route Guard & Permission Check

**File:** `resources/js/router/index.js`

- Tambahkan route `/` untuk Landing Page dengan meta `{ guest: true }`
- `beforeEach`: jika guest route dan sudah login, redirect ke dashboard sesuai role
- `beforeEach`: jika guest dan belum login, biarkan (tampilkan landing/login)
- Role check sudah ada, tapi perlu disesuaikan dengan role names baru:
  - `canAccessAdmin`: superadmin, hrmanager, hrbranch, hr_ast
  - `canAccessSupervisor`: superadmin, adm_manager, admin

---

## C. URUTAN PENGERJAAN

| # | Step | Estimasi |
|---|---|---|
| 1 | Install Spatie + publish config | 2 menit |
| 2 | Buat struktur folder `app/Modules/Auth/` + sub-folder | 5 menit |
| 3 | Auto-load routes di `bootstrap/app.php` | 3 menit |
| 4 | Buat migration: users, user_preferences, user_branches | 15 menit |
| 5 | Install Spatie permission tables migration | 1 menit |
| 6 | Buat model: User, UserPreference | 15 menit |
| 7 | Buat AuthResource | 5 menit |
| 8 | Buat AuthApiController + Routes/api.php | 20 menit |
| 9 | Hapus route API lama di routes/api.php | 2 menit |
| 10 | Buat RolePermissionSeeder | 30 menit |
| 11 | Buat UserSeeder | 10 menit |
| 12 | Run migration + seeder | 2 menit |
| 13 | Frontend: Landing Page (Index.vue) | 20 menit |
| 14 | Frontend: Perbaiki Login.vue | 15 menit |
| 15 | Frontend: Buat Dropdown.vue | 10 menit |
| 16 | Frontend: Buat Partial/Sidebar.vue | 40 menit |
| 17 | Frontend: Buat Partial/Topbar.vue | 25 menit |
| 18 | Frontend: Buat Partial/Footer.vue | 5 menit |
| 19 | Frontend: Refactor AuthenticatedLayout.vue | 15 menit |
| 20 | Frontend: Perbaiki GuestLayout.vue | 5 menit |
| 21 | Frontend: Hubungkan auth store ke API | 10 menit |
| 22 | Frontend: Update router guard & permission check | 10 menit |
| 23 | Test login flow end-to-end | 10 menit |

**Total estimasi:** ~4-5 jam

---

## D. CATATAN PENTING

1. **Tidak ada company_id / branch_id di tabel users** — isolasi penuh, 1 DB per instance
2. **Tidak ada global scope** — tidak ada trait HasCompanyScope atau HasBranchScope
3. **Tidak ada session company/branch** — tidak ada middleware BranchContext
4. **Tidak ada Inertia** — semua pure REST API + Vue SPA
5. **Role names pakai yang baru:** superadmin, hrmanager, adm_manager, hrbranch, hr_ast, admin (BUKAN yang lama: super_admin, hr_area, hr_branch, staff)
6. **Password default:** `PassTersulit2026`
7. **Tabel `user_branches`** referensi ke tabel `branches` yang belum ada di migration Organization P1. Jangan jalankan migration ini dulu atau pastikan FK tidak menyebabkan error.
8. **Model User ditaruh di `app/Modules/Auth/Models/`** — bukan di `app/Models/`
9. **Route API lama (`routes/api.php`)** akan dihapus/dikosongkan, diganti auto-load dari module
10. **Frontend menggunakan Boxicons** (`bx bx-*` classes) — pastikan boxicons sudah terinstall
11. **Frontend menggunakan Tailwind CSS v4** dengan CSS variables (`--primary`, `--bg-main`, dll)
