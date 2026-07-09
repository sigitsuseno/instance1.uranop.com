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
