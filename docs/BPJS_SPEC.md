# Pengelolaan BPJS — Struktur & Spesifikasi

**Tanggal**: 2026-06-08
**Referensi**: Old system `hris.uranop.com` + diskusi dengan Sigit

---

## Menu Structure

```
Pengelolaan BPJS (Payroll / Employee module)
├── 1. Keanggotaan — data karyawan + status kepesertaan
│     Tabel: Nama | Nomor BPJS | ☐ BPJS TK | ☐ BPJS KES | ☐ BPJS PEN
│     plus tombol "Generate Iuran"
│
└── 2. Iuran BPJS — nominal rupiah per karyawan
      Tabel: Nama | Gaji Pokok | TJ MK | Tunjangan | Dasar BPJS |
             5 kolom Employer | 3 kolom Employee
```

---

## Database

### Tabel `bpjs_configs` (Settings module)

Konfigurasi persentase BPJS, versioned by effective_date.

| Kolom | Tipe | Default | Keterangan |
|-------|------|---------|------------|
| effective_date | date | — | Tanggal berlaku |
| is_active | boolean | true | Satu config aktif per waktu |
| jht_employer | decimal(5,2) | 3.70 | JHT perusahaan (%) |
| jht_employee | decimal(5,2) | 2.00 | JHT karyawan (%) |
| jkk | decimal(5,2) | 0.24 | JKK — perusahaan only |
| jkm | decimal(5,2) | 0.30 | JKM — perusahaan only |
| jp_employer | decimal(5,2) | 2.00 | JP perusahaan (%) |
| jp_employee | decimal(5,2) | 1.00 | JP karyawan (%) |
| kesehatan_employer | decimal(5,2) | 4.00 | BPJS Kes perusahaan (%) |
| kesehatan_employee | decimal(5,2) | 1.00 | BPJS Kes karyawan (%) |
| max_wage_cap | decimal(15,2) | 12jt | Batas maks upah |
| description | text | null | Keterangan |

### Tabel `employee_bpjs` (Employee module)

Data kepesertaan + iuran per karyawan.

#### FK + Status (Keanggotaan)
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| employee_id | FK unique | One employee = one BPJS record |
| pay_period_id | FK nullable | Diisi saat generate |
| bpjs_ketenagakerjaan_no | varchar(50) | Nomor BPJS TK |
| bpjs_kesehatan_no | varchar(50) | Nomor BPJS Kesehatan |
| has_bpjs_tk | boolean | ☐ Centang ikut TK |
| has_bpjs_ks | boolean | ☐ Centang ikut Kesehatan |
| has_bpjs_pen | boolean | ☐ Centang ikut Pensiun |

#### Dasar Kalkulasi
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| bpjs_base_type | enum(gaji_pokok,umk,custom) | Default: gaji_pokok |
| bpjs_base_salary | decimal(15,2) | Gaji dasar yang dipakai |
| tj_masa_kerja | decimal(15,2) | Tunjangan masa kerja |
| tunjangan | decimal(15,2) | Tunjangan tetap |

#### 5 Porsi Perusahaan (Employer)
| Kolom | Program | % Default | Checkbox |
|-------|---------|-----------|----------|
| employer_jht | JHT | 3.70% | TK |
| employer_jkk | JKK | 0.24% | TK |
| employer_jkm | JKM | 0.30% | TK |
| employer_kesehatan | BPJS Kes | 4.00% | KES |
| employer_jp | JP | 2.00% | PEN |

#### 3 Porsi Karyawan (Employee)
| Kolom | Program | % Default | Checkbox |
|-------|---------|-----------|----------|
| employee_jht | JHT | 2.00% | TK |
| employee_kesehatan | BPJS Kes | 1.00% | KES |
| employee_jp | JP | 1.00% | PEN |

#### Tambahan
| Kolom | Tipe | Keterangan |
|-------|------|------------|
| status_ketenagakerjaan | enum(active,inactive,suspended) | Default: active |
| status_kesehatan | enum(active,inactive,suspended) | Default: active |
| date_joined_ketenagakerjaan | date null | |
| date_joined_kesehatan | date null | |
| kesehatan_dependents | integer 0-5 | +1% per tanggungan |
| bpjs_kesehatan_class | enum(Kelas I,II,III) null | |
| faskes_tingkat_1 | varchar(100) null | |
| faskes_tingkat_1_code | varchar(20) null | |
| last_generated_at | timestamp null | |
| notes | text null | |
| created_by, updated_by | FK users | |
| timestamps, softDeletes | — | |

---

## Rumus Kalkulasi

```
bpjs_base = gaji_pokok + tj_masa_kerja + tunjangan
capped    = min(bpjs_base, max_wage_cap)

IF has_bpjs_tk:
  employer_jht       = capped × jht_employer / 100
  employee_jht       = capped × jht_employee / 100
  employer_jkk       = capped × jkk / 100
  employer_jkm       = capped × jkm / 100
ELSE → semua 0

IF has_bpjs_kes:
  employer_kesehatan = capped × kesehatan_employer / 100
  employee_kesehatan = capped × kesehatan_employee / 100
ELSE → 0

IF has_bpjs_pen:
  employer_jp        = capped × jp_employer / 100
  employee_jp        = capped × jp_employee / 100
ELSE → 0
```

## Modul Placement
- `BpjsConfig` → Settings module (existing)
- `EmployeeBpjs` → Employee module (existing)
- `BpjsCalculator` → Employee module (new service)
- `BpjsConfigController` → Settings module (API)
- `BpjsEmployeeController` → Employee module (API)

## Yang Sudah Ada di Instance1
- Migration `bpjs_configs` ✅ (perlu update nama kolom dari *_company_pct ke *_employer)
- Migration `employee_bpjs` ✅ (perlu rewrite — tambah tj_masa_kerja, tunjangan, 5 employer + 3 employee)
- Model `BpjsConfig` ⚠️ (masih skeleton)
- Model `EmployeeBpjs` ⚠️ (perlu update fillable)
- BpjsApiController ✅ (CRUD basic)
- Employee.bpjs() relation ✅
