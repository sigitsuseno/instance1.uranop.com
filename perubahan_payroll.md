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
    - tj_mk = prepare->employee-> (di model employee ada function call untuk tunjangan masa kerja)
    - hari_kerja (HK) = hitung hari dari start_date sampai now(), dikurangi hari minggu + holiday
    -

2. setelah end_date
