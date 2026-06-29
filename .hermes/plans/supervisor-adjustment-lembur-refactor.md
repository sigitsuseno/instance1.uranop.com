# Plan: Upgrade Perhitungan Lembur Supervisor — Pakai AttendanceCalculatorService

## Latar Belakang

Sekarang, tombol **Perhitungan Lembur** di `/supervisor/attendance` (`AttendanceAutologController@adjustment`) pake **hardcoded multiplier**:
- SHIFT + holiday: jam-1 diabaikan, sisanya ×2/×3/×4
- Non-SHIFT (FIXED/FLEX-SHIFT): ≤0.5j ×1, jam-1 ×1.5, sisanya ×2

Sementara `AttendanceCalculatorService` (dipakai modul Attendance) udah pake:
- `OvertimeRule` + `OvertimeRuleDetail` → multiplier per-jam per-work_pattern
- `OvertimeCalculatorConfig` → baseline config per-work_pattern
- Fallback ke hardcoded kalau rule nggak ada

**Masalah:** hasil perhitungan lembur Supervisor bisa beda sama modul Attendance karena aturannya beda.

## Goal

Refactor `adjustment()` di `AttendanceAutologController` supaya:
1. Pake `AttendanceCalculatorService` untuk hitung `lembur_calc`
2. Konsisten sama modul Attendance — multiplier dari `OvertimeRule` per work_pattern
3. Juga update field `lm` dan `lm_calc` yang sekarang belum tersentuh

## Detail Perubahan

### 1. Field di `attendance_autologs` yang relevan

| Field | Saat ini | Nanti |
|-------|----------|-------|
| `lembur` (int, menit) | ✅ Terisi dari import | ✅ Dipertahankan (raw input) |
| `lembur_calc` (float) | ❌ Hardcoded formula | ✅ Pakai `overtime_count`/60 |
| `lm` (int, menit) | ❌ Nggak disentuh | ✅ Diisi dari `calc['lm']` |
| `lm_calc` (int) | ❌ Nggak disentuh | ✅ Diisi dari `calc['lm_count']` — tambah ke `$fillable` |

### 2. Algoritma baru di `adjustment()`

```
foreach autolog in range:
    // Tetap: update leave/cuti (tidak berubah)
    ...

    // Baru: hitung lembur via AttendanceCalculatorService
    if ($autolog->lembur > 0):
        roster = autolog->employeeShiftRoster
        shift = roster?->shift
        workPatternType = roster?->workPattern?->employee_type
        workPatternId = roster?->work_pattern_id

        calc = calculator->calculate(
            prepare: autolog (→ butuh AttendancePrepare type hint)
            manualOvertime: autolog->lembur
            ...
        )

        updateData['lembur_calc'] = calc['overtime_count'] / 60  (konversi menit→jam)
        updateData['lm']          = calc['lm']
        updateData['lm_calc']     = calc['lm_count']
    endif
```

### 3. Tantangan: Type Hint `AttendancePrepare`

`AttendanceCalculatorService::calculate(AttendancePrepare $prepare, ...)` — Supervisor punya `SupervisorAttendance` (beda model).

**Solusi:** Buat method baru di `AttendanceCalculatorService` yang bisa nerima parameter individual tanpa harus objek `AttendancePrepare` — misal `calculateFromValues()`.

Atau alternatif lebih sederhana: panggil langsung `OvertimeRule` + `OvertimeRuleDetail` query di controller, replikasi logika `multiplyFromRule()` yang cuma ~20 baris. Tapi ini duplikasi.

**Pilihan:**

- **A.** Tambah public method `calculateManual()` di `AttendanceCalculatorService` — paling bersih, reusable
- **B.** Duplikasi logic `multiplyFromRule()` di controller — simple tapi duplikasi
- **C.** Buat adapter/temporary AttendancePrepare — hacky

**Rekomendasi: A** — tambah method `calculateManual()` di `AttendanceCalculatorService` yang ngga perlu objek `AttendancePrepare`, karena `manualOvertime` sudah di-pass langsung.

### 4. Method baru di `AttendanceCalculatorService`

```php
/**
 * Hitung overtime/LM multiplier tanpa perlu AttendancePrepare.
 * Dipanggil dari supervisor adjustment.
 */
public function calculateManual(
    int $manualOvertime,
    ?string $workPatternType = null,
    ?int $workPatternId = null,
    bool $isHoliday = false,
    bool $isSunday = false,
    bool $isSaturday = false,
): array
```

Output: `{ lm: int, lm_count: int, overtime: int, overtime_count: int, late_minutes: 0 }`

### 5. File yang diubah

1. `app/Modules/Attendance/Services/AttendanceCalculatorService.php`
   - Tambah method `calculateManual()`
   - (Opsional) Jadikan `multiplyFromRule()` public

2. `app/Modules/Supervisor/Attendance/Controllers/AttendanceAutologController.php`
   - Inject `AttendanceCalculatorService` di `adjustment()`
   - Ganti block hardcoded multiplier dengan panggilan `calculateManual()`
   - Tambah update `lm`, `lm_calc`

3. `app/Modules/Supervisor/Attendance/Models/SupervisorAttendance.php`
   - Tambah `'lm_calc'` ke `$fillable`

## Risiko

- Data `lembur_calc` yang udah ada mungkin beda setelah refactor → wajar, karena aturan jadi konsisten
- Kalau `OvertimeRule` kosong, fallback ke hardcoded yang sama seperti sebelumnya → aman
