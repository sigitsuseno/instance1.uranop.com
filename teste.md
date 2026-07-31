cek jadwal disini sebenarnya simple aja sih..

1. halaman yang meiliki tabel list karyawan berdasarkan sch_employee_shift_rosters
2. tabelnya :

- NIP/PIN = roster->employee->nip
- Nama = roster->employee->name
- scan log = ambil data pertanggal di periode (pay_period) terpilih mulai dari jam 05.30 hingga 23.55 dari att_row_logs.
- wp = roster->work_pattern->code (editable dropdown work_patterns->code)
- kode = deteksi check_in, prosesnya check apakah hasil scan log itu ada di range roster->shift->check_in_start dan roster->shift->check_in_end. ini dropdown "P, S, ML, None" (editable dropdown shift->external kode)
- jadwal = jika kode = None tampilkan roster-shift->work_hour_start dengan warna merah. jika kode ada isinya tampilkan kode-nya.

Penting !!
ada fungsi update :

- bila dropdown work_pattern selected di ganti akan update ke tabel sch_employee_shift_roster->work_pattern_id
- bila dropdown kode di ganti di dropdown kode akan update ke tabel sch_employee_shift_roster->shift_code dan sch_employee_shift_roster->external_code (tabel shift berelasi belongTo work_pattern, jadi tiap work_pattern punya shift masing-masing)
- kalau kurang faham dan ada konflik tanyakan langsung

jo. ke menu kehadiran di sub hitung lembur,

detailnya gini jo,

Penting, payroll itu ada yang split pelajari split ini.

1. Model dan tabel

- supervisor_breakdown.
- di data lama menggunakan employee_salary_breakdown
- mapping dan perhitungan bisa lihat di /admin/payroll

2. Sumber Data

- data karyawan ambil karyawan yang ada di daftar supervisor_employee_group periode terpilih
- sumber data dari supervisor_att_snapshot
- pengambilan data dan perhitungan (kecuali lm dan lembur) lihat di /admin/payroll.

3. Flow dan Methot

- disini tolong ada 2 proses
  a. proses perhitungan seperti di /admin/payroll. dan
  b. proses ambil data dari excel (ini adalah proses overwrite jika ada filenya, prosesnya bisa di lihat dari data employee_salary_breakdown). jadi bila nanti tidak ada file, bisa mengikuti default perhitungan yang sudah ada

4. kalau kurang faham tanya ya, tapi jangan di timer

1. ya
1. kalau sebenarnya kalau supervisor_att_snapshot bisa di pakai jadi dasar pengolahan di supervisor_breakdown bisa pakai supervisor_att_snapshot, cuma harus kamu mapping kolom yang berar dan perlu perbaikan proses simpan snapshot dulu.
1. gini jadi bulan januari sampai juni kan sudah berjalan dan di kerjakan manual di excel. jadi biar datanya di aplikasi cocok sama yang di excel, maka ambil langsung aja dari excel. nah untuk bulan selanjutnya beda prosesnya.
1. untuk perhitungan lebih baik kamu lihat di /admin/payroll
1. ya (isGroupGaji() untuk /supervisor kan maksutmu ?)

Step 1 : submenu Karyawan.
Step 2 : submenu Group Karyawan
Step 3 : submenu Gaji Karyawan
Step 4 : submenu Kontrak Kerja
Step 5 : submenu Kompensasi
Step 6 : submenu Dokumen, submenu Keluarga & tanggungan, submenu riwayat pekerjaan, submenu resign & phk

yun, gini aja, aku ingin fronten applikasi menu Data Karyawan, Jadwal Kerja, Pengelolaan Kasbon, Pengelolaan cuti beserta semua submenu-nya. aku ingin kamu salin aja dari aplikasi instance1. data menggunakan data dummy dulu. yang di sesuaikan cuma perubahan dari vue spa web ke vue spa desktop,

gini jo, proses di AttendanceImportService dan AttendanceDataFixImport itu saat mengisi check_in, check_out, actual_in dan actual_out itu :

A. di AttendanceImportService

1. Data karyawan = ambil data karyawan dari sch_employee_shift_roster
2. Data holiday = abil data holiday dari sch_holiday
3. data cuti_sakit_izin = ambil data dari leave_request yang berelasi dengan leave_types
4. ambil att_prepare = ambil data dari attendance prepare untuk =
    - $check_in = att_prepare->check_in
    - $check_out = att_prepare->check_in
    - $lembur = att_prepare->overtime
5. ambil jadwal dari shift

- $schedul_in = sch_employee_shift_roster->shift->work_hour_start
- $schedul_out = sch_employee_shift_roster->shift->work_hour_end

6. mengisi check_in, check_out, actual_in dan actual_out dengan 3 kondisi berdasarkan sch_employee_shift_roster->work_pattern_type :
   6.1. jika sch_employee_shift_roster->work_pattern_type = FIXED,
   a. Hari minggu dan holliday
    - check_in = null - check_out = null - actual_in = null - actual_out = null - status = off
      b. cuti / izin / sakit
    - check_in = null - check_out = null - actual_in = null - actual_out = null - status = sesuai leave_type->code
      c. hari kerja - check_in = $check_in - check_out = $check_out - actual_in = $schedul_in - actual_out = $schedul_out
      d. lembur maksimal di hari kerja 3 jam,
      6.3. jika sch_employee_shift_roster->work_pattern_type = FLEX-SHIFT,
      a. Hari minggu dan holliday
    - check_in = null - check_out = null - actual_in = null - actual_out = null - status = off
      b. cuti / izin / sakit
    - check_in = null - check_out = null - actual_in = null - actual_out = null - status = sesuai leave_type->code
      c. hari kerja - check_in = $check_in - check_out = $check_out - actual_in = $schedul_in - actual_out = $schedul_out
      d. lembur maksimal di hari kerja 3 jam,
      6.1. jika sch_employee_shift_roster->work_pattern_type = SHIFT,
      a. sch_employee_shift_roster->external_code = "L" - check_in = null - check_out = null - actual_in = null - actual_out = null - status = off
      b. cuti / izin / sakit
    - check_in = null - check_out = null - actual_in = null - actual_out = null - status = sesuai leave_type->code
      c. sch_employee_shift_roster->external_code != "L"
      c.1 jika lembur === 4 - check_in = $check_in - check_out = $check_out - actual_in = $schedul_in - actual_out = $schedul_out

jo, buat tabel baru employee_overtime

kolomnya :

1. id
2. uuid
3. autolog_id
4. lembur,
5. lembur_hitung,
6. um_code
7. um_nominal
8. insentif
9. komponen (json)

gini aja, saat buka tombol update data itu memunculkan modal, isinya

1. dropdown pay_period,
2. field $empTanpaSabtuMingguHoliday (isinya id karyawan)
3. aturan spesifik berdasarkan jabatan (KABAG, KASHIF, ALLIN)
4. aturan spesifik teknisi.

$upahLemburPerJam = (gaji_pokok + tj_masa_kerja + tunjangan) / 173
Ambil data att_prepare di periode yang dipilih.
FILTER BERDASARKAN GROUP

1. GRP-JKT
   a. hari senin sampai jumat : jika overtime lebih dari 2 jam
    - employee_overtime->lembur = att_prepare->overtime
    - employee_overtime->lembur_hitung = 0
    - employee_overtime->um_code = UM
    - employee_overtime->nominal = aturan spesifik berdasarkan jabatan
    - employee_overtime->insentif = 0
    - employee_overtime->komponen = {status: att_prepare->status}
      b. hari sabtu
    - employee_overtime->lembur = att_prepare->overtime
    - employee_overtime->lembur_hitung = 0
    - employee_overtime->um_code = att_prepare->overtime >= 4 = FULL, att_prepare->overtime lebih dari >= 2 kurang dari <= 3.5 = 2(dua)
    - employee_overtime->nominal = aturan spesifik berdasarkan jabatan
    - employee_overtime->insentif = 0
    - employee_overtime->komponen = {status: att_prepare->status}
      c. hari minggu dan holiday.
      c1. jika $empTanpaSabtuMingguHoliday - employee_overtime->lembur = 0 - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = 0
        - employee_overtime->nominal = 0 - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
          c2. jika bukan $empTanpaSabtuMingguHoliday - employee_overtime->lembur = att_prepare->lm - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = att_prepare->lm >= 8 = FULL, att_prepare->overtime lebih dari >= 4 kurang dari <= 7.5 = 1/2 (HALF)
        - employee_overtime->nominal = aturan spesifik berdasarkan jabatan - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
2. GRP-ALLIN, GRP-GD
   a. hari senin sampai jumat : jika overtime lebih dari 2 jam
    - employee_overtime->lembur = att_prepare->overtime
    - employee_overtime->lembur_hitung = 0
    - employee_overtime->um_code = UM
    - employee_overtime->nominal = aturan spesifik berdasarkan jabatan
    - employee_overtime->insentif = 0
    - employee_overtime->komponen = {status: att_prepare->status}
      b. hari sabtu
    - employee_overtime->lembur = att_prepare->overtime
    - employee_overtime->lembur_hitung = 0
    - employee_overtime->um_code = att_prepare->overtime >= 4 = FULL, att_prepare->overtime lebih dari >= 2 kurang dari <= 3.5 = 2(dua)
    - employee_overtime->nominal = aturan spesifik berdasarkan jabatan
    - employee_overtime->insentif = 0
    - employee_overtime->komponen = {status: att_prepare->status}
      c. hari minggu dan holiday. - employee_overtime->lembur = att_prepare->lm - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = att_prepare->lm >= 8 = FULL, att_prepare->overtime lebih dari >= 4 kurang dari <= 7.5 = 1/2 (HALF)
        - employee_overtime->nominal = aturan spesifik berdasarkan jabatan - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}

3. GRP-SPR
   a. hari senin sampai sabtu - employee_overtime->lembur = 0 - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = ''
    - employee_overtime->nominal = 0 - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
      b. hari minggu, - employee_overtime->lembur = att_prepare->lm_count - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = ""
    - employee_overtime->nominal = lm_count \* $upahLemburPerJam - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
4. GRP-PS1, GRP-SS,
   a. hari senin sampai sabtu - employee_overtime->lembur = att_prepare->overtime - employee_overtime->lembur_hitung = att_prepare->overtime_count - employee_overtime->um_code = ""
    - employee*overtime->nominal = att_prepare->overtime_count * $upahLemburPerJam - employee*overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
      b. hari minggu, - employee_overtime->lembur = att_prepare->lm - employee_overtime->lembur_hitung = att_prepare->lm_count - employee_overtime->um_code = "" - employee_overtime->nominal = lm_count * $upahLemburPerJam - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}

**(dua group dibawah ini (KRY-TKN,) sifatnya overwrite jadi harus dikerjakan terakhir)** 5. KRY-TKN (ini punya aturan spesifik teknisi sendiri)
a. hari senin sampai jumat : jika overtime lebih dari 2 jam - employee_overtime->lembur = att_prepare->overtime - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = UM - employee_overtime->nominal = aturan spesifik teknisi senin - jumat - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
b. hari sabtu - employee_overtime->lembur = att_prepare->overtime - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = att_prepare->overtime >= 4 = FULL, att_prepare->overtime lebih dari >= 2 kurang dari <= 3.5 = 2(dua)

- employee_overtime->nominal = aturan spesifik teknisi sabtu - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
  c. hari minggu dan holiday. - employee_overtime->lembur = att_prepare->lm - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = att_prepare->lm >= 8 = FULL, att_prepare->overtime lebih dari >= 4 kurang dari <= 7.5 = 1/2 (HALF)
- employee_overtime->nominal = aturan spesifik teknisi minggu dan holiday - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}

6. KRY-SPC
   a. hari senin sampai sabtu - employee_overtime->lembur = att_prepare->overtime_count - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = ""
    - employee*overtime->nominal = att_prepare->overtime_count * $upahLemburPerJam - employee*overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}
      b. hari minggu, - employee_overtime->lembur = att_prepare->lm_count - employee_overtime->lembur_hitung = 0 - employee_overtime->um_code = "" - employee_overtime->nominal = lm_count * $upahLemburPerJam - employee_overtime->insentif = 0 - employee_overtime->komponen = {status: att_prepare->status}

masih salah itu, menghitung ada berapa jumlah uang makan dalam 1 periode, (contoh dalam satu periode di setiap hari senin sampai jumat lembur lebih dari 2 jam = 12 hari maka di um di tulis 12 hari)

1. UM = jam lembur setiap senin sampai jumat lebih dari 2 jam.
2. 2 = jam lembur setiap sabtu lebih dari 2 - 3 jam,
3. FULL (Sab) = jam lembur setiap sabtu lebih dari 4 jam,
4. 1/2 HK = jam lembur hari minggu/holiday lebih dari 4 jam
5. L (tolong ganti jadi FULL D) = jam lembur hari minggu/holida lebih dari 8 jam.

** yang dapat uang lembur tidak ada batasan minimal **

kemudian

1. GRP-JKT
   hanya dapat UANG MAKAN (kecuali yang 1 3 orang yang masuk KRY-SPC, dan 2 orang KRY-TKN)
2. GRP-ALLIN, GRP-GD
   hanya dapat UANG MAKAN (kecuali yang 1 1 orang yang masuk KRY-SPC, dan 1 orang KRY-TKN)
3. GRP-SPR
   dapat uang lembur dan insentif
4. GRP-PS1, GRP-SS,
   hanya dapat uang lembur
5. KRY-TKN
   uang makan hitungan khusus
6. KRY-SPC
   hanya dapat uang lembur
    1. Ambil supervisor_breakdowns yang dipilih
    2. Untuk setiap record:

        a. Tentukan Segmen
        - Normal → 1 segmen (null)
        - Split → 2 segmen (A & B), data attendance dihitung ulang langsung dari
          attendance_prepares per rentang segmen

        b. Skip non-group-gaji (karyawan tanpa GRP-\*)

        c. Rumus:
        Komponen: Gaji
        Formula: (gajiPokok / fixedDays) × hariKerja
        ────────────────────────────────────────
        Komponen: Upah Lembur
        Formula: ceil((gapok + TJMK + tunjangan) / 173 × totalLemburJam / 100) × 100
        ────────────────────────────────────────
        Komponen: Premi Hadir
        Formula: (premi / fixedDays) × hariKerja
        ────────────────────────────────────────
        Komponen: Revisi
        Formula: Seg-A: −TJMK / Seg-B: 0
        ────────────────────────────────────────
        Komponen: BPJS
        Formula: Seg-A: 0 / Seg-B: dari employee->bpjs
        ────────────────────────────────────────
        Komponen: Gaji Kotor
        Formula: gaji + TJMK + upahLembur + revisi + premiHadir + tunjangan
        ────────────────────────────────────────
        Komponen: Gaji Bersih
        Formula: ceil((gajiKotor − potongan) / 100) × 100
        ────────────────────────────────────────
        Komponen: PBLT
        Formula: selisih pembulatan ke atas

        d. Overtime Rules:
        | Group | Lembur? |
        |-----------------|-------------------------------------------|
        | GRP-ALLIN | ❌ Zero semua (LM=0, LBR=0) |
        | GRP-SPR | ⚠️ Hanya LM (LBR JAM = 0, upah = LM saja) |
        | GRP-GD, SS, PS1 | ✅ Full overtime (LM + LBR) |

        e. Simpan ke pay_records (via updateOrCreate)

tolong fokus di modul report, di /admin/reports/payroll
perubahan dalam mengambil data.

1. kolom CABANG ambil data dari employee->bank_cabang,
2. kolom TANGGAL TRANSAKSI ambil data dari pay_periode->tanggal_penggajian,
3. kolom KETERANGAN ambil data dari pay_record->notes
4. Kolom NOMINAL = ($gajiKus - $GajiAudit) + $uangMakan + $insentif

- $gajiKus = pay_records->gaji_bersih
- $gajiAudit = supervisor_breakdown->gaji_bersih
- $insentif = employee->overtime->sum(insentif).
- $uangMakan = employee_overtime->sum(nominal), hanya untuk Section A. KARYAWAN ALLIN dengan filter kecuali karyawan group GRP-SPR (grp spr tidak dapat uang makan)

Kalau kurang faham tolong tanyakan

jais 196.000
miftah 313.000
suyono 188.000

Perusahaan

- Menu edit profil management user
  -> Profile Perusahaan
    - Identitas Perusahaan
      -> Admin
    - Submenu managemen user

5. KEHADIRAN
   5.1 Import Kehadiran - Proses import data mentah absensi ke tabel log (yang ini kalau bisa selengkap mungkin)
   5.2 Manual Sync - Check scan kehadiran manual dan push ke att_prepare
   5.3 (dan submenu selanjutya)

Buat Tombol Kalibrasi Cuti,

- fungsinya menghitung ulang saldo cuti
- mengurutkan leave request perkayawan berdasarkan kolom sisa cuti per karyawan untuk cuti tahunan, (leave_request->sisa_cuti)

Caranya :
$cuti = Cari karyawan yang memiliki leave_request di leave_periods yang di pilih, urutkan berdasarkan tanggal terlama.

$jumlahCuti = $cuti->count(),
$ixd = urutanCuti
a. di tabel employee_leave

- employee_leave->transaction_type = decrement
- employee_leave->amount = 12 - $jumlah_cuti
  b. di tabel leave_request
- seperti kode dibawah

```php
foreach ($cuti as $index => $cutiSingle) {
    $cutiSingle->update([
        'sisa_cuti' => 12 - ($index + 1)
    ]);
}
```

 di /admin/payroll/slip di tombol cetak semua, itu tolong saat di klik muncul modal yang yang memfilter print berdasarkan GRP-\* . faham tidak ?

berarti overwrite total,

- ambil karyawannya yang punya sch_employee_shift_roster dan yang masuk di supervisor_employee_group di periode terpilih.
- filter berdasarkan work_patterns->type
  a. fixed & flex-shift
  a.1 hari senin - jumat jika roster->external_code === 'P' - check_in = roster->shift->work_hour_start + (randomMinutes (-10, 3)) - check_out = roster->shift->work_hour_start + lembur + (randomMinutes (10, -3)) - actual_in = roster->shift->work_hour_start - Actual_out = roster->shift->work_hour_end - lm = 0 - lembur = att_prepare->overtime (cap max 3 jam) - status = att_prepare->status -
  a.2 hari senin - jumat jika roster->external_code === 'S' - check_in = roster->shift->work_hour_start + lembur + (randomMinutes (-10, 3)) - check_out = roster->shift->work_hour_start + (randomMinutes (10, -3)) - actual_in = roster->shift->work_hour_start - Actual_out = roster->shift->work_hour_end - lm = 0 - lembur = att_prepare->overtime (cap max 3 jam) - status = att_prepare->status
  a.3 hari sabtu sama seperti hari senin-jumat, hanya lm dan lembur selalu 0.
  a.4 hari Minggu & holiday - check_in = '' - check_out = '' - actual_in = '' - Actual_out = '' - lm = 0 - lembur = 0 - status = selalu off

    c. shift full ambil dari att_prepare kecuali actual_in dan actual_out. jika di att_prepare kosong default ''. - check_in = att_prepare->check_in - check_out = roster->shift->work_hour_start + lembur + (randomMinutes (10, -3)) - actual_in = roster->shift->work_hour_start - Actual_out = roster->shift->work_hour_end - lm = att_prepare->lm - lembur = att_prepare->overtime - status = att_prepare->status

- setiap tanggal selalu ada record.
