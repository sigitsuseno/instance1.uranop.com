# Plan: Roster Autolog untuk Supervisor Attendance

**Route**: `/supervisor/attendance/roster`  
**Tanggal**: 2026-06-30  
**Status**: Draft

---

## Overview

Membangun halaman roster cross-table untuk tabel `attendance_autologs` di supervisor.
UI meniru admin SyncKehadiran — employees sebagai rows, dates sebagai columns, 
cells menampilkan check_in, check_out, lembur, lm. Edit modal untuk koreksi data.

Checkbox filter berdasarkan `employee_group_masters` dengan `group_label = "Imported Shift/Group"`.

---

## Phase 1: Backend — API Endpoints

### 1A. GET `/api/v1/supervisor/attendance/roster`
**Controller**: `AttendanceAutologController::roster()` (method baru)

**Parameters:**
- `start_date`, `end_date` — range periode
- `group_codes[]` — array kode group dari employee_group_masters (misal: `['GRP-JKT', 'GRP-ALLIN']`)

**Logic:**
1. Ambil semua employee yang tergabung dalam `group_codes` yang dipilih (via tabel `employee_groups`)
2. Ambil semua `attendance_autologs` untuk employee tersebut dalam range tanggal
3. Ambil roster/shift data dari `employee_shift_rosters` (shift_start, shift_end)
4. Generate daftar tanggal lengkap dalam range
5. Return struktur JSON:
```json
{
  "employees": [{ id, name, nip, department, position }],
  "dates": [{ date, day, dayName, isWeekend }],
  "autologData": {
    "<employee_id>": {
      "<date>": {
        "id": <autolog_id>,
        "check_in": "HH:mm",
        "check_out": "HH:mm",
        "lembur": 120,
        "lm": 60,
        "status": "present",
        "shift_start": "08:00",
        "shift_end": "16:00",
        "is_locked": false,
        "is_holiday": false,
        "is_sat": false,
        "is_sun": false
      }
    }
  },
  "groups": [{ code, name }] // available groups for checkboxes
}
```

### 1B. POST `/api/v1/supervisor/attendance/roster/update`
**Controller**: `AttendanceAutologController::updateRoster()` (method baru)

**Parameters:**
```json
{
  "id": <autolog_id>,
  "check_in": "08:30" | null,
  "check_out": "17:00" | null,
  "lembur": 120,
  "lm": 60
}
```

**Logic:**
1. Validasi autolog exists
2. Cek is_locked → reject if locked
3. Update fields: check_in, check_out, lembur, lm
4. Set is_manual_edit = true, last_edited_at = now, last_edited_by = auth user
5. Return updated record

### 1C. Register routes di `app/Modules/Supervisor/Routes/api.php`
```php
Route::prefix('attendance')->group(function () {
    Route::get('roster', [AttendanceAutologController::class, 'roster']);
    Route::post('roster/update', [AttendanceAutologController::class, 'updateRoster']);
});
```

---

## Phase 2: Frontend — Vue Component

### 2A. Rewrite `resources/js/Pages/Supervisor/Attendance/Roster/Index.vue`

**Layout (mirip admin SyncKehadiran):**

1. **Header**: "Roster Autolog" + description
2. **Group Checkboxes**: horizontal checkbox list untuk tiap group dari API 
   (Imported Shift/Group), bisa multi-select. Default: semua dicentang.
3. **Toolbar**: Period selector + Date navigation (5-date sliding window)
4. **Filter Bar**: search karyawan, filter department
5. **Cross-Table**:
   - Header: Nama (sticky left) + dates (scrollable horizontal)
   - Rows: Employee name/NIP/department (sticky left) + daily cells
   - Cells: menunjukkan check_in, check_out, lembur, lm, status badge
   - Weekends: background merah tipis
6. **Edit Modal** (click cell):
   - Employee info + date
   - Fields: check_in (time input), check_out (time input), 
     lembur (number, menit), lm (number, menit)
   - Save/Cancel buttons
   - Locked cells: disabled fields

**Data flow:**
- `onMounted`: fetch groups → fetch roster data
- Group checkbox change → re-fetch data with selected group_codes
- Period/date change → re-fetch
- Save edit → update local data + API call

---

## Phase 3: Sidebar & Navigation

### 3A. Update sidebar
File: `resources/js/Layouts/Supervisor/Sidebar.vue`  
Tambahkan menu item (atau pastikan sudah ada):
```js
{ title: 'Roster Autolog', icon: 'bx bx-grid-alt', route: '/supervisor/attendance/roster' }
```

Router sudah terdaftar di line 91+228:
```js
const SupervisorRoster = () => import('../Pages/Supervisor/Attendance/Roster/Index.vue')
// ...
{ path: 'attendance/roster', name: 'supervisor.attendance.roster', component: SupervisorRoster, ... }
```

---

## Checklist

- [ ] Phase 1A: GET roster API endpoint
- [ ] Phase 1B: POST roster/update API endpoint
- [ ] Phase 1C: Register routes
- [ ] Phase 2A: Vue Roster/Index.vue component
- [ ] Phase 3A: Sidebar menu item
- [ ] Test: verify data tampil, edit berfungsi
