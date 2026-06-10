# Changelog — Overtime Rules & LM Multiplier Fix

**Tanggal:** 2026-06-10

## Ringkasan

Finalisasi aturan overtime_rules dan perbaikan rumus `lm_count` sesuai kaidah:
- Minggu/Holiday: SEMUA jam kerja = lembur, potong 1 jam istirahat, lalu ×2
- Sabtu + Holiday: progressive multiplier (×2→×3→×4)

## Perubahan

### 1. Kalkulator — `lm_count` formula fix
- **File:** `app/Modules/Attendance/Services/AttendanceCalculatorService.php`
- `calculateLmMultiplier()`: sekarang potong 60 menit istirahat **sebelum** multiplier
- `calculateLmMultiplierFallback()`: hapus double-deduct, langsung `total_jam × 2`
- Rumus final: `lm_count = (lm_dalam_menit - 60) × 2` (via DB rule atau fallback)

### 2. Kolom `is_saturday` di `overtime_rules`
- **Migration:** `2026_06_10_100000_add_is_saturday_to_overtime_rules.php`
- **Model:** `OvertimeRule.php` — tambah cast `boolean`
- Fungsi: bedakan aturan LM Minggu/Holiday biasa vs Sabtu+Holiday

### 3. 3 Aturan Overtime di Database

| Code | is_holiday | is_saturday | Multiplier |
|---|---|---|---|
| OVT-REG | false | false | Jam 1: 1.5x, Jam 2+: 2.0x |
| OVT-LM | true | false | Jam 1-7: 2.0x |
| OVT-LM-S | true | true | Jam 1-5: 2.0x, Jam 6: 3.0x, Jam 7: 4.0x |

### 4. Seeder
- **File:** `database/seeders/OvertimeRuleSeeder.php`
- Disinkronkan dengan data existing di database
- Tambah rule OVT-LM-S dengan `is_saturday=true`

### 5. Kalkulator — thread `isSaturday`
- `calculate()` → `calculateLmMultiplier()` → `multiplyFromRule()`
- `multiplyFromRule()`: query filter `is_saturday` untuk resolve rule yang tepat
- Sabtu + Holiday otomatis resolve ke OVT-LM-S

## Contoh Hasil

```
8 jam LM (Minggu):   (480-60) × 2.0 = 840 menit = 14 jam upah
8 jam LM (Sabtu+H):  (480-60) → OVT-LM-S = 1020 menit = 17 jam upah
5 jam OT (biasa):    300 × (60×1.5 + 240×2.0) = 570 menit
```
