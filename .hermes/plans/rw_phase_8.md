# Phase 8: Payroll Engine + Supervisor Payroll + Web UI + Desktop

**File**: `rw_phase_8.md`
**Status**: Draft v3 — Web + Desktop included
**Prasyarat**: Phase 1-7 (semua modul sebelumnya)
**Prioritas**: ⚡ KRITIS — module terkompleks

---

## Tujuan

1. Payroll period management + bulk generate
2. Payroll calculation engine (admin + supervisor)
3. THR calculation
4. PPh 21 (TER / progressive)
5. BPJS calculation
6. Payslip generation (print + export)
7. Split period handling
8. Supervisor CSV import

---

## 1. STRUKTUR FOLDER & FILE

```
app/Modules/Payroll/
├── Models/
│   ├── PayrollPeriod.php
│   ├── PayrollRecord.php
│   ├── PayrollConfig.php
│   ├── PphConfig.php
│   ├── BpjsConfig.php
│   ├── ThrConfig.php
│   ├── TerRate.php
│   ├── PtkpRate.php
│   ├── ProgressiveRate.php
│   ├── ServiceYearAllowance.php
│   ├── SalaryGrade.php
│   └── SalaryGradeHistory.php
├── Services/
│   ├── PayrollCalculatorService.php
│   ├── OvertimePayService.php
│   ├── ThrCalculatorService.php
│   ├── PphCalculatorService.php
│   ├── BpjsCalculatorService.php
│   ├── PayslipService.php
│   └── SplitPeriodService.php
├── Controllers/Api/V1/
│   ├── PayrollPeriodController.php
│   ├── PayrollController.php
│   ├── ThrController.php
│   ├── PayslipController.php
│   └── PayrollExportController.php
├── Exports/
│   ├── PayrollExport.php
│   └── PayslipExport.php
└── Routes/api.php

app/Modules/Supervisor/
└── Payroll/
    ├── Models/
    │   └── SupervisorPayrollBreakdown.php
    ├── Services/
    │   ├── BreakdownCalculatorService.php
    │   ├── CsvImportService.php
    │   └── SupervisorPayslipService.php
    ├── Controllers/Api/V1/
    │   ├── BreakdownController.php
    │   ├── PayslipController.php
    │   └── PayrollExportController.php
    ├── Exports/
    │   └── SupervisorPayrollExport.php
    └── Routes/api.php
```

---

## 2. DATABASE

### 2.1 `payroll_periods` (rename from `pay_periods`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(100) | "Januari 2026" |
| code | varchar(20) | |
| year | int | |
| period_month | int | 1-12 |
| start_date | date | Tanggal mulai (biasanya 25) |
| end_date | date | Tanggal akhir (biasanya 24) |
| payment_date | date nullable | Tanggal pembayaran |
| is_split | tinyint(1) default 0 | Apakah periode split? |
| total_employees | int default 0 | |
| status | enum('draft','generated','reviewed','locked','closed') | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.2 `payroll_records` (rename from `pay_records`)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| payroll_period_id | bigint (FK→payroll_periods) | |
| employee_id | bigint (FK→employees) | |
| segment | varchar(5) nullable | A / B (split period) |
| gaji_pokok | decimal(15,2) | |
| tunjangan | decimal(15,2) default 0 | |
| tj_masa_kerja | decimal(15,2) default 0 | |
| premi | decimal(15,2) default 0 | |
| hk | int default 0 | Hari kerja |
| hari_kerja | int default 25 | Fixed work days |
| lm | int default 0 | LM (MENIT) |
| lm_count | float default 0 | LM (JAM) |
| lembur_count | float default 0 | Lembur (JAM) |
| gaji | decimal(15,2) default 0 | Gaji = (gapok/hari_kerja) × hk |
| upah_lembur | decimal(15,2) default 0 | |
| premi_hadir | decimal(15,2) default 0 | |
| revisi | decimal(15,2) default 0 | |
| gaji_kotor | decimal(15,2) default 0 | |
| bpjs_tk | decimal(15,2) default 0 | |
| bpjs_ks | decimal(15,2) default 0 | |
| bpjs_pen | decimal(15,2) default 0 | BPJS Pensiun |
| pph | decimal(15,2) default 0 | |
| cashbon | decimal(15,2) default 0 | Cicilan kasbon |
| pot_kehadiran | decimal(15,2) default 0 | Potongan absensi |
| pblt | decimal(15,2) default 0 | Pembulatan |
| gaji_bersih | decimal(15,2) default 0 | |
| remaining_leave | int default 0 | Sisa cuti |
| notes | text nullable | |
| status | varchar(20) default 'synced' | |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(payroll_period_id, employee_id, segment)`

### 2.3 `supervisor_payroll_breakdowns` (rename from `supervisor_breakdowns`)

Struktur sama dengan `payroll_records`, tapi untuk supervisor.

| Kolom tambahan | Type | Keterangan |
|-------|------|------------|
| employee_name | varchar(100) | Denormalized |
| department_name | varchar(100) | Denormalized |
| position_name | varchar(100) | Denormalized |
| gender | varchar(5) | Denormalized |
| join_date | date | Denormalized |
| group_codes | json nullable | Array kode group |

### 2.4 `thr_configs` (existing, perbaiki)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| min_months | int default 12 | |
| max_months | int default 36 | |
| is_prorated | tinyint(1) default 1 | |
| percentage | decimal(5,2) default 100 | |
| is_active | tinyint(1) default 1 | |

### 2.5 `employee_thr` (existing)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| thr_year | int | |
| thr_period_id | bigint nullable (FK→payroll_periods) | |
| gaji_pokok | decimal(15,2) | |
| tunjangan | decimal(15,2) default 0 | |
| tj_masa_kerja | decimal(15,2) default 0 | |
| basis_thr | decimal(15,2) | gapok + tunjangan + tjMK |
| work_months | int | Bulan kerja |
| is_full | tinyint(1) | Full atau prorate |
| amount | decimal(15,2) | Nilai THR final |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

Unique: `(employee_id, thr_year)`

### 2.6 `ter_rates` (PPh 21 TER — monthly)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| ptkp_type | varchar(10) | TK/0, TK/1, K/0, etc |
| min_income | decimal(15,2) | |
| max_income | decimal(15,2) | |
| rate | decimal(5,2) | Persentase |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.7 `ptkp_rates`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| type | varchar(10) | TK/0, TK/1, K/0, etc |
| value | decimal(15,2) | Nilai PTKP per tahun |
| effective_year | int | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.8 `progressive_rates` (PPh 21 progressive — yearly)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| min_income | decimal(15,2) | |
| max_income | decimal(15,2) | |
| rate | decimal(5,2) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.9 `service_year_allowances` (TJ Masa Kerja)

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| min_years | int | |
| max_years | int | |
| amount | decimal(15,2) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.10 `salary_grades`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| name | varchar(50) | |
| gaji_pokok_min | decimal(15,2) | |
| gaji_pokok_max | decimal(15,2) | |
| created_at | timestamp | |
| updated_at | timestamp | |

### 2.11 `salary_grade_histories`

| Kolom | Type | Keterangan |
|-------|------|------------|
| id | bigint (PK) | |
| employee_id | bigint (FK→employees) | |
| salary_grade_id | bigint (FK→salary_grades) | |
| effective_date | date | |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## 3. BUSINESS RULES (1:1 dari existing)

### 3.1 Formula Utama

```php
// Fixed days
$fixedDays = 25;

// Gaji per hari
$gajiPerHari = $gajiPokok / $fixedDays;
$gaji = round($gajiPerHari * $hariKerja, 2);

// Upah Lembur
$totalLemburJam = $lmCount + $lemburCount; // JAM
$upahLembur = ceil((($gajiPokok + $tjMasaKerja + $tunjangan) / 173) * $totalLemburJam / 100) * 100;

// Premi Hadir
$premiHadir = round(($premi / $fixedDays) * $hariKerja, 2);

// Gaji Kotor
$gajiKotor = $gaji + $tjMasaKerja + $upahLembur + $revisi + $premiHadir + $tunjangan;

// Potongan
$totalPotongan = $bpjsTk + $bpjsKs + $bpjsPen + $pph + $cashbon + $potKehadiran;

// PBLT (Pembulatan)
$beforeRounding = $gajiKotor - $totalPotongan;
$pblt = ceil($beforeRounding / 100) * 100 - $beforeRounding;

// Gaji Bersih
$gajiBersih = $beforeRounding + $pblt;
```

### 3.2 Split Period

```
Part A (tgl 25-31):
  - BPJS = 0 (TK, KS, Pensiun)
  - revisi = -TMK (negatif)
  - Overtime dari attendance_autologs segmen (bukan snapshot)
  - HK = hkSegment dari config

Part B (tgl 1-24):
  - Normal penuh
  - BPJS dari employee.bpjs
  - Overtime dari attendance_autologs segmen
  - HK = hkSegment dari config
```

### 3.3 Zero Overtime Rules

```
GRP-ALLIN & Section A (except GRP-SPR): upahLembur = 0
GRP-SPR: upahLembur hanya dari lm_count (tanpa lembur_count)
```

### 3.4 THR

```php
$basis = $gajiPokok + $tunjangan + $tjMasaKerja; // PREMI TIDAK MASUK

if ($workMonths >= 12) {
    $thr = $basis;                    // FULL
} else {
    $thr = (($basis / 12) / 30) * $workDays;  // PRORATE
}

$thrFinal = ceil($thr / 100) * 100;  // Pembulatan
```

### 3.5 BPJS

```php
// Dari employee.bpjs (atau employee_salary untuk rate)
$bpjsTk      = abs($employee->bpjs_tk_rate / 100 * $gajiPokok);
$bpjsKs      = abs($employee->bpjs_ks_rate / 100 * $gajiPokok);
$bpjsPen     = abs($employee->bpjs_pen_rate / 100 * $gajiPokok);

// Split Part A: semua BPJS = 0
```

### 3.6 PPh 21

```php
// Metode TER (monthly):
$gajiBulanan = $gajiKotor;
$terRate = TerRate::where('ptkp_type', $employee->ptkp)
    ->where('min_income', '<=', $gajiBulanan)
    ->where('max_income', '>=', $gajiBulanan)
    ->first();
$pph = $terRate->rate / 100 * $gajiBulanan;

// Metode Progressive (yearly):
$gajiTahunan = $gajiBulanan * 12;
$ptkp = PtkpRate::where('type', $employee->ptkp)->first()->value;
$pkp = max(0, $gajiTahunan - $ptkp);
$pphYearly = 0;
foreach (ProgressiveRate::all() as $bracket) {
    $bracketIncome = min(max(0, $pkp - $bracket->min_income), $bracket->max_income - $bracket->min_income);
    $pphYearly += $bracketIncome * ($bracket->rate / 100);
}
$pph = $pphYearly / 12;
```

### 3.7 TJ Masa Kerja

```php
$joinDate = Carbon::parse($employee->join_date);
$yearsOfService = $joinDate->diffInYears(Carbon::parse($period->end_date));
$allowance = ServiceYearAllowance::where('min_years', '<=', $yearsOfService)
    ->where('max_years', '>=', $yearsOfService)
    ->first();
$tjMasaKerja = $allowance ? $allowance->amount : 0;
```

### 3.8 Supervisor CSV Import (`cleanNum`)

```php
$cleanNum = function ($val) {
    if (empty($val)) return 0.0;
    if (is_numeric($val)) return (float) $val;
    $str = (string) $val;
    $str = str_replace(['Rp', ' ', "\xc2\xa0"], '', $str);
    $isNegative = false;
    if (str_starts_with($str, '(') && str_ends_with($str, ')')) {
        $str = substr($str, 1, -1); $isNegative = true;
    }
    if (strpos($str, ',') !== false) {
        $str = str_replace('.', '', $str);
        $str = str_replace(',', '.', $str);
    }
    $result = (float) $str;
    return $isNegative ? -$result : $result;
};
```

---

## 4. YANG HARUS DIPERBAIKI

### 4.1 Nama Tabel

- `pay_periods` → `payroll_periods`
- `pay_records` → `payroll_records`
- `supervisor_breakdowns` → `supervisor_payroll_breakdowns`
- **HAPUS** `supervisor_payrolls` (0 rows)

### 4.2 THR Config Tidak Dipakai

**Existing**: `ThrConfig::percentage` dan `is_prorated` cuma pajangan di DB, generate pakai hardcode.

**Perbaikan**: `ThrCalculatorService` baca dari `ThrConfig` model.

### 4.3 Naming `premi` vs `tunjangan`

**Existing**: Field DB `premi` tapi nilainya bisa "tunjangan" — naming kacau.

**Perbaikan**: `premi` = premi hadir (berdasarkan kehadiran). `tunjangan` = tunjangan tetap. Jangan dicampur.

### 4.4 Cashbon Auto-Deduct

**Existing**: Cashbon diinput manual di slip supervisor.

**Perbaikan**: `KasbonInstallment` yang `status=unpaid` auto-terpotong di payroll generate (jika `payroll_period_id` match).

---

## 5. TASK LIST

### Task 1: Migration
- [ ] Rename `pay_periods` → `payroll_periods`
- [ ] Rename `pay_records` → `payroll_records`
- [ ] Rename `supervisor_breakdowns` → `supervisor_payroll_breakdowns`
- [ ] Drop `supervisor_payrolls`
- [ ] `thr_configs`
- [ ] `employee_thr`
- [ ] `ter_rates`
- [ ] `ptkp_rates`
- [ ] `progressive_rates`
- [ ] `service_year_allowances`
- [ ] `salary_grades`
- [ ] `salary_grade_histories`

### Task 2: Models
- [ ] Semua model + `$fillable`, `$casts`, relasi

### Task 3: Services (PRIORITAS)

**`PayrollCalculatorService.php`**
- `calculate(periodId, employeeIds)` — kalkulasi per employee
- `calculateSingle(period, employee)` — formula utama
- `getOvertimeData(employeeId, startDate, endDate)` — dari autologs
- `getAttendanceSummary(employeeId, startDate, endDate)` — HK, absent, sick

**`OvertimePayService.php`**
- `calculate(gajiPokok, tjMK, tunjangan, lmCount, lemburCount)` → upahLembur
- `getApplicableOvertime(groupCode, section)` — zero overtime rules

**`ThrCalculatorService.php`**
- `generate(year, employeeIds)` — hitung THR untuk semua employee
- `calculateSingle(employee, year)` — formula THR per employee
- `getWorkMonths(employee, year)` — hitung masa kerja

**`PphCalculatorService.php`**
- `calculate(gajiKotor, ptkp, method)` → pph
- `terMethod(gajiKotor, ptkp)` — TER lookup
- `progressiveMethod(gajiKotor, ptkp)` — progressive bracket

**`BpjsCalculatorService.php`**
- `calculate(employee, gajiPokok)` → array [tk, ks, pen]
- `getRates(employee)` — dari config atau employee BPJS

**`PayslipService.php`**
- `generate(payrollRecord)` — HTML slip
- `print(payrollRecord)` — PDF

**`SplitPeriodService.php`**
- `getSegments(period)` — return Part A & B date ranges
- `getHkSegment(period, segment)` — HK per segmen
- `isSplit(period)` — cek apakah periode split

**`BreakdownCalculatorService.php`** (Supervisor)
- `calculate(periodId, segment)` — dari snapshot → breakdown
- `updateOrCreate` per employee+segment

**`CsvImportService.php`** (Supervisor)
- `resolveCsvPath(period, segment)` — mapping file CSV
- `import(path, period, segment)` — find→update, skip if not found
- `cleanNum(val)` — parser angka Indonesia

### Task 4: Controllers

**Admin:**
- `PayrollPeriodController.php` — index, store (single + bulk), update
- `PayrollController.php` — calculate, records, export, print
- `ThrController.php` — generate, index
- `PayslipController.php` — show

**Supervisor:**
- `BreakdownController.php` — index, calculate, import, export, print
- `PayslipController.php` — index, update, updateStatus

### Task 5: Routes

### Task 6: Test — HEAVY

- [ ] `PayrollCalculatorServiceTest.php` — formula, split period, edge cases
- [ ] `OvertimePayServiceTest.php` — jam→rupiah, zero overtime
- [ ] `ThrCalculatorServiceTest.php` — full vs prorate, basis
- [ ] `PphCalculatorServiceTest.php` — TER, progressive, ptkp lookup
- [ ] `BpjsCalculatorServiceTest.php` — split period BPJS=0
- [ ] `SplitPeriodServiceTest.php` — segment ranges, HK per segment

---

## 6. ENDPOINT SUMMARY

```
# Period (Admin)
GET    /api/v1/payroll/periods
POST   /api/v1/payroll/periods                   # single
POST   /api/v1/payroll/periods/generate           # bulk 12 bulan
PUT    /api/v1/payroll/periods/{id}
DELETE /api/v1/payroll/periods/{id}

# Calculate (Admin)
POST   /api/v1/payroll/calculate                  # trigger per period
GET    /api/v1/payroll/records?period_id=&segment=
GET    /api/v1/payroll/records/{id}

# THR (Admin)
POST   /api/v1/payroll/thr/generate               # body: { year }
GET    /api/v1/payroll/thr?year=

# Export & Print (Admin)
GET    /api/v1/payroll/export?period_id=&segment=
GET    /api/v1/payroll/print?period_id=&segment=
GET    /api/v1/payroll/payslip/{id}

# Supervisor Payroll
GET    /api/v1/supervisor/payroll?period_id=&segment=
POST   /api/v1/supervisor/payroll/calculate        # body: { period_id, segment }
POST   /api/v1/supervisor/payroll/import           # body: { period_id, segment }
GET    /api/v1/supervisor/payroll/export
GET    /api/v1/supervisor/payroll/print

# Supervisor Payslip
GET    /api/v1/supervisor/payroll/slip?period_id=
PUT    /api/v1/supervisor/payroll/slip/{id}        # update cashbon + notes
PATCH  /api/v1/supervisor/payroll/slip/{id}/status
```
