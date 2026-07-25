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

jo, buat tabel baru karyawan_titipan yang isi kolomnya : id, uuid, nama, employee_code, start_date, end_date, status
