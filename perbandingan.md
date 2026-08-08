# Perbandingan Perhitungan Payroll Gaji Karyawan

**Tanggal:** 2026-08-07
**Sumber sekarang:** `AttendanceApiController::recapApprove()` (generate pay_records) + `GajiKaryawanController`
**Sumber usulan:** rangkuman Sigit (mode "sebelum end_date", is_split = false)

---

## 1. Sumber Data & Filter Karyawan

| Aspek | Sekarang | Usulan Sigit | Status |
|---|---|---|---|
| Sumber kehadiran | `att_prepares` (whereBetween start–end periode) | `att_prepares` (range start–end periode) | 🟢 Sama |
| Filter karyawan | `isGroupGaji()` = whitelist 5 group: GRP-ALLIN, GRP-PS1, GRP-GD, GRP-SS, GRP-SPR | Punya `sch_employee_shift_roster` DAN **bukan GRP-JKT** (blacklist) | 🟡 Beda filosofi |
| Catatan GRP-JKT | GRP-JKT otomatis tidak lolos (tidak ada di whitelist) | Diexclude eksplisit | 🟡 Hasil akhir mirip, tapi whitelist juga menolak group lain (KRY-SPC, SG, dll) |

> ⚠️ GRP-JKT ada di DB (terverifikasi). Tapi `isGroupGaji()` = whitelist, bukan blacklist — beda perilaku untuk karyawan dengan group di luar 5 kode gaji.

---

## 2. Perbandingan Per Field

### A. Data Karyawan

| Field | Sekarang | Usulan Sigit | Status |
|---|---|---|---|
| idNo | `employee_code` | `employee_code` | 🟢 Sama |
| Nama | `employee->name` | `employee->name` | 🟢 Sama |
| L/P | `employee->gender` | `employee->gender` | 🟢 Sama |
| Bagian | `department->name` | `department->name` | 🟢 Sama |
| Jabatan | `position->name` | `position->name` | 🟢 Sama |
| Tahun Masuk | `join_date` | `join_date` | 🟢 Sama |
| gaji_pokok | `employee->gaji_pokok($month)` → `baseSalary()` | `employee->gaji_pokok()` | 🟢 Sama |
| tunjangan | `employee->tunjangan($month)` | `employee->tunjangan()` | 🟢 Sama |
| tj_mk | `employee->tunjangan_masa_kerja($month)` | `employee->tunjangan_masa_kerja()` | 🟢 Sama |

### B. Kehadiran & Lembur

| Field | Sekarang | Usulan Sigit | Status |
|---|---|---|---|
| **hari_kerja (HK)** | `fixed_working_day (25) - (unpaid + absent)` | **Hitung hari dari start_date sampai NOW(), minus Minggu + holiday** | 🔴 **KONFLIK BESAR** |
| lm | sum(`att_prepares.lm`) — GRP-ALLIN di-0-kan, GRP-SPR tetap | sum(`lm`), group **GRP-ALLIN & GRP-GD** di-0-kan | 🔴 Beda: usulan tambah GRP-GD = 0 |
| lm_count | sum(`att_prepares.lm_count`) — ALLIN 0, SPR tetap | sum(`lm_count`), **GRP-ALLIN & GRP-GD** di-0-kan | 🔴 Beda: usulan tambah GRP-GD = 0 |
| overtime (menit) | **Tidak disimpan** ke pay_records (hanya dipakai `overtime_count`) | sum(`overtime`), **GRP-ALLIN & GRP-SPR & GRP-GD** di-0-kan | 🟡 Pay_record tidak punya kolom `overtime` |
| overtime_count | sum(`att_prepares.overtime_count`) → disimpan sbg `lembur_count`; ALLIN & SPR di-0-kan | sum(`overtime_count`), **GRP-ALLIN & GRP-SPR & GRP-GD** di-0-kan | 🔴 Beda: usulan tambah GRP-GD = 0 |
| lbrJam (LBR JAM) | UI: `lembur_count / 60` (menit→jam) | `lmCount + overtime_count` (lalu ke jam) | 🟡 Beda komposisi |

> 🔴 **GRP-GD**: kode sekarang TIDAK men-0-kan lembur GRP-GD (dia section B, dihitung normal). Usulan Sigit memasukkan GRP-GD ke daftar yang di-0-kan untuk lm, lm_count, overtime, overtime_count.
> 🟡 **Satuan**: di att_prepares, `lm`, `lm_count`, `overtime`, `overtime_count` semuanya integer **MENIT** (terverifikasi dari data). Kode sekarang membagi 60 di `totalLemburJam = (lmCount + lemburCount) / 60`.

### C. Hitungan Gaji

| Field | Sekarang | Usulan Sigit | Status |
|---|---|---|---|
| **gaji (GAJI)** | `(gaji_pokok / 25) * hari_kerja` | `(gaji_pokok / **24**) * hari_kerja` | 🔴 **KONFLIK pembagi: 25 vs 24** |
| lemburPerJam | `(gaji_pokok + tj_mk + tunjangan) / 173` | `(gaji_pokok + tj_mk + tunjangan) / 173` | 🟢 Sama |
| uangLembur (LEMBUR) | `ceil((base/173) * jam / 100) * 100` | `lbrJam * lemburPerJam` (tanpa pembulatan 100) | 🟡 Beda: usulan tidak ada round-up 100 |
| revisi | 0 (non-split) / -tj_mk (split A) | 0 | 🟢 Sama utk non-split |
| tunjangan (TUNJANGAN) | `tunjangan` | `tunjangan` | 🟢 Sama |
| premi_hadir | `(premi / 25) * hari_kerja` | `(premi / 25) * hari_kerja` | 🟢 Sama (asumsi fixedDays=25) |
| **gaji_kotor (TOTAL)** | `gaji + tj_mk + upah_lembur + revisi + premi_hadir + tunjangan` | **`gaji + tunjangan + premi_hadir`** | 🔴 **KONFLIK BESAR** — usulan tidak menyertakan upah_lembur, tj_mk, revisi |
| pblt | `ceil((kotor - potongan)/100)*100 - selisih` | Pembulatan 100 (contoh 5.454.252 → 5.454.300) | 🟢 Sama konsep |
| **pph** | Dihitung `PphCalculationService` (PPh 21) | **0 — "ditanggung pemerintah, dikelola perusahaan"** | 🔴 **KONFLIK BESAR** |
| cashbon | 0 | 0 | 🟢 Sama |
| bpjs_tk | `employee_bpjs->bpjs_tk_karyawan` → `employee_jht` | `employee_bpjs->employee_jht` | 🟢 Sama (aksesor mapping sama) |
| bpjs_kes | `employee_bpjs->bpjs_kes_karyawan` → `employee_kesehatan` | `employee_bpjs->employee_kesehatan` | 🟢 Sama |
| bpjs_pen | `employee_bpjs->bpjs_pensiun` → `employee_jp` | `employee_bpjs->employee_jp` | 🟢 Sama |
| gaji_bersih (TERIMA) | `gaji_kotor - (bpjs_tk+kes+pen+pph+cashbon) + pblt` | `gaji_kotor - (bpjs_tk+kes+pen+kasbon) + pblt` | 🔴 Beda karena pph (0 vs dihitung) |

---

## 3. Rangkuman Konflik (🔴)

1. **hari_kerja**: sekarang = fixed 25 - (unpaid+absent). Usulan = hitung hari aktual start→NOW minus Minggu+holiday. Ini beda fundamental — butuh helper baru (belum ada di codebase; `sch_holidays` & `sch_working_calendars` ada tapi tanpa helper count working days).
2. **gaji**: pembagi 25 (sekarang, dari fixed_working_day) vs **24** (usulan). Hasil beda ±4%.
3. **gaji_kotor**: sekarang = gaji + tj_mk + upah_lembur + revisi + premi_hadir + tunjangan. Usulan = gaji + tunjangan + premi_hadir (tanpa lembur & tj_mk!). Perlu dipastikan: kalau LEMBUR & TJ MK tidak masuk TOTAL, kolomnya ditampilkan buat apa?
4. **pph**: sekarang dihitung PPh 21; usulan 0 (ditanggung pemerintah). Ini mengubah gaji_bersih.
5. **GRP-GD**: usulan men-0-kan lembur GRP-GD; sekarang tidak.
6. **GRP-JKT**: usulan blacklist GRP-JKT; sekarang whitelist 5 group gaji.

## 4. Perlu Klarifikasi (🟡)

- **Pembagi gaji 24 vs 25** — disengaja? Kalau gaji pakai /24 tapi premi pakai /25, hasil beda.
- **gaji_kotor tanpa upah_lembur** — serius? Kolom LEMBUR di UI jadi cuma info, tidak menambah TOTAL?
- **uangLembur** — tetap dibulatkan ke 100 seperti sekarang, atau murni `lbrJam * rate`?
- **pph = 0** berlaku hanya mode "sebelum end_date", atau dihapus total (mode sesudah juga)?
- **`overtime` (menit)** — usulan memakai field ini, tapi pay_records tidak punya kolomnya. Disimpan ke mana?
- **lbrJam** = lmCount + overtime_count — apakah ini yang ditampilkan di kolom LBR JAM, menggantikan lembur_count saja?
- **hari_kerja aktual**: batas "sampai NOW()" — kalau dibuka di tengah hari, karyawan yang belum check-out tetap dihitung hadir? Atau hitung sampai kemarin?

## 5. Yang Sudah Sama (🟢)

- Semua data karyawan (idNo, nama, gender, bagian, jabatan, tahun masuk)
- gaji_pokok / tunjangan / tj_mk — semua dari function call Employee
- lemburPerJam = (gapok+tjmk+tunjangan)/173
- premi_hadir = (premi/25) * hari_kerja
- revisi = 0 (non-split)
- bpjs_tk/kes/pen (employee_jht / employee_kesehatan / employee_jp — mapping aksesor sama persis)
- cashbon = 0
- pblt = pembulatan 100
