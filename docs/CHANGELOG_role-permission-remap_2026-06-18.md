# CHANGELOG — Role & Permission Remapping

**Tanggal:** 2026-06-18  
**Perubahan oleh:** Paijo  

## Ringkasan

Remapping akses role ke dashboard: hrbranch dipindahkan dari `/admin/` ke `/supervisor/`.

## Final Role Mapping

| Role | Dashboard | Switch |
|---|---|---|
| **superadmin** | `/admin/` + `/supervisor/` | Bisa switch via topbar |
| **hrmanager** | `/admin/` only | — |
| **hrbranch** | `/supervisor/` only | — |
| **adm_manager** | `/supervisor/` only | — |
| **hr_ast** | `/admin/` only | — |

## File yang Diubah

### 1. `resources/js/Stores/auth.js`
- `canAccessAdmin`: hapus `'hrbranch'` → `['superadmin', 'hrmanager', 'hr_ast']`
- `canAccessSupervisor`: tambah `'hrbranch'` → `['superadmin', 'adm_manager', 'hrbranch']`

### 2. `resources/js/Stores/permission.js`
- `hrbranch`: tambah 8 permission supervisor:
  - `import attendances`, `edit attendances`
  - `manage overtime`, `approve overtime`
  - `view payroll`
  - `manage leave`, `approve leave`
  - `export supervisor data`

### 3. `database/seeders/RolePermissionSeeder.php`
- `hrbranch`: hapus `view companies`, `view branches`
- `hrbranch`: tambah 8 permission supervisor (sama dengan frontend)

### 4. `resources/js/Layouts/Admin/Sidebar.vue`
- Hapus import `isHrbranch` yang tidak terpakai

## Catatan

- `isHrbranch` computed tetap tersedia di auth store untuk future use
- Router guard (`requiresAdmin` / `requiresSupervisor`) otomatis redirect role yang salah ke dashboard yang benar
- `superadmin` tetap bisa switch dashboard via tombol di topbar
- Backend route guard `middleware('role:superadmin')` untuk `/api/admins/*` dan `/api/roles/*` / `/api/permissions/*` tidak berubah
