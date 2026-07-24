# Phase 1: Pondasi — Auth, Instance, Dashboard, Notification, AuditLog + Web UI (Inertia 3) + Desktop Scaffold (Tauri v2)

**File**: `rw_phase_1.md`
**Status**: Draft v3 — Web (Inertia 3) + Desktop (Tauri) included
**Target**: Login sampai dashboard berfungsi di **web + desktop**, pondasi siap untuk semua modul

---

## ⚠️ STACK & PRINSIP (self-contained — baca ini dulu!)

### Stack Teknologi

| Layer        | Teknologi                       | Auth                         | Keterangan                              |
| ------------ | ------------------------------- | ---------------------------- | --------------------------------------- |
| **Web UI**   | Inertia 3 (Laravel SSR + Vue 3) | Session cookie               | Server-side routing, Inertia middleware |
| **REST API** | Laravel `Api/V1/`               | Sanctum token (Bearer)       | Untuk desktop & mobile                  |
| **Sync API** | Laravel `Api/Sync/`             | Sanctum token                | Batch sync desktop → server             |
| **Desktop**  | Tauri v2 (Rust + WebView)       | Sanctum token (localStorage) | Offline-first, local SQLite             |
| **CSS**      | Tailwind CSS v4 + CSS variables | —                            | NO hardcode Tailwind colors             |

### Aturan WAJIB

1. **UI**: Sebelum buat UI, **WAJIB** baca `.hermes/uistyle.md`
    - Tema: Clean Elegant Enterprise
    - CSS variables ONLY (`bg-(--bg-main)`, `text-(--text-main)`, dll)
    - DILARANG hardcode Tailwind color (gray-800, blue-500, etc)
    - Spacing: `p-4` / `p-6` max. Element: `sm=h-8`, `md/lg=h-10`
    - No double padding parent-child. `rounded-md`.
    - Light/dark via localStorage
2. **Response format**: Semua API pakai `BaseApiController::success()` / `error()` / `paginated()`
3. **Service layer**: Business logic di Service, Controller cuma I/O
4. **Test**: TDD — test service dulu, baru controller
5. **Module autoload**: Routes auto-loaded dari `app/Modules/*/Routes/` via `bootstrap/app.php`

---

## Tujuan

1. Project baru bisa login, logout, cek user + permission
2. Multi-tenant scope siap (Instance)
3. Dashboard tampil statistik
4. Notification + AuditLog service siap pakai
5. Base classes & helpers siap — semua modul berikutnya tinggal pakai
6. **Web UI siap**: Login page + Dashboard page (Inertia 3 — SSR via Laravel, Vue components)
7. **Desktop scaffold**: Tauri v2 project siap, local SQLite, auth flow

---

## 1. STRUKTUR FOLDER & FILE

```
H:\laragon\www\hris-master\
├── app/
│   ├── Helpers/
│   │   ├── ResponseHelper.php
│   │   ├── DateHelper.php
│   │   ├── NumberHelper.php
│   │   └── ExcelHelper.php
│   │
│   ├── Traits/
│   │   ├── HasAudit.php
│   │   └── HasPeriod.php
│   │
│   ├── Enums/
│   │   ├── AttendanceStatus.php
│   │   ├── PayrollStatus.php
│   │   └── LeaveType.php
│   │
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── V1/
│   │               └── BaseApiController.php
│   │
│   └── Modules/
│       ├── Auth/
│       │   ├── Models/
│       │   │   ├── User.php
│       │   │   └── UserPreference.php
│       │   ├── Services/
│       │   │   └── AuthService.php
│       │   ├── Controllers/
│       │   │   ├── Web/                     # Inertia (session auth)
│       │   │   │   └── AuthController.php
│       │   │   ├── Api/
│       │   │   │   ├── V1/                  # General REST API
│       │   │   │   │   ├── AuthController.php
│       │   │   │   │   └── PermissionController.php
│       │   │   │   └── Sync/                # Desktop sync (batch)
│       │   │   │       └── AuthSyncController.php
│       │   │   └── Report/
│       │   └── Routes/
│       │       ├── web.php                  # Inertia routes
│       │       ├── api.php                  # General API routes
│       │       └── api-sync.php             # Sync routes
│       │
│       ├── Instance/
│       │   ├── Models/
│       │   │   └── Instance.php
│       │   └── Routes/
│       │       └── api.php
│       │
│       ├── Dashboard/
│       │   ├── Services/
│       │   │   └── DashboardService.php
│       │   ├── Controllers/
│       │   │   ├── Web/
│       │   │   │   └── DashboardController.php   # Inertia render
│       │   │   └── Api/V1/
│       │   │       └── DashboardController.php   # REST stats
│       │   └── Routes/
│       │       ├── web.php
│       │       └── api.php
│       │
│       ├── Notification/
│       │   ├── Models/
│       │   │   ├── Notification.php
│       │   │   └── PushSubscription.php
│       │   ├── Services/
│       │   │   └── NotificationService.php
│       │   └── Routes/
│       │       └── api.php
│       │
│       └── AuditLog/
│           ├── Models/
│           │   └── AuditLog.php
│           └── Routes/
│               └── api.php
│
├── database/
│   └── migrations/
│       ├── 0001_01_01_000000_create_instances_table.php
│       ├── 0001_01_01_000001_create_users_table.php
│       ├── 0001_01_01_000002_create_user_preferences_table.php
│       ├── 0001_01_01_000003_create_permission_tables.php        # Spatie
│       ├── 0001_01_01_000004_create_personal_access_tokens_table.php  # Sanctum
│       ├── 0001_01_01_000005_create_notifications_table.php
│       ├── 0001_01_01_000006_create_push_subscriptions_table.php
│       ├── 0001_01_01_000007_create_audit_logs_table.php
│       └── 0001_01_01_000008_create_jobs_tables.php              # queue
│
├── routes/
│   └── api.php
│
├── bootstrap/
│   └── app.php                                                  # module route auto-load
│
├── tests/
│   ├── Unit/
│   │   ├── Helpers/
│   │   │   └── ResponseHelperTest.php
│   │   └── Modules/
│   │       └── Auth/
│   │           └── AuthServiceTest.php
│   └── Feature/
│       └── Modules/
│           └── Auth/
│               ├── AuthControllerTest.php
│               └── PermissionControllerTest.php
│
├── composer.json
├── phpunit.xml
└── .env.example
```

---

## 2. DATABASE — TABEL & KOLOM

### 2.1 `instances` — Deployment metadata (1 row per deployment)

| Kolom         | Type                  | Keterangan                                  |
| ------------- | --------------------- | ------------------------------------------- |
| id            | bigint (PK)           |                                             |
| code          | varchar(20) UNIQUE    | Kode unik (contoh: "KUS-MJL")               |
| name          | varchar(100)          | Nama instansi/perusahaan                    |
| slug          | varchar(100) UNIQUE   | URL-friendly name                           |
| domain        | varchar(100) nullable | Domain (contoh: "kusmajalengka.uranop.com") |
| database_name | varchar(100) nullable | Nama database terpisah                      |
| logo          | varchar(255) nullable | Path logo                                   |
| is_active     | tinyint(1) default 1  |                                             |
| settings      | json nullable         | Konfigurasi instance-specific               |
| created_at    | timestamp             |                                             |
| updated_at    | timestamp             |                                             |

### 2.2 `users`

| Kolom         | Type                  | Keterangan |
| ------------- | --------------------- | ---------- |
| id            | bigint (PK)           |            |
| name          | varchar(100)          |            |
| email         | varchar(100) UNIQUE   |            |
| password      | varchar(255)          | Bcrypt     |
| avatar        | varchar(255) nullable |            |
| is_active     | tinyint(1) default 1  |            |
| last_login_at | timestamp nullable    |            |
| last_login_ip | varchar(45) nullable  |            |
| created_at    | timestamp             |            |
| updated_at    | timestamp             |            |

### 2.3 `user_preferences`

| Kolom      | Type              | Keterangan |
| ---------- | ----------------- | ---------- |
| id         | bigint (PK)       |            |
| user_id    | bigint (FK→users) |            |
| key        | varchar(50)       |            |
| value      | text              |            |
| created_at | timestamp         |            |
| updated_at | timestamp         |            |

Unique: `(user_id, key)`

### 2.4 `permission_tables` (Spatie)

Tabel standar Spatie Permission:

- `permissions` — id, name, guard_name, created_at, updated_at
- `roles` — id, name, guard_name, created_at, updated_at
- `model_has_roles` — role_id, model_type, model_id
- `model_has_permissions` — permission_id, model_type, model_id
- `role_has_permissions` — permission_id, role_id

### 2.5 `personal_access_tokens` (Sanctum)

Tabel standar Sanctum:

- `id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`

### 2.6 `notifications`

| Kolom      | Type                       | Keterangan                         |
| ---------- | -------------------------- | ---------------------------------- |
| id         | bigint (PK)                |                                    |
| user_id    | bigint (FK→users) nullable | null = broadcast semua             |
| type       | varchar(50)                | leave_approved, payroll_ready, etc |
| title      | varchar(200)               |                                    |
| body       | text                       |                                    |
| data       | json nullable              | Payload (leave_id, payroll_id)     |
| is_read    | tinyint(1) default 0       |                                    |
| read_at    | timestamp nullable         |                                    |
| created_at | timestamp                  |                                    |

### 2.7 `push_subscriptions`

| Kolom            | Type              | Keterangan        |
| ---------------- | ----------------- | ----------------- |
| id               | bigint (PK)       |                   |
| user_id          | bigint (FK→users) |                   |
| endpoint         | varchar(500)      | Web push endpoint |
| public_key       | varchar(255)      |                   |
| auth_token       | varchar(255)      |                   |
| content_encoding | varchar(20)       |                   |
| created_at       | timestamp         |                   |
| updated_at       | timestamp         |                   |

### 2.8 `audit_logs`

| Kolom      | Type                       | Keterangan                               |
| ---------- | -------------------------- | ---------------------------------------- |
| id         | bigint (PK)                |                                          |
| user_id    | bigint (FK→users) nullable |                                          |
| context    | varchar(20) default 'main' | main / shadow / cron                     |
| module     | varchar(50)                | Auth, Employee, Attendance, dll          |
| action     | varchar(50)                | created, updated, deleted, login, logout |
| model_type | varchar(100)               | App\Modules\Employee\Models\Employee     |
| model_id   | bigint nullable            |                                          |
| old_values | json nullable              | Data sebelum diubah                      |
| new_values | json nullable              | Data setelah diubah                      |
| ip_address | varchar(45) nullable       |                                          |
| user_agent | varchar(500) nullable      |                                          |
| created_at | timestamp                  |                                          |

---

## 3. YANG HARUS DIPERBAIKI

### 3.1 Model Scope Issue — Multi-instance, bukan multi-tenant

**Arsitektur**: Aplikasi ini **multi-instance** (1 deployment = 1 database = 1 perusahaan), bukan multi-tenant (1 database berisi banyak perusahaan). Tidak ada scoping `instance_id` di query — semua data dalam satu deployment otomatis milik instance tersebut.

### 3.2 Test Suite Broken

**Masalah**: `collision` v8 incompat dengan `phpunit` v12 → `php artisan test` crash.

**Fix**:

```bash
composer require nunomaduro/collision:^8.7 --dev
# Atau downgrade phpunit ke v11.5
composer require phpunit/phpunit:^11.5 --dev
```

### 3.3 Route Broken

**Masalah**: `SupervisorWorkScheduleController` tidak ada → `route:list` crash.

**Fix**: Karena ini project baru, route ini tidak perlu dibuat. Skip.

### 3.4 Response Format Tidak Konsisten

**Perbaikan**: Semua controller wajib pakai `BaseApiController::success()` / `BaseApiController::error()`.

---

## 4. TASK LIST

### Task 1: Scaffold Project ✅

- [x] `composer create-project laravel/laravel:^13 api`
- [x] Setup `.env` (database connection ke existing)
- [x] `php artisan key:generate`
- [x] Install Sanctum: `composer require laravel/sanctum`
- [x] Install Spatie Permission: `composer require spatie/laravel-permission`
- [x] Install DomPDF: `composer require barryvdh/laravel-dompdf`
- [x] Install Excel: `composer require maatwebsite/excel`
- [x] Install WebPush: `composer require laravel-notification-channels/webpush`
- [x] Setup CORS config untuk akses dari desktop/mobile app
- [x] Fix collision + phpunit → `php artisan test` jalan (17/17 PASSING)

### Task 2: Base Classes & Helpers ✅

- [x] `BaseApiController.php` — `success()`, `error()`, `paginated()`
- [x] `ResponseHelper.php` — static helpers
- [x] `DateHelper.php` — `periodeRange()`, `formatId()`, `parseDate()`
- [x] `NumberHelper.php` — `cleanNum()`, `formatRupiah()`, `overtime()`
- [x] `ExcelHelper.php` — stub untuk pattern export/import

### Task 3: Traits & Enums ✅

- [x] `HasAudit.php` — auto-log create/update/delete via model events
- [x] `HasPeriod.php` — `scopeByPeriod()`, `scopeCurrentPeriod()`
- [x] `AttendanceStatus.php` — PRESENT, ABSENT, LEAVE, SICK, etc
- [x] `PayrollStatus.php` — DRAFT, GENERATED, REVIEWED, LOCKED, CLOSED
- [x] `LeaveType.php` — ANNUAL, SICK, MATERNITY, etc

### Task 4: Migration ✅

- [x] `instances` table
- [x] `users` table
- [x] `user_preferences` table
- [x] Spatie permission tables
- [x] Sanctum tokens table
- [x] `notifications` table
- [x] `push_subscriptions` table
- [x] `audit_logs` table
- [x] Jobs tables (queue, driver: database)
- [x] `php artisan migrate`

### Task 5: Models ✅

- [x] `Instance.php` — `$fillable`, `$casts`, relasi
- [x] `User.php` — `$fillable`, `$hidden`, `$casts`, `HasRoles`, `HasApiTokens`, `HasPushSubscriptions`, `HasAudit`, guard_name=`api`, relasi `preferences()`, `notifications()`
- [x] `UserPreference.php`
- [x] `Notification.php`
- [x] `PushSubscription.php`
- [x] `AuditLog.php`

### Task 6: Services ✅

- [x] `AuthService.php`
    - `login(email, password)` → token + user
    - `logout(user)` → revoke token
    - `me(user)` → user + roles + permissions
- [x] `DashboardService.php`
    - `getStats()` → total_users, active_users, total_roles, total_permissions, unread_notifications
- [x] `NotificationService.php`
    - `send(user, title, body, type, data)` → create + web push
    - `markAsRead(notificationId)`
    - `getUnread(userId)`

### Task 7: Controllers ✅

- [x] `AuthController.php` (API)
    - `POST /api/v1/auth/login`
    - `POST /api/v1/auth/logout`
    - `GET /api/v1/auth/me`
- [x] `PermissionController.php` (API)
    - `GET /api/v1/auth/permissions`
    - `GET /api/v1/auth/roles`
- [x] `DashboardController.php` (API)
    - `GET /api/v1/dashboard/stats`
- [x] `AuthenticatedSessionController.php` (Web — session login/logout)

### Task 8: Routes ✅

- [x] `routes/api.php` — auth routes + dashboard route
- [x] Module auto-load di `bootstrap/app.php`
- [x] Rate limiting: `RateLimiter::for('api')` → 60 request/menit
- [x] Exception handler: custom response untuk `ModelNotFoundException`, `AuthenticationException`, `ValidationException`

### Task 9: Seeder ✅

- [x] `InstanceSeeder` — 1 instance default (name: "Default Instance", code: "MAIN")
- [x] `UserSeeder` — superadmin@uranop.com / PassTersulit2026
- [x] `RoleSeeder` — superadmin, admin, supervisor, karyawan
- [x] `PermissionSeeder` — daftar permission per modul

### Task 10: Test ✅

- [x] `ResponseHelperTest.php` — format success, error, paginated
- [x] `AuthServiceTest.php` — login success, login fail, logout, token expire
- [x] `AuthControllerTest.php` — endpoint response format, status code
- [x] `PermissionControllerTest.php` — permission list

### Task 11: CI Setup ✅

- [x] `.github/workflows/test.yml` — lint + test
- [x] `php artisan test` harus passing (17/17)

### Task 12: Web UI — Inertia 3 ✅

- [x] Install Inertia 3: `composer require inertiajs/inertia-laravel` + `npm install @inertiajs/vue3`
- [x] Install Vue 3 + Pinia + Vite Vue plugin
- [x] Setup `app.css` — CSS variables dari uistyle.md (light + dark)
- [x] Setup `app.js` — `createInertiaApp()` dengan Vue 3 resolver
- [x] Setup `HandleInertiaRequests` middleware → share `auth.user`, `permissions`, `flash`
- [x] Setup `bootstrap/app.php` — register Inertia middleware
- [x] Setup `vite.config.js` — Vue plugin + `@` alias
- [x] Bikin `app.blade.php` — root HTML + `@inertia` + `@inertiaHead`
- [x] Bikin `Index.vue` — Landing page (Uranop Enterprise branding)
- [x] Bikin `Login.vue` — form POST ke `/login`, error via `$page.props.errors`
- [x] Bikin `AuthLayout.vue` — centered card, slot content
- [x] Bikin `AppLayout.vue` — sidebar + header + `<slot />`
- [x] Bikin `AppSidebar.vue` — menu items (Dashboard)
- [x] Bikin `AppHeader.vue` — user name, theme toggle, logout
- [x] Bikin `StatCard.vue` — card statistik reusable
- [x] Bikin `Dashboard.vue` — 5 stat cards + welcome message
- [x] Bikin `useApi.js` — axios instance
- [x] Bikin `authStore.js` — Pinia: user, roles, permissions
- [x] Bikin `uiStore.js` — Pinia: sidebar, theme
- [x] Setup Inertia routes di `routes/web.php` — `/`, `/login`, `/dashboard`, `/logout`
- [x] Copy fonts: Roboto Condensed + Open Sans
- [x] `npm run build` — production build ✅
- [x] `php artisan db:seed` — user siap login

### Task 13: Desktop — Tauri v2 Scaffold ⏳

- [ ] Install Tauri CLI: `npm install @tauri-apps/cli@latest`
- [ ] Init Tauri project di `H:\desktopapp` (existing scaffold, perlu disesuaikan)
- [ ] Setup `tauri.conf.json` → dev URL ke Vite, build ke `dist/`
- [ ] Bikin local SQLite schema: `users`, `sync_queue`
- [ ] Bikin Tauri command: `login(email, password)` → call API, simpan token
- [ ] Bikin Tauri command: `logout()` → hapus token
- [ ] Bikin `desktop/index.html` + `desktop/main.js` — entry khusus desktop
- [ ] Auth flow offline: cek local token dulu, fallback ke login
- [ ] Verify: `npx tauri dev` → login page muncul di window native

> **Catatan**: Desktop Tauri project ada di `H:\desktopapp` (scaffold lama, perlu disesuaikan).

---

## 5. ENDPOINT SUMMARY

```
POST   /api/v1/auth/login              # email + password → token + user
POST   /api/v1/auth/logout             # revoke token
GET    /api/v1/auth/me                 # current user + roles + permissions
GET    /api/v1/auth/permissions        # list semua permission
GET    /api/v1/auth/roles              # list semua role
GET    /api/v1/dashboard/stats         # statistik dashboard
```

---

## 6. RESPONSE FORMAT (Wajib)

```json
// Success
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Superadmin", "email": "superadmin@uranop.com" },
    "token": "1|abc123..."
  },
  "message": "Login berhasil"
}

// Error
{
  "success": false,
  "message": "Email atau password salah",
  "errors": null
}

// Validation Error
{
  "success": false,
  "message": "Validasi gagal",
  "errors": {
    "email": ["Email wajib diisi"],
    "password": ["Password minimal 8 karakter"]
  }
}

// Unauthenticated
{
  "success": false,
  "message": "Unauthenticated"
}

// Paginated
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 25,
    "total": 112
  }
}
```

---

## 7. FRONTEND — Inertia 3 (Web UI)

### 7.1 Arsitektur

Inertia 3 menghubungkan Laravel backend dengan Vue 3 frontend via SSR:

- Laravel controller render Inertia page → Vue component di-mount di client
- Session-based auth (cookie), tidak perlu token di localStorage
- Routing ditangani Laravel (server-side), tidak pakai Vue Router
- Shared data via `HandleInertiaRequests` middleware

### 7.2 Struktur `resources/js/`

```
resources/js/
├── Pages/                        # Inertia page components (1:1 dgn Laravel route)
│   ├── Auth/
│   │   └── Login.vue            # Form email + password → POST /login
│   └── Dashboard/
│       └── Dashboard.vue         # Stat cards panggil API internal
├── Components/                   # Shared Vue components (Inertia + Tauri)
│   ├── AppSidebar.vue            # Sidebar navigasi (menu dari permissions)
│   ├── AppHeader.vue             # Top bar: user info, logout
│   └── StatCard.vue              # Card statistik reusable
├── Layouts/
│   ├── AuthLayout.vue            # Clean layout untuk login (centered card)
│   └── AppLayout.vue             # Sidebar + Header + <slot />
├── Composables/
│   ├── useApi.js                 # axios wrapper (pakai cookie session, no token)
│   └── useAuth.js                # login(), logout(), user() via Inertia
├── Stores/
│   ├── authStore.js              # Pinia: user, roles, permissions (dari shared props)
│   └── uiStore.js                # sidebar, theme, loading
├── app.js                        # createInertiaApp({ resolve, setup })
└── App.vue                       # Root: inertia-head + slot layout
```

> **Note**: Tidak ada `router/` folder — routing server-side via Laravel. Tidak ada `Vue Router`.
> **Note**: Shared Inertia data (`auth.user`, `permissions`) auto-inject via `HandleInertiaRequests`.

### 7.3 Tasks (Frontend) ✅

- [x] Install Inertia 3: `composer require inertiajs/inertia-laravel` + `npm install @inertiajs/vue3`
- [x] Setup `app.js` — `createInertiaApp()` dengan Vue 3 resolver
- [x] Setup `HandleInertiaRequests` middleware → share `auth.user`, `permissions`, `flash`
- [x] Bikin Login.vue — form POST ke `/login` (Laravel route), error via `$page.props.errors`
- [x] Bikin AuthLayout.vue — centered card, slot content
- [x] Bikin AppLayout.vue — sidebar + header + `<slot />`
- [x] Bikin AppSidebar.vue — menu items (Home, nanti bertambah per phase)
- [x] Bikin AppHeader.vue — user name dari `$page.props.auth.user`, logout POST
- [x] Bikin Dashboard.vue — 5 stat cards, panggil API internal
- [x] Setup Inertia routes di `routes/web.php` — `Route::inertia('/dashboard', 'Dashboard/Dashboard')`
- [x] `npm run build` — production build

---

## 8. DESKTOP — Tauri v2 Scaffold

### 8.1 Struktur `src-tauri/`

```
src-tauri/
├── Cargo.toml
├── tauri.conf.json
├── capabilities/
│   └── default.json
├── icons/
└── src/
    ├── main.rs              # Tauri entry point
    ├── lib.rs               # Tauri commands (auth, sync)
    └── db.rs                # Local SQLite init + migrations
```

### 8.2 Tasks (Desktop)

- [ ] Install Tauri CLI: `npm install @tauri-apps/cli@latest`
- [ ] Init Tauri project: `npx tauri init` (app name: HRIS Master)
- [ ] Setup `tauri.conf.json` → dev URL ke Vite, build ke `dist/`
- [ ] Bikin local SQLite schema: `users`, `sync_queue`
- [ ] Bikin Tauri command: `login(email, password)` → call API, simpan token
- [ ] Bikin Tauri command: `logout()` → hapus token
- [ ] Bikin `desktop/index.html` + `desktop/main.js` — entry khusus desktop
- [ ] Auth flow offline: cek local token dulu, fallback ke login
- [ ] Verify: `npx tauri dev` → login page muncul di window native

---

## 9. ENDPOINT SUMMARY (Updated)

```
POST   /api/v1/auth/login              # email + password → token + user
POST   /api/v1/auth/logout             # revoke token
GET    /api/v1/auth/me                 # current user + roles + permissions
GET    /api/v1/auth/permissions        # list semua permission
GET    /api/v1/auth/roles              # list semua role
GET    /api/v1/dashboard/stats         # statistik dashboard

# Web UI (Inertia 3 — server-side routes, no Vue Router)
GET    /                               # Inertia::render('Index') — Landing page (guest)
GET    /login                          # Inertia::render('Auth/Login') — Login form (guest)
POST   /login                          # Session login → redirect /dashboard
GET    /dashboard                      # Inertia::render('Dashboard/Dashboard') (auth)
POST   /logout                         # Session logout → redirect /login (auth)
```
