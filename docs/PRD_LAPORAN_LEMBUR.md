# PRD: Laporan Lembur — Porting dari hris-system ke instance1

**Tanggal:** 2026-06-09
**Module:** Reports > Lembur
**Sumber Riset:** `hris.uranop.com` (Controller: LaporanLemburController, Export: LemburHarianExport, LemburBulananExport)

---

## 1. Struktur Menu & Navigasi

```
Laporan
├── Laporan Absensi        (mock)
├── Laporan Gaji            (mock)
├── Laporan Pajak           (mock)
├── Laporan BPJS            (mock)
├── Laporan Cuti            (mock)
├── Laporan THR             (mock)
├── Laporan Uang Makan      (udah ada — UangMakan/Index.vue)
└── Laporan Lembur          ← BARU, submenu sendiri
    ├── Tab Harian          ← /admin/reports/lembur/harian
    └── Tab Bulanan         ← /admin/reports/lembur/bulanan
```

**Navigasi:** Satu halaman dengan 2 tab (Harian | Bulanan), mirip pattern `Admin/Reports/UangMakan/Index.vue`.

---

## 2. Struktur Data Source (instance1)

**Sumber utama:** `att_prepares` — bukan `pay_records`.

| Old Table | New Table | Keterangan |
|-----------|-----------|------------|
| `attendance_prepares` | `att_prepares` | Sumber UTAMA — data kehadiran + lembur per hari |
| `payrolls` | `pay_records` | Hanya untuk ambil gaji/harga per jam |
| `employees` | `employees` | Master karyawan |
| `sch_employee_shift_rosters` | `sch_employee_shift_rosters` | Filter: hanya karyawan yang punya roster |
| `employee_groups` | `employee_groups` | Filter: grouping per checkbox |

**⚠️ Status `sch_employee_shift_rosters`:** Saat ini KOSONG (blum ada data roster). Tabel & relasi udah siap, tinggal nunggu data dari import roster.

**Mapping kolom penting:**
| Old (hris-system) | New (instance1) |
|---|---|
| `attendance_prepares.holiday_overtime_duration` | `att_prepares.lm` (menit) |
| `attendance_prepares.calculated_overtime` | `att_prepares.overtime` (menit) |
| `attendance_prepares.shift_code` | join ke `sch_shifts.code` via `att_prepares` shift_id |
| `payrolls.base_salary` | `pay_records.gaji_pokok` atau `employee_salaries.base_salary` |
| `payrolls.total_overtime_minutes` | `att_prepares.overtime` (aggregate per periode) |
| `payrolls.overtime_earnings` | `pay_records.upah_lembur`

---

## 3. API Endpoints (Backend)

### Module: `app/Modules/Reports/Controllers/Api/V1/LaporanLemburController.php`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/lembur/harian?date=YYYY-MM-DD` | Data harian |
| GET | `/api/v1/reports/lembur/harian/export?date=YYYY-MM-DD` | Export Excel harian |
| GET | `/api/v1/reports/lembur/bulanan?year=YYYY` | Data bulanan |
| GET | `/api/v1/reports/lembur/bulanan/export?year=YYYY` | Export Excel bulanan |

### Route File: `app/Modules/Reports/Routes/api.php`

```php
Route::prefix('v1/reports/lembur')->middleware('auth:sanctum')->group(function () {
    Route::get('/harian', [LaporanLemburController::class, 'harian']);
    Route::get('/harian/export', [LaporanLemburController::class, 'exportHarian']);
    Route::get('/bulanan', [LaporanLemburController::class, 'bulanan']);
    Route::get('/bulanan/export', [LaporanLemburController::class, 'exportBulanan']);
});
```

### `harian()` Logic (REVISED — sumber: att_prepares):

```
1. Parse date → ambil att_prepares WHERE date = $date, group by employee_id
2. Ambil karyawan yang punya roster + group terpilih:
   Employee::whereHas('shiftRosters', fn($q) => $q->whereDate('date', $date))
       ->whereHas('groups', fn($q) => $q->whereIn('reference_code', $selectedGroups))
       ->with(['activeSalary', 'position'])
3. Per karyawan:
   a. Ambil att_prepare untuk employee+date tersebut
   b. Ambil gaji dari pay_records (periode aktif yang mencakup date)
      atau fallback ke employee.activeSalary()
   c. Hitung:
      - gaji = pay_record.gaji_pokok ?? activeSalary.base_salary
      - tjMk = pay_record.tj_masa_kerja ?? activeSalary.manual_earnings
      - tunjangan = pay_record.tunjangan
      - upahPerHari = (gaji + tjMk + tunjangan) / 25
      - hourlyRate = (gaji + tjMk + tunjangan) / 173
      - lemburMinggu = att_prepare.lm (sudah menit) → jam = lm / 60
      - lembur = att_prepare.overtime (sudah menit) → jam = overtime / 60
      - uangLembur = hourlyRate × (lm + overtime) / 60
   d. shift_kode: join ke sch_shifts via att_prepare.shift_id?
      Atau ambil dari att_prepare.schedule_in/schedule_out untuk inferensi
   e. Return item lengkap
```

### `bulanan()` Logic (REVISED — sumber: att_prepares):

```
1. Ambil semua att_prepares WHERE year(date) = $year
2. Group by employee_id + month(date)
3. Ambil karyawan yang punya roster di tahun tersebut + group terpilih:
   Employee::whereHas('shiftRosters', fn($q) => $q->whereYear('date', $year))
       ->whereHas('groups', fn($q) => $q->whereIn('reference_code', $selectedGroups))
       ->with(['activeSalary'])
4. Per karyawan:
   a. Ambil gaji dari pay_records (terbaru di tahun tersebut)
      atau fallback ke activeSalary
   b. Untuk setiap bulan (1-12):
      - Aggregate dari att_prepares:
        * lm = sum(lm) → menit
        * overtime = sum(overtime) → menit
        * lm_count = sum(lm_count) → menit (hasil multiplier)
        * overtime_count = sum(overtime_count) → menit (hasil multiplier)
      - hourlyRate = (gaji + tjMk + tunjangan) / 173
      - overtimePay = hourlyRate × (lm_count + overtime_count) / 60
      - months[m] = { hourlyRate, hours: lm, calculated: overtime, overtimePay }
   c. Return item
```

---

## 4. Frontend (Vue SPA)

### Struktur File

```
resources/js/Pages/Admin/Reports/Lembur/
├── Index.vue           ← Halaman utama dengan 2 tab
├── TabHarian.vue       ← Tabel laporan harian
└── TabBulanan.vue      ← Tabel laporan bulanan (matrix)
```

### Halaman Utama (`Index.vue`)

Pattern: mirip `Admin/Reports/UangMakan/Index.vue`
- Header dengan judul "Laporan Lembur"
- Dua tab: "Harian" | "Bulanan"
- Router: `{ name: 'reports.lembur', path: '/admin/reports/lembur' }`

### Tab Harian (`TabHarian.vue`)

**Filter:**
- Tanggal (date picker, default: hari ini)
- Checkbox group: Jakarta (GRP-JKT) / All In (GRP-ALLIN, dll) / Contract/Others
  - Pattern: gunakan `employee_groups` reference_code untuk filter

**Tabel (mirip old system):**
```
No | Nama | Bagian/Jabatan | L/P | Tj.MK | Tunjangan | Upah/Hari | Upah Lembur/Jam | [Tanggal: Kode | H/A | L/M | Lembur | Nominal]
```

**Tombol:**
- Export Excel → download `/api/v1/reports/lembur/harian/export?date=...`
- Print → window.open ke halaman print (opsional — bisa phase 2)

### Tab Bulanan (`TabBulanan.vue`)

**Filter:**
- Tahun (select: 2024, 2025, 2026, ...)
- Checkbox group: sama seperti Harian

**Tabel Matrix:**
```
No | Nama | Gaji Pokok | Tj.MK | Tunjangan | Jan | Feb | Mar | ... | Des
```
Tiap cell bulan berisi:
```
Upah/jam: xxx
Lembur: xx / xx
Uang Lbr: xxx
```

**Tombol:**
- Export Excel → download
- Print → window.open (opsional — phase 2)

### Komponen yang Digunakan (existing)

- `BaseCard`, `BaseButton`, `BaseModal`
- `TextInput`, `SelectInput`
- `DataTable` atau tabel HTML custom (karena matrix layout spesial)
- `useApi()` composable — `import { useApi } from '@/composables/useApi'`
- `useNotificationStore` untuk toast

---

## 5. Group Filter Logic (Updated)

Bukan hardcode JKT/ALLIN/CONTRACT. Gunakan **semua group** dari master "Imported Shift/Group":

| Kode | Nama | 
|------|------|
| GRP-JKT | JKT |
| GRP-ALLIN | ALLIN |
| GRP-PS1 | PS1 |
| GRP-GD | GD |
| GRP-SS | SS |
| GRP-SPR | SOPIR |

**Frontend:** Checkbox per group, semua checked default. User uncheck yang tidak ingin dilihat.

**Backend:** Filter employee via:
```php
Employee::whereHas('shiftRosters') // hanya karyawan dengan roster
    ->whereHas('groups', fn($q) => $q->whereIn('reference_code', $selectedGroups))
```

**Penjelasan:** Kenapa `sch_employee_shift_rosters`? Karena laporan lembur hanya relevan untuk karyawan yang punya jadwal shift/roster. Karyawan tanpa roster (misal: staff harian lepas) tidak perlu muncul.

---

## 6. Split Payroll Handling

Periode lintas bulan/tahun (is_split=true) punya 2 pay_records:
- **Part 1 (segment A)**: bulan pertama
- **Part 2 (segment B)**: bulan kedua

**Harian:** pilih part berdasarkan tanggal:
```php
$day = Carbon::parse($date)->day;
$part = ($day >= 25 && $day <= 31) ? 1 : 2; // asumsi cut-off 25
$payRecord = $payRecords->where('segment', $part === 1 ? 'A' : 'B')->first();
```

**Bulanan:** aggregate kedua part:
```php
$overtimePay = $records->sum('upah_lembur');
$calculated = $records->sum('lembur_count') / 60;
$lm = $records->sum('lm_count');
// hourly rate dari part 2 (gaji baru)
```

---

## 7. Checklist Implementasi

### Phase 1 — Core (Backend + Frontend Dasar)

- [ ] Buat `LaporanLemburController` di `app/Modules/Reports/Controllers/Api/V1/`
- [ ] Buat route di `app/Modules/Reports/Routes/api.php`
- [ ] Register route auto-load di `bootstrap/app.php` (kalau belum)
- [ ] Buat `Reports/Index.vue` halaman induk dengan 2 tab
- [ ] Buat `Reports/Lembur/TabHarian.vue` — tabel + filter + fetch API
- [ ] Buat `Reports/Lembur/TabBulanan.vue` — matrix tabel + filter + fetch API
- [ ] Daftarin route Vue Router: `/admin/reports/lembur`
- [ ] Tambah menu "Laporan Lembur" di Admin Sidebar
- [ ] `npm run build` + test

### Phase 2 — Export & Print

- [ ] Buat Export Excel Harian (pakai PhpSpreadsheet atau library ringan)
- [ ] Buat Export Excel Bulanan
- [ ] Tambah tombol Print (opsional — bisa re-use export)

### Phase 3 — Polish

- [ ] Group filter real dari DB (bukan hardcode)
- [ ] Loading state + empty state
- [ ] Error handling yang proper

---

## 8. Perbedaan Kunci Old vs New

| Aspek | Old (hris.uranop.com) | New (instance1) |
|-------|----------------------|-----------------|
| Framework | Inertia.js SSR | Vue 3 SPA (Vue Router) |
| Auth | `auth` + `branch.context` | `auth:sanctum` |
| Tenancy | `company_id` + `branch_id` | **None** |
| Data payroll | `payrolls` table | `pay_records` table |
| Data kehadiran | `attendance_prepares` | `att_prepares` |
| Excel export | Maatwebsite/Laravel Excel | PhpSpreadsheet direct / lightweight |
| Print | Blade view terpisah | Bisa re-use export atau window.print() |
| Group filter | Hardcode JKT/ALLIN/CONTRACT | Real query ke `employee_groups` |
| Frontend state | Inertia `router.get()` | `useApi().get()` |

---

## 9. Pertanyaan / Konfirmasi

1. **Print PDF**: Old system pake Blade view terpisah. Di instance1, mau tetep pake print halaman terpisah atau cukup export Excel aja? (A: Export Excel only dulu, B: Print + Export)

2. **Group filter**: Hardcode dulu sesuai old system (JKT/ALLIN/CONTRACT), atau langsung query real dari `employee_groups`?

3. **Data source LM**: Old system bedain `holiday_overtime_duration` (LM) dan `calculated_overtime` (lembur biasa). Di instance1 `att_prepares` punya `lm` dan `overtime`. Mapping ini udah bener?
