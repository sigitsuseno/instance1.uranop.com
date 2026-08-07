intinya perubahan di payroll ini adalah menambahkan 2 mode perhitungan.

- Sebelum end_date periode terpilih
- sesudah end_date periode terpilih

1. Sebelum end_date
    - preprare = ambil data karyawan dari att_prepare berdasarkan range start_date dan end_date periode yang terpilih filter karyawan yang punya sch_employee_shift_roster dan bukan GRP-JKT

    - idNo = prepare->employee->employee_code
    - namaKaryawan = prepare->employee->name
    - gender (L/P) = prepare->employee->gender
    - bagian = prepare->employee->department->name
    - jabatan = prepare->employee->position->name
    - tahunMasuk = prepare->employee->join_date
    - tunjangan = prepare->employee->
    - lemburJam =
    - tj_mk = prepare->employee-> (di model employee ada function call untuk tunjangan masa kerja)
    - hari_kerja (HK) = hitung hari dari start_date sampai now(), dikurangi hari minggu + holiday
    - lm = filter berdasarkan group [GRP-ALLIN, GRP-GD] set 0, yang lain = prepare->sum(lm) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - lmCount (tidak ada di tabel UI) = filter berdasarkan group [GRP-ALLIN, GRP-GD] set 0, yang lain = prepare->sum(lm) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - overtime (tidak ada di tabel UI)= filter berdasarkan group [GRP-ALLIN, GRP-SPR, GRP-GD] set 0, yang lain = prepare->sum(overtime) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - overtime_count (tidak ada di tabel UI) = filter berdasarkan group [GRP-ALLIN, GRP-SPR, GRP-GD] set 0, yang lain = prepare->sum(overtime_count) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - lbrJam (LBR JAM) = lmCount + overtime_count,
    - uangLembur ()

2. setelah end_date
