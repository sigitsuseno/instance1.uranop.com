# Dashboard Enhancement — 2026-06-17

## Perubahan

### Backend — Dashboard API Controller (BARU)
- `app/Modules/Dashboard/Controllers/Api/V1/DashboardApiController.php`
- Single endpoint `GET /api/v1/dashboard` return semua data dashboard:
  - **stats**: totalKaryawan (is_active=1), hadirHariIni (att_prepares check_in not null today), menungguCuti (leave_requests status=pending), totalPayroll (sum gaji_kotor pay_records bulan aktif)
  - **pendingLeaves**: 10 cuti terbaru status pending + employee name, department, leave type, tanggal
  - **contractsExpiring**: kontrak active+latest expiring ≤30 hari + days_left dari accessor
  - **recentAuditLogs**: 15 audit log terbaru + user, action, module
  - **birthdays**: karyawan aktif yang ultah bulan ini, diurutin per tanggal

### Route (BARU)
- `app/Modules/Dashboard/Routes/api.php` — auto-discover via `glob(app_path('Modules/*/Routes/api.php'))`
- Prefix: `v1/dashboard`, middleware: `auth:sanctum`

### Frontend — Admin Dashboard.vue (REWRITE)
- `resources/js/Pages/Admin/Dashboard.vue`
- Fetch real data dari `/api/v1/dashboard` via `useApi().get()`
- Stats cards: computed dari API response
- Pending Cuti table: DataTable dengan 7 kolom (nama, dept, jenis, mulai, selesai, hari, status)
- Kontrak Expiring: card samping dengan days_left badge (warning/danger)
- **NEW**: Audit Log table — 5 kolom (user, action, module, entity, waktu)
- **NEW**: Ultah Bulan Ini — card dengan daftar karyawan + tanggal ultah
- Handle loading state dan empty state di tiap section

## File yang Diubah
- `resources/js/Pages/Admin/Dashboard.vue` — rewrite

## File Baru
- `app/Modules/Dashboard/Controllers/Api/V1/DashboardApiController.php`
- `app/Modules/Dashboard/Routes/api.php`
