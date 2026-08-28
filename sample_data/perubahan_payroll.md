intinya perubahan di payroll ini adalah menambahkan 2 mode perhitungan.

- Sebelum end_date periode terpilih
- sesudah end_date periode terpilih

1. Sebelum end_date dan jika is_split = false,
    - preprare = ambil data karyawan dari att_prepare berdasarkan range start_date dan end_date periode yang terpilih filter karyawan yang punya sch_employee_shift_roster dan bukan GRP-JKT
    - idNo = prepare->employee->employee_code
    - namaKaryawan = prepare->employee->name
    - gender (L/P) = prepare->employee->gender
    - bagian = prepare->employee->department->name
    - jabatan = prepare->employee->position->name
    - tahunMasuk = prepare->employee->join_date
    - gaji_pokok = prepare->employee->(di model employee ada function call untuk gaji pokok)
    - tunjangan = prepare->employee-> (di model employee ada function call untuk tunjangan)
    - lemburPerJam = gaji_pokok + tj_mk + tunjangan / 173
    - tj_mk = prepare->employee-> (di model employee ada function call untuk tunjangan masa kerja)
    - hari_kerja (HK) = hitung hari dari start_date sampai now(), dikurangi hari minggu + holiday
    - lm = filter berdasarkan group [GRP-ALLIN, GRP-GD] set 0, yang lain = prepare->sum(lm) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - lmCount (tidak ada di tabel UI) = filter berdasarkan group [GRP-ALLIN, GRP-GD] set 0, yang lain = prepare->sum(lm) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - overtime (tidak ada di tabel UI)= filter berdasarkan group [GRP-ALLIN, GRP-SPR, GRP-GD] set 0, yang lain = prepare->sum(overtime) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - overtime_count (tidak ada di tabel UI) = filter berdasarkan group [GRP-ALLIN, GRP-SPR, GRP-GD] set 0, yang lain = prepare->sum(overtime_count) // lihat apakah menit atau jam, kalau menit buat menjadi jam,
    - lbrJam (LBR JAM) = lmCount + overtime_count,
    - gaji (GAJI) = (gaji_pokok / 24) \* hari_kerja
    - uangLembur (LEMBUR) = lbrJam \* lemburPerJam
    - revisi = 0
    - tjng (TUNJANGAN) = tunjangan
    - premi_hadir = (premi / 25 ) \* hari_kerja,
    - pblt (PBLT) = hitungan kalau di tambah ini gaji bersih jadi pembulatan seratus, contoh gaji bersih 5.454.252 + pblt = 5.454.300
    - gaji_kotor (GAJI) = gaji + tunjangan + premi_hadir
    - bpjs_tk (BPJS TK) = employee_bpjs->employee_jht
    - bpjs_kes (BPJS KES) = employee_bpjs->employee_kesehatan
    - bpjs_pen (BPJS PEN) = employee_bpjs->employee_jp
    - kasbon = 0
    - pph = 0 (pph disini tidak di munculkan karena di tanggung pemerintah dan di kelola oleh perusahaan)
    - gaji_bersih (TERIMA) = gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + kasbon)

2. Sebelum end_date dan jika is_split = true, untuk segmen A

jadi gambaran flownya seperti ini : HR klik menu Payrol submenu gaji karyawan yang akan membuka halaman gaji karyawan yang belum ada isinya (karena belum pilih periode) -> pilih
periode yang akan memunculkan tombol simpan tombol ini akan menyimpan data ke pay_record, jika now() sebelum atau sama dengan end_date, tabel perhitungan on_the_fly akan di tampilkan, jika now()
sesuah end_date makan akan muncul tombol finalisasi dengan fungsi finalisasi () yang akan menggitung dengan perhitungan sama (di hari_kerja hitungannya seperti hitungan lama yaitu fixed_days / 25
dikurangi leave yang tidak dibayar (izin tidak masuk) dan absent) dan merubah mode on_the_fly menjadi on_record (membaca dari pay_record), dan tombol lock yang akan membuat semua fungsi dan form CRUD
tabel pay_record tidak berfungsi, bisa unlock dengan password yang tersimpan di setting. faham tidak jo

1. dari fixed_working_day atau 25
2. gaji_kotor = gaji + tunjangan + premi_hadir + revisi
3. round-up ke kelipatan 100 (ceil(x/100)\*100)
4. GRP-GD ikut di-0-kan
5. isGroupGaji()
6. export tetap membaca database, nanti biar hr saya instruksikan untuk menyimpan dulu sebelum mengexport
7. sekarang untuk reverensi perhitungan aja. nanti kalau perhitungannya sudah klop dengan data hitungan fisik nanti kita hapus.
8. oh iya, hitung jumlah record per karyawan di kurangi minggu + holiday + status absent aja jo
9. ambil record dati tabel holiday di rentang start_date sampai end_date di periode terpilih.
10. pain text aja tapi di batasi yang bisa lihat hanya role superadmin
11. lock pay_record aja,
