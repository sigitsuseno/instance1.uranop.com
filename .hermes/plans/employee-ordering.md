# Employee Ordering — Drag-to-Reorder `no_urut`

> **Dibuat:** 2026-06-25
> **Tujuan:** Halaman drag-to-reorder untuk mengisi kolom `no_urut` di tabel `employees`
> **Library:** vuedraggable (next) — Vue 3 compatible sortable

---

## Overview

Sigit baru menambahkan kolom `no_urut` (integer, nullable) ke tabel `employees` via migration `2026_06_25_092832_add_no_urut_to_employees_table.php`. Semua nilai masih `null`. Butuh halaman khusus untuk mengisi nomor urut dengan cara drag-to-reorder.

## Arsitektur

```
┌─────────────────────────────────────────────┐
│  Sidebar: Data Karyawan                     │
│    ├── Karyawan                             │
│    ├── Import Karyawan                      │
│    ├── Grouping Karyawan                    │
│    ├── Urutan Karyawan  ← NEW               │
│    ├── Gaji Karyawan                        │
│    └── ...                                  │
└─────────────────────────────────────────────┘

Halaman: /admin/employees/ordering
  - Tabel/list karyawan yang bisa di-drag naik/turun
  - Tombol "Auto-Number" (isi nomor urut 1,2,3.. otomatis)
  - Tombol "Simpan Urutan"
  - Filter: bisa pilih group (GRP-JKT, GRP-ALLIN, dll) biar gak semua tampil

Backend API:
  GET  /api/v1/employees/ordering          → list karyawan (dengan no_urut)
  POST /api/v1/employees/ordering/reorder  → simpan urutan baru (bulk update)
  POST /api/v1/employees/ordering/auto-number → auto-assign no_urut
```

## Phase 1: Backend API

### 1a. Route
File: `app/Modules/Employee/Routes/api.php`

```php
Route::prefix('v1/employees/ordering')->group(function () {
    Route::get('/', [EmployeeOrderingApiController::class, 'index']);
    Route::post('/reorder', [EmployeeOrderingApiController::class, 'reorder']);
    Route::post('/auto-number', [EmployeeOrderingApiController::class, 'autoNumber']);
});
```

### 1b. Controller
File: `app/Modules/Employee/Controllers/Api/V1/Ordering/EmployeeOrderingApiController.php`

- **`index()`**: Ambil semua employee (`id, name, employee_code, no_urut, employment_status`), order by `no_urut ASC, name ASC`. Juga kirim daftar group_master (buat filter di frontend).

- **`reorder()`**: Terima `{ ordered_ids: [254, 152, ...] }` — array employee_id yang sudah diurutkan. Loop dan set `no_urut` = index + 1.

- **`autoNumber()`**: Ambil employee order by `name ASC`, set `no_urut` = row_number. Return count.

### 1c. Ubah Grouping Controller (Side Effect)
File: `app/Modules/Employee/Controllers/Api/V1/Grouping/EmployeeGroupingApiController.php`

- Tambah `'no_urut'` ke `select()`
- Ganti `->orderBy('name')` → `->orderBy('no_urut')->orderBy('name')`

## Phase 2: Frontend Page

### 2a. Install vuedraggable
```bash
npm install vuedraggable@next
```

### 2b. Halaman Utama
File: `resources/js/Pages/Admin/Employees/Ordering/Index.vue`

Komponen:
- Header: judul "Urutan Karyawan" + tombol "Auto-Number" + "Simpan"
- Filter dropdown: pilih group (opsional, filter by group reference_code)
- List draggable: tampilkan karyawan sebagai card/row dengan:
  - Avatar/initial
  - Nama
  - NIP/employee_code
  - Nomor urut saat ini
  - Handle drag (icon grip)

- Drag-to-reorder pakai `vuedraggable`
- Setelah drag → tandai "ada perubahan" → muncul tombol Simpan
- Tombol Simpan → POST `/api/v1/employees/ordering/reorder`
- Tombol Auto-Number → POST `/api/v1/employees/ordering/auto-number` → reload

### 2c. Router
File: `resources/js/router/index.js`

```js
// Import (lazy load)
const EmployeeOrderingIndex = () => import('../Pages/Admin/Employees/Ordering/Index.vue')

// Route (di children /admin)
{ path: 'employees/ordering', name: 'employees.ordering', component: EmployeeOrderingIndex, meta: { title: 'Urutan Karyawan' } }
```

### 2d. Sidebar
File: `resources/js/Layouts/Admin/Sidebar.vue`

Tambah di bawah "Grouping Karyawan":
```js
{ title: 'Urutan Karyawan', icon: 'bx bx-sort-alt-2', route: '/admin/employees/ordering', visible: !isManajemen.value },
```

## Phase 3: Integrasi

Setelah `no_urut` terisi, halaman Grouping Karyawan otomatis menampilkan karyawan terurut sesuai `no_urut` (karena Phase 1c sudah mengubah `orderBy`).

## File yang Terkena

| File | Action |
|------|--------|
| `package.json` | Tambah `vuedraggable` |
| `app/Modules/Employee/Routes/api.php` | Tambah 3 route |
| `app/Modules/Employee/Controllers/Api/V1/Ordering/EmployeeOrderingApiController.php` | **NEW** |
| `app/Modules/Employee/Controllers/Api/V1/Grouping/EmployeeGroupingApiController.php` | Edit select + orderBy |
| `resources/js/Pages/Admin/Employees/Ordering/Index.vue` | **NEW** |
| `resources/js/router/index.js` | Tambah route |
| `resources/js/Layouts/Admin/Sidebar.vue` | Tambah menu |

## Pitfalls

- `vuedraggable@next` harus versi `^4.1.0` — versi lama (`2.x`) buat Vue 2
- `no_urut` kolom nullable → pastikan `orderBy('no_urut')` tidak error kalau semua null (null values akan di-sort duluan di MySQL)
- Drag-drop di vuedraggable perlu `item-key` prop (pakai `employee.id`)
- Setelah auto-number atau reorder, harus refresh Grouping page untuk lihat perubahan
- Jangan lupa npm run build setelah install vuedraggable
