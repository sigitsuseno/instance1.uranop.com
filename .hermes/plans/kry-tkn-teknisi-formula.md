# Plan: KRY-TKN — Formula Teknisi di Lembur & Uang Makan

> Date: 2026-06-23 | Status: **Phase 0 — Confirmed**

## Overview

KRY-TKN adalah **flag group** (bukan section terpisah) — 3 karyawan teknisi tetap tampil di section GRP aslinya (Jakarta/AllIn), tapi pake formula u.makan khusus.

| Employee | GRP Section | Formula |
|----------|------------|---------|
| DARYANTO | A. JAKARTA | TKN |
| NUR KHOLIS | A. JAKARTA | TKN |
| YOHANES | B. ALL IN | TKN |

## Formula TKN

| Hari | Kondisi | Uang Makan |
|------|---------|-----------|
| Senin-Jumat | work_hour ≥ 11 | Flat **15.000** |
| Senin-Jumat | work_hour < 11 | 0 |
| Sabtu | per jam | lemburTotal × **(100.000 ÷ 7)** ≈ 14.286/jam |
| Minggu / Holiday | per jam | lemburTotal × **(200.000 ÷ 7)** ≈ 28.571/jam |

Ket: `work_hour` = (raw_lm + raw_overtime) ÷ 60 (dalam jam)

## Scope: Backend Only

**Frontend:** NO CHANGES. Tidak ada section baru, tidak ada filter baru.
**Backend:** 3 file disentuh.

---

## Phase 1 — TknHelper Class

**File baru:** `app/Modules/Reports/Helpers/Lembur/TknHelper.php`

- Type: `uang_makan`
- Group codes: `['KRY-TKN']`
- Label: `'TEKNISI'` (internal only, tidak dipakai di UI)
- Key: `'teknisi'`
- Method `matches()`: cek `$employee->groups->contains(ref_code === 'KRY-TKN')`
- Method `processEmployee()`: override dengan custom day processing
  - Untuk tiap hari:
    - Hitung `work_hour = (lm + overtime) / 60`
    - Weekday (Mon-Fri): jika work_hour ≥ 11 → nominal = 15000, kode = 'L', lm = '', lembur = 'TKN'
    - Sabtu: nominal = round(lemburTotal × (100000/7)), kode = 'L', lm = '', lembur = 'TKN'
    - Minggu/Holiday: nominal = round(lemburTotal × (200000/7)), kode = 'L', lm = '', lembur = 'TKN'
  - Upah/hari, HA, kode = sama kayak JakartaHelper

## Phase 2 — Controller Injection

**File:** `app/Modules/Reports/Controllers/Api/V1/LaporanLemburController.php`
**Method:** `buildCombinedDetailData()`

Di dalam loop `foreach ($employees as $employee)`:

1. **(NEW) Cek KRY-TKN dulu** — sebelum SPC check:
   ```
   if (TknHelper::matches($employee))
       → $item = $tknHelper->processEmployee(...)
       → push ke $jakartaEmployees atau $allInEmployees berdasarkan GRP
       → continue
   ```

2. Flow existing tetap: SPC → Jakarta → Printing → AllIn

**Method:** `buildCombinedResumeData()`

- Tidak perlu ubah — resume udah generic, dia loop `$detailResult['sections']` existing

## Phase 3 — Registration & Verify

1. `use` statement di controller untuk TknHelper
2. Instantiate `$tknHelper = new TknHelper()` bareng helper lainnya
3. Verify: run `php artisan route:list` — ga boleh ada error
4. Verify: cek logika classification — pastikan KRY-TKN karyawan masuk section yang benar

---

## Files Changed (3)

| File | Change |
|------|--------|
| `app/Modules/Reports/Helpers/Lembur/TknHelper.php` | **NEW** |
| `app/Modules/Reports/Controllers/Api/V1/LaporanLemburController.php` | **EDIT** — injection point |
