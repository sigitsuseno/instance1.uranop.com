# Import Attendance — Logic Baru (per 2026-07-01)

## Sumber Data

| Periode | Sumber Utama | Override |
|---------|-------------|----------|
| 1-6 (2026) | att_prepares → **XLSX overwrite** | Excel hardcoded |
| 7+ (2026) | att_prepares | Tidak ada |
| 2027+ | att_prepares | Tidak ada |

## Rule: Excel > att_prepares
- **Ada Excel** → data final = Excel (overwrite)
- **Tidak ada Excel** → data final = att_prepares

## Filter Group (6 group dari att_prepares)
GRP-JKT, GRP-ALLIN, GRP-SPR (supir), GRP-GD, GRP-SS (satpam), GRP-SP1

## Rules Per Hari (dari att_prepares)

### Senin-Jumat (Weekday)
| Field | Sumber |
|-------|--------|
| lm | att_prepares.lm |
| lembur | att_prepares.overtime |
| check_in | att_prepares.check_in |
| check_out | att_prepares.check_out |

### Sabtu
| Field | Sumber |
|-------|--------|
| lm | 0 |
| lembur | 0 |
| check_in | dari jadwal/roster (abaikan att_prepares) |
| check_out | dari jadwal/roster (abaikan att_prepares) |

### Minggu & Holiday
Semua null/0 (tidak ada lembur, tidak ada check_in/out)

### GRP-SS (Satpam) — Exception
Ambil SEMUA dari att_prepares apa adanya (termasuk Sabtu/Minggu/Holiday):
- lm, overtime, check_in, check_out → semua dari att_prepares

## Service Baru
Menggantikan:
- `SupervisorAttPrepareSync`
- `AttendanceOvertimeSyncService`

## Catatan
- Setelah import, **Perhitungan Lembur (adjustment)** tetap harus dijalankan untuk multiplier
- `lembur_calc` dan `lm_calc` dihitung oleh adjustment, bukan oleh import
