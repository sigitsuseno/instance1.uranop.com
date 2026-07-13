# Phase 7: Attendance Engine + Supervisor Attendance + Web UI + Desktop (Offline Sync!)

**File**: `rw_phase_7.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1-4 (auth, organization, employee, schedule)
**Prioritas**: ⚡ KRITIS — module paling buggy di existing

---

## Tujuan

1. Raw logs → autologs sync engine
2. Manual detect + correction + push to att_prepares
3. Overtime calculation (LM + lembur)
4. Attendance reports (daily grid, summary, consecutive days)
5. Supervisor attendance snapshot + recap

---

## 1. STRUKTUR FOLDER & FILE — 4 Sub-Modul

```
app/Modules/Attendance/
├── Sync/                              # Raw logs → autologs + manual detect
│   ├── Models/
│   │   ├── AttendanceRawLog.php
│   │   ├── AttendanceAutolog.php
│   │   ├── AttendanceManualDetect.php
│   │   ├── AttendancePrepare.php
│   │   └── AttendanceRecord.php
│   ├── Services/
│   │   ├── RawLogImportService.php
│   │   ├── AutologSyncService.php
│   │   └── ManualDetectService.php
│   ├── Controllers/Api/V1/
│   │   ├── RawLogController.php
│   │   ├── AutologController.php
│   │   └── ManualDetectController.php
│   └── Routes/api.php
│
├── Overtime/
│   ├── Models/
│   │   ├── OvertimeRule.php
│   │   └── OvertimeRuleDetail.php
│   ├── Services/
│   │   └── OvertimeCalculatorService.php
│   ├── Controllers/Api/V1/
│   │   └── OvertimeRuleController.php
│   └── Routes/api.php
│
├── Report/
│   ├── Models/
│   │   └── AttendanceConsecutiveDay.php
│   ├── Services/
│   │   └── AttendanceReportService.php
│   ├── Controllers/Api/V1/
│   │   └── AttendanceReportController.php
│   └── Routes/api.php
│
└── Config/
    ├── Models/
    │   ├── AttendanceConfig.php
    │   └── AttendanceCalculatorConfig.php
    ├── Controllers/Api/V1/
    │   └── AttendanceConfigController.php
    └── Routes/api.php

app/Modules/Supervisor/
└── Attendance/
    ├── Models/
    │   └── SupervisorAttendanceSnapshot.php
    ├── Services/
    │   ├── AttendanceSnapshotService.php
    │   └── SupervisorRecapService.php
    ├── Controllers/Api/V1/
    │   ├── AttendanceSnapshotController.php
    │   └── RecapController.php
    └── Routes/api.php
```

---

## 2. DATABASE

### 2.1 `attendance_raw_logs` (rename from `att_raw_logs`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| pin | varchar(20) | PIN fingerprint |
| scan_datetime | datetime | Waktu scan |
| verify_mode | varchar(20) nullable | Mode verifikasi |
| import_batch | varchar(50) nullable | Batch ID import |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `(pin, scan_datetime)`

### 2.2 `attendance_autologs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| date | date | |
| check_in | time nullable | Jam masuk |
| check_out | time nullable | Jam keluar |
| actual_in | time nullable | Scan pertama |
| actual_out | time nullable | Scan terakhir |
| schedule_in | time nullable | Jadwal masuk (dari roster) |
| schedule_out | time nullable | Jadwal keluar (dari roster) |
| status | varchar(20) | present, absent, leave, holiday, off |
| late_duration | int default 0 | Menit keterlambatan |
| overtime_duration | int default 0 | **DEPRECATED** — gak dipakai lagi |
| lm | int default 0 | Lembur Minggu/holiday (MENIT) |
| lm_calc | float default 0 | Lembur Minggu/holiday (JAM) |
| lembur | int default 0 | Lembur reguler Senin-Sabtu (MENIT) |
| lembur_calc | float default 0 | Lembur reguler (JAM) |
| scan_count | int default 0 | Jumlah scan hari itu |
| sick_duration | int default 0 | Durasi sakit (hari) |
| leave_duration | int default 0 | Durasi cuti (hari) |
| izin_duration | int default 0 | Durasi izin (hari) |
| is_holiday | tinyint(1) default 0 | |
| holiday_name | varchar(100) nullable | |
| deduct_day | int default 0 | Hari potongan |
| deduct_attendance | tinyint(1) default 0 | Flag potongan absensi |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `(employee_id, date)` UNIQUE, `(date)`, `(status)`

### 2.3 `attendance_manual_detects` (rename from `att_manual_detect`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| date | date | |
| check_in | time nullable | |
| check_out | time nullable | |
| status | varchar(20) | lengkap, perhatian, cek |
| schedule_in | time nullable | |
| schedule_out | time nullable | |
| scan_records | json nullable | Array scan raw_logs |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `(employee_id, date)` UNIQUE

### 2.4 `attendance_prepares` (rename from `att_prepares`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| date | date | |
| periode_start | date | |
| periode_end | date | |
| check_in | time nullable | |
| check_out | time nullable | |
| schedule_in | time nullable | |
| schedule_out | time nullable | |
| status | varchar(20) | hadir, absent |
| review_status | varchar(20) | lengkap, perhatian, cek |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `(employee_id, date)` UNIQUE, `(periode_start, periode_end)`

### 2.5 `attendance_records` (rename from `att_records`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| attendance_date | date | |
| status | varchar(20) | |
| check_in | time nullable | |
| check_out | time nullable | |
| overtime_minutes | int default 0 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.6 `attendance_consecutive_days` (rename from `att_consecutive_days`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| start_date | date | |
| end_date | date | |
| total_days | int | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.7 `overtime_rules`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(100) | |
| type | enum('weekday','sunday','holiday') | |
| is_active | tinyint(1) default 1 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.8 `overtime_rule_details`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| overtime_rule_id | bigint (FK→overtime_rules) | |
| hour_from | int | Jam ke-1 |
| hour_to | int | Jam ke-... |
| multiplier | decimal(5,2) | 1.5x, 2x, 3x |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.9 `attendance_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| key | varchar(50) | |
| value | text | |
| description | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.10 `attendance_calculator_configs`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| key | varchar(50) | |
| value | text | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.11 `supervisor_attendance_snapshots` (rename from `supervisor_att_snapshot`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| period_start | date | |
| period_end | date | |
| segment | varchar(5) nullable | A / B (untuk split period) |
| hk | int default 0 | Hari kerja |
| lm | int default 0 | LM (MENIT) |
| lm_count | float default 0 | LM (JAM) |
| lembur | int default 0 | Lembur (MENIT) |
| lembur_count | float default 0 | Lembur (JAM) |
| sick_count | int default 0 | |
| leave_count | int default 0 | |
| absent_count | int default 0 | |
| deduct_day | int default 0 | |
| data | json nullable | Detail harian |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(employee_id, period_start, period_end, segment)`

---

## 3. BUSINESS RULES (Wajib diporting 1:1 dari existing)

### 3.1 Overtime Detection

```
IF (date is Sunday) OR (autolog.is_holiday == true):
    overtime = autolog.lm           # MENIT
    overtime_calc = autolog.lm_calc # JAM
ELSE:
    overtime = autolog.lembur           # MENIT
    overtime_calc = autolog.lembur_calc # JAM

# SATU HARI HANYA PAKAI SALAH SATU — JANGAN DIJUMLAHKAN
```

### 3.2 Status Mapping

```
IF sick_duration > 0     → 'S' (Sakit)
IF izin_duration > 0     → 'I' (Izin)
IF status == 'present'   → 'H' (Hadir)
IF status == 'leave'     → 'C' (Cuti)
IF status == 'absent'    → '-' (Absen)
IF status == 'holiday'   → 'L' (Libur)
IF status == 'off'       → 'O' (Off)
NO LOG                   → '' (kosong)
```

### 3.3 Present Detection

```
Present IF (check_in IS NOT NULL) OR (check_out IS NOT NULL)
```

### 3.4 Manual Detect Flow

```
1. Fetch raw_logs for employee + date range
2. Auto-detect:
   - check_in = scan PERTAMA hari itu
   - check_out = scan TERAKHIR hari itu
   - status = 'lengkap' (jika keduanya ada), 'perhatian' (salah satu), 'cek' (tidak ada)
3. User review + correction (via UI)
4. Save → update status di att_manual_detect
5. Push Prepare → upsert ke att_prepares
```

### 3.5 Push Prepare Pattern

```php
// Ambil SEMUA record dalam rentang (bukan per halaman)
$records = ManualDetect::whereBetween('date', [$startDate, $endDate])->get();

foreach ($records as $record) {
    $reviewStatus = match ($record->status) {
        'lengkap'   => 'lengkap',
        'perhatian' => 'perhatian',
        default     => 'cek',
    };
    $attStatus = ($record->check_in || $record->check_out) ? 'hadir' : 'absent';

    DB::table('attendance_prepares')->upsert([
        'employee_id'   => $record->employee_id,
        'date'          => $record->date->toDateString(),
        'periode_start' => $startDate,
        'periode_end'   => $endDate,
        'check_in'      => $record->check_in,
        'check_out'     => $record->check_out,
        'status'        => $attStatus,
        'review_status' => $reviewStatus,
        'updated_at'    => now(),
    ], ['employee_id', 'date'], [
        'check_in', 'check_out', 'status', 'review_status', 'updated_at'
    ]);
}
```

### 3.6 Snapshot — SUM APA ADANYA

```php
// JANGAN KONVERSI:
$lm          = (int)   $logs->sum('lm');           // MENIT
$lmCount     = (float) $logs->sum('lm_calc');       // JAM
$lembur      = (int)   $logs->sum('lembur');        // MENIT
$lemburCount = (float) $logs->sum('lembur_calc');   // JAM

// JANGAN: round(sum * 60), count() hari, round()
```

---

## 4. YANG HARUS DIPERBAIKI

### 4.1 Field `overtime_duration` — HAPUS/DEPRECATED

Existing punya field `overtime_duration` di `attendance_autologs`. Field ini TIDAK DIPAKAI lagi. Yang dipakai: `lm`, `lm_calc`, `lembur`, `lembur_calc`.

### 4.2 Employee Lookup — PAKAI NIP

Employee lookup dari raw_logs selalu pakai `nip` (bukan `employee_code`). Pin di raw_logs = employee.nip.

### 4.3 Snapshot Orphan Records

Setelah `save_all` / `storeBulk`, hapus record yatim:
```php
Model::where('pay_period_id', $period->id)
    ->whereNotIn('employee_id', $employeeIds)
    ->delete();
```

---

## 5. TASK LIST

### Task 1: Migration
- [ ] Rename `att_raw_logs` → `attendance_raw_logs`
- [ ] Rename `att_manual_detect` → `attendance_manual_detects`
- [ ] Rename `att_prepares` → `attendance_prepares`
- [ ] Rename `att_records` → `attendance_records`
- [ ] Rename `att_consecutive_days` → `attendance_consecutive_days`
- [ ] Rename `supervisor_att_snapshot` → `supervisor_attendance_snapshots`
- [ ] Drop `supervisor_attendances` (cek dulu isinya)
- [ ] `overtime_rules`
- [ ] `overtime_rule_details`
- [ ] `attendance_configs`
- [ ] `attendance_calculator_configs`

### Task 2: Models
- [ ] `AttendanceRawLog.php`
- [ ] `AttendanceAutolog.php` — **PAKAI ELOQUENT** (bukan DB::table)
- [ ] `AttendanceManualDetect.php`
- [ ] `AttendancePrepare.php`
- [ ] `AttendanceRecord.php`
- [ ] `AttendanceConsecutiveDay.php`
- [ ] `OvertimeRule.php`
- [ ] `OvertimeRuleDetail.php`
- [ ] `SupervisorAttendanceSnapshot.php`

### Task 3: Services (PRIORITAS — ini core engine)

**`AutologSyncService.php`**
- `syncFromRawLogs(employeeId, startDate, endDate)`
- `syncFromPrepares(periodeStart, periodeEnd)`
- `detectOvertime(autolog, roster, shift)` — LM vs lembur
- `calculateStatus(autolog)` — present/absent/leave/etc

**`ManualDetectService.php`**
- `fetchAndDetect(employeeId, dateRange)` — auto-detect dari raw_logs
- `saveCorrection(records, mode)` — single/all
- `pushPrepare(startDate, endDate)` — upsert ke att_prepares
- `getReviewStatus(checkIn, checkOut)` — lengkap/perhatian/cek

**`OvertimeCalculatorService.php`**
- `calculate(autolog, roster)` — return lm + lembur dalam MENIT & JAM
- `isWeekendOrHoliday(date)` — Sunday/holiday check
- `applyRules(totalOvertimeHours, ruleType)` — progressive multiplier

**`AttendanceReportService.php`**
- `dailyGrid(employeeIds, startDate, endDate)` — tabel harian
- `summary(employeeIds, startDate, endDate)` — rekap per employee
- `consecutiveDays(employeeIds, startDate, endDate)` — deteksi hari berturut-turut
- `export(type, params)` — Excel export
- `print(type, params)` — HTML print

**`AttendanceSnapshotService.php`**
- `generate(periodStart, periodEnd, employeeIds)` — bikin snapshot
- `getByEmployee(employeeId, periodStart, periodEnd)`

**`SupervisorRecapService.php`**
- `getRecap(periodStart, periodEnd)` — recap per group

### Task 4: Controllers

**Sync:**
- `RawLogController.php` — index, import (upload Excel)
- `AutologController.php` — index, sync
- `ManualDetectController.php` — index, save, pushPrepare

**Overtime:**
- `OvertimeRuleController.php` — CRUD

**Report:**
- `AttendanceReportController.php` — daily, summary, consecutive, export, print

**Config:**
- `AttendanceConfigController.php` — index, update

**Supervisor:**
- `AttendanceSnapshotController.php` — index, store (generate), storeBulk
- `RecapController.php` — index, export, print

### Task 5: Routes
- [ ] `Attendance/Sync/Routes/api.php`
- [ ] `Attendance/Overtime/Routes/api.php`
- [ ] `Attendance/Report/Routes/api.php`
- [ ] `Attendance/Config/Routes/api.php`
- [ ] `Supervisor/Attendance/Routes/api.php`

### Task 6: Test — HEAVY, ini kritis!

- [ ] `AutologSyncServiceTest.php` — sync flow, edge cases
- [ ] `ManualDetectServiceTest.php` — auto-detect, correction, pushPrepare
- [ ] `OvertimeCalculatorServiceTest.php` — LM vs lembur, Sunday/holiday detection
- [ ] `AttendanceReportServiceTest.php` — daily grid generation, status mapping
- [ ] `AttendanceSnapshotServiceTest.php` — sum apa adanya, orphan cleanup

---

## 6. ENDPOINT SUMMARY

```
# Sync — Raw Logs
GET    /api/v1/attendance/raw-logs?start_date=&end_date=&pin=
POST   /api/v1/attendance/raw-logs/import           # upload Excel

# Sync — Autologs
GET    /api/v1/attendance/autologs?start_date=&end_date=&employee_id=
POST   /api/v1/attendance/autologs/sync              # trigger sync

# Sync — Manual Detect
GET    /api/v1/attendance/manual-detect?start_date=&end_date=
POST   /api/v1/attendance/manual-detect/save         # mode: single|all
POST   /api/v1/attendance/manual-detect/push-prepare

# Overtime
GET    /api/v1/attendance/overtime-rules
POST   /api/v1/attendance/overtime-rules
PUT    /api/v1/attendance/overtime-rules/{id}
DELETE /api/v1/attendance/overtime-rules/{id}

# Report
GET    /api/v1/attendance/reports/daily?start_date=&end_date=&employee_id=
GET    /api/v1/attendance/reports/summary?start_date=&end_date=
GET    /api/v1/attendance/reports/consecutive?start_date=&end_date=
GET    /api/v1/attendance/reports/export?type=daily&start_date=&end_date=
GET    /api/v1/attendance/reports/print?type=daily&start_date=&end_date=

# Config
GET    /api/v1/attendance/configs
PUT    /api/v1/attendance/configs

# Supervisor
GET    /api/v1/supervisor/attendance?period_start=&period_end=
POST   /api/v1/supervisor/attendance/snapshot
GET    /api/v1/supervisor/attendance/recap?period_start=&period_end=
GET    /api/v1/supervisor/attendance/export
GET    /api/v1/supervisor/attendance/print
```
