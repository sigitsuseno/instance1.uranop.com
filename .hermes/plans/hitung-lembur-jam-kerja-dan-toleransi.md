# Plan: Hitung Lembur — Jam Kerja + Toleransi Dinamis

**Tanggal**: 2026-06-26  
**Inisiator**: Sigit  
**Status**: In Progress

## Ringkasan

Dua perubahan:
1. **Jam kerja default FIXED**: 480 → 540 menit (8 kerja + 1 istirahat = 9 jam)
2. **Toleransi rounding**: 5 → 10 menit, dimasukin ke modal Setting (dinamis)

## Perubahan File

### Phase 1: Backend — AttendanceCalculatorService.php

1. **`calculateRawOvertime()`** — default `$workHoursConfig`:
   - `FIXED.weekday`: 480 → **540**
   
2. **`roundUp()`** — support override dari PayrollConfig:
   - Tambah parameter `?int $thresholdOverride = null, ?int $intervalOverride = null`
   - Kalau ada override, pakai itu; kalau nggak, fallback ke OvertimeCalculatorConfig

3. **`calculateRawOvertime()`** — panggil `roundUp()` dengan nilai dari settingConfig:
   - Baca `$settingConfig['rounding_threshold']` dan `$settingConfig['rounding_interval']`
   - Oper ke `roundUp()`

4. **Bugfix SHIFT**: tambah fallback ke OvertimeCalculatorConfig (saat ini langsung hardcode)

### Phase 2: Frontend — Index.vue (modal Setting)

1. **`settingForm`** — tambah fields:
   - `rounding_threshold`: default 10
   - `rounding_interval`: default 30

2. **`fetchConfig()`** — baca dari PayrollConfig:
   - `rounding_threshold` dari `conf.rounding_threshold ?? 10`
   - `rounding_interval` dari `conf.rounding_interval ?? 30`

3. **Modal UI** — tambah section "Pembulatan":
   - Input rounding_interval (interval, menit)
   - Input rounding_threshold (toleransi, menit)

4. **`settingForm.hours_fixed_wd`** — default 480 → 540

5. **`saveConfig()`** — include rounding values

### Phase 3: Verifikasi

- Hitung Lembur untuk periode terbaru
- Cek FIXED employee weekday: deduction harus 540
- Cek FLEX-SHIFT employee weekday: deduction tetap 480
- Cek rounding dengan threshold 10: 19→0, 20→30, 50→60

## Tidak Berubah

- SHIFT pattern (satpam): weekday 480, Sabtu 360
- FLEX-SHIFT: weekday 480, Sabtu 360
- FIXED Sabtu: 360
- Logic LM/OT split, multiplier, late calculation
