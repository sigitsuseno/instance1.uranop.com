# Plan: SupervisorAttPrepareSync Service (Updated)

## Goal
Sync `att_prepares` → `attendance_autologs` dengan filtering karyawan.

## Logic

### Filtering Karyawan per Tahun+Periode

| Tahun | Periode | Karyawan | Alasan |
|-------|---------|----------|--------|
| **2026** | 1-4 | **GRP-JKT only** | Non-JKT sudah ada dari XLSX manual (auditor) |
| **2026** | 5-12 | **All** | Murni dari att_prepares |
| **2027+** | 1-12 | **All** | Full system |

### Input
| Param | Type | Deskripsi |
|-------|------|-----------|
| `periodId` | int | PayPeriod ID (1=Jan...12=Des) |
| `startDate` | string | Y-m-d |
| `endDate` | string | Y-m-d |

### Proses
1. Tentukan tahun dari `startDate`
2. Jika tahun=2026 AND periodId in 1-4: query att_prepares + WHERE IN employee_id GRP-JKT
3. Selain itu: query att_prepares ALL employees
4. Join ke `sch_employee_shift_rosters` (LEFT JOIN)
5. UPSERT per record (sama kayak AttendanceImportFromPrepares)

## File
- **New**: `app/Modules/Supervisor/Attendance/Services/SupervisorAttPrepareSync.php`
