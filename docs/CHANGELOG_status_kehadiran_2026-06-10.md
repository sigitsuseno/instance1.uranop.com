# Changelog — 10 Juni 2026

> Sesi bareng Paijo: refactor status kehadiran + perbaikan edit manual + tombol perbarui status

---

## 1. Status Kehadiran → Kode LeaveType Spesifik

### Perubahan Backend

**`AttendanceSyncService.php`**
- `getLeaveStatus()`: return kode LeaveType spesifik lowercase (`ct`, `cm`, `skt`, `itm`, dll) — bukan generic `cuti`/`izin`/`sakit`
- `getSyncSummary()`: hapus count `terlambat`/`cuti`/`izin`/`sakit`, tambah count `leave` (WHERE IN kode leave type)

**`AttendancePrepare.php`** (model)
- HAPUS constant: `STATUS_TERLAMBAT`, `STATUS_CUTI`, `STATUS_IZIN`, `STATUS_SAKIT`
- `getStatusLabelAttribute()`: resolve nama leave type via static cache `leave_type_cache`
- `getStatusBadgeClassAttribute()`: semua leave type fallback biru
- Tambah `$appends` = `status_label`, `status_badge_class`, `review_status_label`, `review_status_badge_class`
- Static cache `$leaveTypeCache` + `getLeaveTypeName()` / `loadLeaveTypeCache()` / `getLeaveTypeInfo()` (public)
- `isExcused()`: pakai cache, bukan query langsung

**`AttendanceService.php`**
- `autoLengkapi()`: simpan kode leave type spesifik (bukan `STATUS_CUTI`), eager-load `leaveType`, counter `leave`
- `bulkHitungLembur()`: skip cuti/izin/sakit pakai `isExcused() && !isOffDay()` (LIBUR/OFF tetap dihitung)

**`UangMakanReportController.php`**
- `mapStatus()`: hapus `'terlambat'`, tambah lookup LeaveType cache

**`AttendanceApiController.php`**
- `prepareOvertimeSummary`: hapus check `'terlambat'`, cuma cek `'hadir'`

### Perubahan Frontend

**`SyncKehadiran.vue` (Admin)**
- Status badge: pakai `status_label` dari API → tampil nama cuti spesifik
- `statusLabel()` / `statusBadgeClass()`: hapus `terlambat`/`cuti`/`izin`/`sakit`
- Stats cards: 9→6 card (Total, Hadir, Absen, Cuti/Izin/Sakit aggregate, Belum Lengkap, Terkunci)
- Filter dropdown: hapus "Terlambat"
- Edit modal: dropdown cuti/izin/sakit diganti `<optgroup>` dinamis dari API leave types
- `fetchLeaveTypes()`: ambil daftar leave type untuk dropdown

### Daftar Status Baru

| Kode | Nama |
|------|------|
| `hadir` | Hadir |
| `absent` | Absen |
| `libur` | Hari Libur |
| `off` | Hari Minggu |
| `ct` | Cuti Tahunan |
| `cm` | Cuti Menikah |
| `ckm` | Cuti Keluarga Meninggal |
| `ch` | Cuti Hajatan |
| `ctm` | Cuti Melahirkan |
| `ctk` | Cuti Keguguran |
| `cth` | Cuti Haid |
| `cti` | Cuti Ibadah |
| `skt` | Sakit |
| `itm` | Izin Tidak Masuk |
| `imt` | Izin Masuk Terlambat |
| `ipa` | Izin Pulang Awal |

---

## 2. Perbaikan Edit Manual (Lengkapi per Cell)

### Masalah
Klik cell tanpa data prepare → `prepareId` = null → validasi backend gagal (422) → tanpa error feedback

### Perbaikan

**Backend**
- `AttendanceApiController::prepareLengkapi()`: validasi `id` nullable, `employee_id` + `date` required jika id kosong
- `AttendanceService::bulkLengkapi()`: support create (record baru) + update, fix `$prepare->update()` → `fill()->save()` untuk new record, counter `updated`/`created` akurat

**Frontend**
- `openEdit()`: kirim `employeeId` + `date` ke `editForm`
- `handleSaveEdit()`: jika `prepareId` kosong, otomatis kirim `employee_id` + `date`; update local data setelah sukses
- Error feedback: alert merah di modal jika gagal

---

## 3. Tombol "Perbarui Status"

### Masalah
Data existing masih pakai status generic (`cuti`/`izin`/`sakit`), tapi sync ulang bahaya karena overwrite data manual.

### Solusi
Tombol khusus di halaman Sync Kehadiran: **"Perbarui Status"**

**Backend**
- `AttendanceService::migrateLegacyStatuses($start, $end)`: cari record status legacy → cocokkan dgn approved leave → update ke kode spesifik
- Endpoint: `POST /api/v1/attendance/prepare/update-status-legacy`
- Route: `api.php`

**Frontend**
- Tombol "Perbarui Status" + `handleUpdateStatus()` — konfirmasi → proses → refresh data
- Aman: cuma update field `status`, skip locked, skip yg sudah kode spesifik

---

## File yang Berubah

| File | Action |
|------|--------|
| `app/Modules/Attendance/Services/AttendanceSyncService.php` | MODIFY |
| `app/Modules/Attendance/Models/AttendancePrepare.php` | MODIFY |
| `app/Modules/Attendance/Services/AttendanceService.php` | MODIFY |
| `app/Modules/Attendance/Controllers/Api/V1/AttendanceApiController.php` | MODIFY |
| `app/Modules/Attendance/Routes/api.php` | MODIFY |
| `app/Modules/Reports/Controllers/Api/V1/UangMakanReportController.php` | MODIFY |
| `resources/js/Pages/Admin/Attendance/SyncKehadiran.vue` | MODIFY |
