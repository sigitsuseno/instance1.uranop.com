# Plan: Konfigurasi Kalkulasi Lembur Dinamis

> Tanggal: 2026-06-18
> Status: In Progress

## Tujuan

Mengganti nilai hardcoded di `AttendanceCalculatorService` dengan tabel konfigurasi dinamis yang bisa dikelola via UI.

## Deliverables

1. Tabel `attendance_calculator_configs` — 1 global + optional override per work_pattern
2. Model `OvertimeCalculatorConfig`
3. API CRUD di `PayrollConfigApiController`
4. Tab UI "Konfigurasi Kalkulasi" di `Payroll/Configs/Index.vue`
5. `AttendanceCalculatorService` pakai config (backward compatible — fallback hardcoded jika config tidak ada)

---

## Phase 1: Database

### Tabel: `attendance_calculator_configs`

| Field | Type | Default | Keterangan |
|---|---|---|---|
| id | BIGINT PK | auto | |
| uuid | CHAR(36) UNIQUE | generated | |
| name | VARCHAR(100) | | Nama konfig |
| work_pattern_id | BIGINT NULL | | NULL = global, ID = override |
| normal_work_minutes | INT | 480 | Jam normal weekday |
| saturday_work_minutes | INT | 360 | Jam normal Sabtu |
| holiday_max_minutes | INT | 480 | Max OT holiday/Minggu |
| shift_saturday_flat | INT | 120 | Flat OT SHIFT Sabtu |
| late_deducts_overtime | BOOLEAN | false | Telat mengurangi lembur? |
| late_tolerance | INT | 0 | Toleransi telat (menit) |
| lm_rest_deduction | INT | 60 | Potongan istirahat LM |
| rounding_interval | INT | 30 | Interval pembulatan |
| rounding_threshold | INT | 5 | Threshold pembulatan |
| hourly_divisor | INT | 173 | Pembagi upah/jam |
| is_active | BOOLEAN | true | |
| description | TEXT NULL | | |
| created_by | FK users NULL | | |
| updated_by | FK users NULL | | |
| timestamps | | | |

### Seeder

1 row global (work_pattern_id = NULL) dengan nilai default.

---

## Phase 2: Model + API

### Model: `App\Modules\Settings\Models\OvertimeCalculatorConfig`

```php
class OvertimeCalculatorConfig extends Model
{
    protected $table = 'attendance_calculator_configs';
    protected $guarded = ['id'];
    
    // Static helper: lookup global/override
    public static function forPattern(?int $workPatternId): self
    {
        return static::where('is_active', true)
            ->where('work_pattern_id', $workPatternId)
            ->first()
            ?? static::where('is_active', true)
                ->whereNull('work_pattern_id')
                ->first()
            ?? new static(); // fallback: empty config → use hardcoded defaults
    }
}
```

### API Routes (tambah di `PayrollConfigApiController` / route baru)

```
GET    /api/v1/settings/payroll-configs/calculator        → list
POST   /api/v1/settings/payroll-configs/calculator        → create
PUT    /api/v1/settings/payroll-configs/calculator/{id}   → update
DELETE /api/v1/settings/payroll-configs/calculator/{id}   → delete
```

**Letakkan di controller yang sudah ada**: `PayrollConfigApiController` — method `indexCalculator`, `storeCalculator`, `updateCalculator`, `destroyCalculator`.

---

## Phase 3: Ubah AttendanceCalculatorService

### calculateRawOvertime()

Semua hardcoded value diganti panggil config via `OvertimeCalculatorConfig::forPattern($workPatternId)`.

```
480 → $config->holiday_max_minutes       (holiday/Minggu cap)
120 → $config->shift_saturday_flat       (SHIFT Sabtu)
480 → $config->normal_work_minutes       (FLEX-SHIFT S weekday deduction)
360 → $config->saturday_work_minutes     (FLEX-SHIFT S Sabtu deduction)
```

Tambahkan logic baru untuk `late_deducts_overtime` di FIXED & FLEX-SHIFT P.

### calculateLmMultiplier()

```
60 → $config->lm_rest_deduction
```

### roundUp()

```
30 → $config->rounding_interval
5  → $config->rounding_threshold
```

### Backward Compatibility

Kalau config tidak ditemukan (tabel kosong), gunakan nilai default yang sama dengan hardcoded sekarang.

---

## Phase 4: Frontend

### Tab Baru: `CalculatorConfigsTab.vue`

Mirip struktur `OvertimeRulesTab.vue`:
- List mode: tampil semua config (global + override)
- Form mode: create/edit
- Field: name, work_pattern_id (dropdown), semua parameter dalam input number

### Integrasi di `Configs/Index.vue`

Tambah tab ke-4: "Konfigurasi Kalkulasi" (key: `calculator`)

---

## Phase 5: Sidebar

Tidak perlu ubah — sudah ada "Gaji & LTHR" → `/admin/payroll/configs`. Tab baru muncul di halaman yang sama.

---

## Rumus Final

```
HOLIDAY/MINGGU:
  OT = min(check_out - check_in, holiday_max_minutes)
  → LM = OT (dipotong lm_rest_deduction, dikali multiplier)

HARI KERJA (late_deducts_overtime = FALSE):
  OT = check_out - schedule_out   (post-shift only)

HARI KERJA (late_deducts_overtime = TRUE):
  effective_late = max(0, late_minutes - late_tolerance)
  OT = (check_out - check_in) - normal_work_minutes - effective_late

FLEX-SHIFT S:
  late_minutes = 0  (hardcoded, tidak berubah)
  OT = max(0, total - normal_work_minutes)

SHIFT:
  Holiday: min(total, holiday_max_minutes)
  Sabtu:   shift_saturday_flat
  Lainnya: 0
```
