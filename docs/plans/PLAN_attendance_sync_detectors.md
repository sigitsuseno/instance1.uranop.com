# Plan: Port Deteksi Work Pattern dari hris-system ke instance1

> **Goal:** Menambahkan detector terpisah untuk 9 tipe work pattern (FIXED, FLEX-SHIFT, SHIFT, LONGSHIFT, SPLIT, FLEXI, HOURLY, ON_CALL, SEASONAL) ke AttendanceSyncService — sesuai sistem lama hris-system.

> **Scope:** HANYA logic deteksi (detect* methods + helper). **Tidak** mengubah struktur data att_prepares, tidak mengubah savePrepare(), tidak mengubah kalkulasi.

**File yang diubah:** `app/Modules/Attendance/Services/AttendanceSyncService.php`

---

## ANALISIS AWAL

### Struktur processRoster saat ini (instance1):

```php
protected function processRoster($roster, $dateStr, $allLogs): AttendancePrepare {
    $employee = $roster->employee;
    $shift    = $roster->shift;
    $isSunday  = Carbon::parse($dateStr)->isSunday();
    $isHoliday = $this->isHoliday($dateStr);

    // 1. Leave check (already handled before detector)
    $leaveStatus = $this->getLeaveStatus(...);
    if ($leaveStatus) { return savePrepare(...); }

    // 2. Get logs
    $logs = $allLogs->get($empCode, collect());

    // 3. No logs → status absent/holiday/off
    if ($logs->isEmpty()) { ... return savePrepare(...); }

    // 4. Match check_in & check_out
    $result = match ($workPatternType) {
        'FIXED' => $this->detectFixed($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        default => $this->detectShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    };

    return $this->savePrepare(...);
}
```

### Struktur processRoster di hris-system (lama):

```php
protected function processRoster(EmployeeShiftRoster $roster) {
    // Holiday, leave, permit di-check DI DALAM detector
    $result = match ($roster->work_pattern_type) {
        FIXED       => $this->detectFixed($roster, $logs),
        FLEX_SHIFT  => $this->detectFlexShift($roster, $logs),
        SHIFT       => $this->detectShift($roster, $logs),
        LONGSHIFT   => $this->detectLongshift($roster, $logs),
        SPLIT       => $this->detectSplit($roster, $logs),
        FLEXI       => $this->detectFlexi($roster, $logs),
        HOURLY      => $this->detectHourly($roster, $logs),
        ON_CALL     => $this->detectOnCall($roster, $logs),
        SEASONAL    => $this->detectSeasonal($roster, $logs),
        default     => $this->detectFlexi($roster, $logs),
    };
    return $this->saveAttendanceRecord($roster, $result);
}
```

---

## PERBEDAAN YANG HARUS DIADAPTASI

| Aspek | hris-system (lama) | instance1 (baru) |
|-------|-------------------|-----------------|
| **Leave/permit check** | Di dalam tiap detector (`$roster->is_leave`) | Sudah di-handle di `processRoster` SEBELUM detector |
| **Holiday flag** | `$roster->is_holiday` (dari roster) | `$isHoliday` param (dari method `isHoliday()`) |
| **Sunday flag** | `$roster->is_sun` | `$isSunday` param (dari Carbon::isSunday()) |
| **Status "cek"** | Return status `"cek"` langsung | Return `has_in`/`has_out` flags, status tetap `HADIR` |
| **Log IDs** | Return `check_in_log_id`, `check_out_log_id` | **Tidak perlu** — sistem baru ga simpan log ID |
| **Deduct flag** | Return `deduct_attendance` | **Tidak perlu** — sistem baru pakai status absent |
| **Result format** | `formatResult(checkIn, checkOut, status, deduct, logId1, logId2)` | Return array: `[check_in, check_out, status, has_in, has_out, is_holiday, is_sunday]` |
| **Helper: getLogsInRange** | Method terpisah | Sudah ada `findLogInWindow()` (ekuivalen) |
| **Helper: findClosestLog** | Method terpisah | Sudah built-in di `findLogInWindow()` |

---

## TASK LIST

### Task 1: Siapkan signature detector baru

**Objective:** Buat semua stub method detector yang sesuai signature sistem baru.

Semua detector menerima parameter:
```
detectXxx(Collection $logs, ?Shift $shift, string $dateStr, bool $isHoliday, bool $isSunday, EmployeeShiftRoster $roster): array
```

Return format yang diharapkan:
```php
[
    'check_in'    => ?Carbon,   // waktu check-in
    'check_out'   => ?Carbon,   // waktu check-out
    'status'      => string,    // 'hadir', 'absent', 'libur', 'off'
    'has_in'      => bool,      // apakah check_in ditemukan
    'has_out'     => bool,      // apakah check_out ditemukan
    'is_holiday'  => bool,      // forward
    'is_sunday'   => bool,      // forward
]
```

---

### Task 2: Implement detectFlexShift — port dari hris-system

**Objective:** Port `detectFlexShift()` dengan adaptasi: hapus leave/permit check (sudah handled), ganti holiday check ke param, ganti formatResult ke array return.

**Logic khusus FLEX-SHIFT:**
- Hari libur/Minggu + ADA log → pakai holiday config dari `$shift->metadata`
- Hari libur/Minggu + NO log → return holiday/off
- Hari biasa → window matching biasa (cek `$shift->check_in_start/end`, `$shift->check_out_start/end`)

**Yang perlu diadaptasi:**
- `$roster->is_leave` / `$roster->is_permit` → HAPUS (sudah handled)
- `$roster->is_holiday` → ganti ke `$isHoliday`
- `$roster->is_sun` → ganti ke `$isSunday`
- `$this->getLogsInRange(...)` → ganti ke `$this->findLogInWindow(...)` atau implement ulang
- `$this->findClosestLog(...)` → logic sudah ada di `findLogInWindow()`
- `$this->formatResult(...)` → return array format baru
- Status `"cek"` → return dengan `has_in`/`has_out` flags (status tetap `HADIR` jika ada salah satu scan)

---

### Task 3: Implement detectShift — port dari hris-system

**Objective:** Port `detectShift()` — window matching standar.

**Logic:**
- No logs + holiday/sunday → return libur/off
- No logs + workday → return absent
- Ada shift → window matching (check_in_start..end, check_out_start..end)
- Overnight handling

**Adaptasi:** Sama seperti FlexShift, ganti ke signature baru.

---

### Task 4: Implement detectLongshift — port dari hris-system

**Objective:** Port `detectLongshift()`.

**Note:** Di sistem lama, Longshift dan Shift & Flexi identik. Untuk efisiensi, bisa alias ke detectShift dulu. Tapi kalau nanti ada business rule berbeda, sudah ada stub terpisah.

---

### Task 5: Implement detectSplit — port dari hris-system

**Objective:** Port `detectSplit()`.

**Note:** Sama — identik dengan Shift di sistem lama. Bisa alias dulu.

---

### Task 6: Implement detectFlexi — port dari hris-system

**Objective:** Port `detectFlexi()`.

**Note:** Sama — identik dengan Shift di sistem lama. Bisa alias dulu.

---

### Task 7: Implement detectHourly — port dari hris-system

**Objective:** Port `detectHourly()`.

**Note:** Sama — identik dengan Shift di sistem lama.

---

### Task 8: Implement detectOnCall — port dari hris-system

**Objective:** Port `detectOnCall()`.

**Note:** Sama — identik dengan Shift di sistem lama.

---

### Task 9: Implement detectSeasonal — port dari hris-system

**Objective:** Port `detectSeasonal()`.

**Note:** Sama — identik dengan Shift di sistem lama.

---

### Task 10: Update processRoster dispatcher

**Objective:** Ganti `match` statement di `processRoster()` untuk memanggil detector yang sesuai.

**Before:**
```php
$result = match ($workPatternType) {
    'FIXED' => $this->detectFixed(...),
    default => $this->detectShift(...),
};
```

**After:**
```php
$result = match ($workPatternType) {
    'FIXED'      => $this->detectFixed($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'FLEX-SHIFT' => $this->detectFlexShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'SHIFT'      => $this->detectShiftWorkPattern($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'LONGSHIFT'  => $this->detectLongshift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'SPLIT'      => $this->detectSplit($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'FLEXI'      => $this->detectFlexi($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'HOURLY'     => $this->detectHourly($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'ON_CAL'     => $this->detectOnCall($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    'SEASONAL'   => $this->detectSeasonal($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
    default      => $this->detectShiftWorkPattern($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
};
```

**⚠️ Penting:** Method yang sekarang bernama `detectShift` harus di-rename ke `detectShiftWorkPattern` karena `match` di PHP 8 menganggap `SHIFT` sebagai pattern matching, bukan string literal.

---

### Task 11: Tambahkan helper findLogInWindow untuk holiday (jika diperlukan)

**Objective:** FLEX-SHIFT butuh logic holiday window yang berbeda — mencari log setelah jam tertentu (`holiday_check_in_start`). Method `findLogInWindow` saat ini tidak support "cari semua log setelah jam X". Perlu adaptasi kecil.

**Opsi:** Bikin variant `findLogsAfterTime(Collection $logs, string $dateStr, string $afterTime)` untuk FLEX-SHIFT holiday case.

---

### Task 12: Verifikasi & cleanup

**Objective:** Pastikan:
- Method lama `detectShift()` sudah di-rename
- Semua detector pakai signature yang sama
- Return format konsisten
- Tidak ada duplicate code
- Type hinting aman

---

## RINGKASAN PERUBAHAN FILE

| File | Action |
|------|--------|
| `app/Modules/Attendance/Services/AttendanceSyncService.php` | MODIFY: tambah 8 detector + 1 helper + rename detectShift + update processRoster dispatcher |

---

## RISIKO & CATATAN

1. **FLEX-SHIFT holiday config**: Sistem lama membaca `$shift->metadata['holiday_check_in_start']` dll. Pastikan field `metadata` di tabel shifts ada dan formatnya JSON. Kalau belum ada, fallback ke default value (05:50, 10:00, 19:00, 11 jam).

2. **ON_CAL vs ON_CALL**: Di WorkPatternTypeConstants, value-nya `'ON_CAL'` (kurang L). Pastikan match di processRoster pakai `'ON_CAL'` juga.

3. **PHP 8 match quirk**: `match` memperlakukan string di case sebagai pattern. `'SHIFT'` bukan reserved word sih, tapi lebih aman rename method untuk hindari kebingungan.

4. **Performance**: 9 detector terpisah tidak menambah overhead karena hanya 1 yang dipanggil per roster. Logic di dalam detector relatif ringan (filter collection, hitung diff).

---

*Plan dibuat: 4 Juni 2026 | Paijo untuk Sigit*
