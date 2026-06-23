# Plan: Perbaikan Flow Import Attendance Supervisor

**Tanggal:** 2026-06-23
**Scope:** `/supervisor/attendance/import` — sesuaikan flow dengan data real

---

## Situasi Sekarang

| Periode | Sumber Data | Proses |
|---|---|---|
| 1-4 | XLSX hardcoded di `database/seeders/` | `AttendanceDataFixImport` → `attendance_autologs` + `OvertimeSyncService` |
| 5-6 | Generate fake dari roster | `AuditorLogService` → `attendance_autologs` |
| 7+ | ❌ Tidak di-handle | Crash (`$result` undefined) |

File `.bin` yang diupload = gimmick (validasi doang, nggak dibaca).

---

## Target

| Periode | Sumber Data | Proses |
|---|---|---|
| 1-4 | XLSX di `database/seeders/` (data REAL input manual) | **TETAP** — `AttendanceDataFixImport` + `OvertimeSyncService` |
| 5-12 | `att_prepares` (data REAL dari fingerprint sync) | **BARU** — Service baru baca `att_prepares` + join roster → upsert ke `attendance_autologs` |
| AuditorLogService | — | **DIHAPUS** dari flow import |

---

## Phase 1: Copy XLSX File Seeders

Copy 4 file dari old → new:
- `data_januari.xlsx`
- `februari.xlsx`
- `sampe_data.xlsx`
- `april.xlsx`

Sumber: `hris-system/database/seeders/`
Target: `instance1/database/seeders/`

---

## Phase 2: Service Baru — `AttendanceImportFromPrepares`

File: `app/Modules/Supervisor/Attendance/Services/AttendanceImportFromPrepares.php`

### Mapping `att_prepares` → `attendance_autologs`

| att_prepares | attendance_autologs | Keterangan |
|---|---|---|
| employee_id | employee_id | langsung |
| date | date | langsung |
| check_in | check_in, actual_in | |
| check_out | check_out, actual_out | |
| late_minutes | late_duration | menit |
| overtime + lm | overtime_duration | raw overtime (menit), tanpa multiplier |
| status | status | normalisasi: `hadir`→`present`, `absent`→`absent`, leave code→`leave`/`izin`/`sakit` |

Dari `EmployeeShiftRoster` (join):
- company_id, branch_id
- id → employee_shift_roster_id
- is_sun, is_sat, is_holiday, is_half_day

### Logic

1. Query `att_prepares` WHERE date BETWEEN `$startDate` AND `$endDate`
2. Left join `sch_employee_shift_roster` untuk dapat context roster
3. Iterate per record, mapping field
4. `updateOrCreate` ke `attendance_autologs` (unique key: employee_id + date)

### Normalisasi Status

```
hadir → present
absent → absent
libur → holiday / off (tergantung is_holiday)
off → off
<leave_type_code> → leave / izin / sakit (cek ke LeaveType)
```

---

## Phase 3: Update `AttendanceImportController`

### Method `store()` — perubahan:

```php
// PERIODE 1-4: TETAP (XLSX hardcoded)
if (isset($periodFileMap[$periodId])) {
    $result = AttendanceDataFixImport::runImport(...);
    $syncResult = $this->overtimeSyncService->sync(...);
}
// PERIODE 5-12: BARU (dari att_prepares)
elseif ($periodId >= 5 && $periodId <= 12) {
    $result = $this->importFromPreparesService->import(...);
}
```

### Constructor — perubahan:

- Tambah injection `AttendanceImportFromPrepares`
- **Hapus** injection `AuditorLogService` (tidak dipakai di store lagi)
- Tapi jangan hapus file `AuditorLogService.php` (mungkin dipakai di flow lain)

---

## Phase 4: Update Frontend (Import.vue)

Minor update:
- Deskripsi: jelaskan bahwa file `.bin` hanya trigger, data diambil dari XLSX (periode 1-4) atau `att_prepares` (periode 5-12)
- Accept file: tambah `.dat` selain `.bin`

---

## Verifikasi

- [ ] File XLSX ada di `database/seeders/`
- [ ] `AttendanceImportFromPrepares` service dibuat
- [ ] `AttendanceImportController::store()` updated
- [ ] Old flow (1-4) tidak rusak
- [ ] New flow (5-12) membaca dari `att_prepares`
- [ ] `AuditorLogService` injection dihapus dari controller
- [ ] Frontend Import.vue diupdate
