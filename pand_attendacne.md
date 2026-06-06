kolom data tambah lm untuk data di tampilan saja, kemudian tabel hitungan tambah 1 kolom gaji,

HK = nilai fixed_work_day dari tabel setting, kalau pay_periode->is_split true = nilainya dari json seperti yang kita omongkan tadi.
hari_kerja = HK - deduct_day.

## hitungan split part 1 :

1. gaji = (gaji_pokok / HK) x (hari_kerja)
2. upah_lembur = ((gaji_pokok + tj_masa_kerja + tunjangan)/173) x (lm_count + lembur_count) -> dibulatkan keatas 100
3. premi_hadir = (premi / HK) x (hari_kerja)
4. revisi = tj_masa_kerja \* -1, (penyeimbang tj masa kerja karena sudah masuk part 2)
5. gaji_kotor = gaji + upah_lembur + premi_hadir + tunjangan

6. bpjs_tk = 0
7. bpjs_kes = 0
8. bpjs_pen = 0
9. pph = 0,
10. cashbon = 0,
11. pot_kehadiran = deduct_day \* (gaji_pokok / HK),
12. pblt = (pembulatan 100 dari (gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + pph + cashbon + pot_kehadiran))) - (gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + pph + cashbon + pot_kehadiran))
13. gaji_bersih = gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + pph + cashbon + pot_kehadiran) + pblt

## hitungan split part 2 :

1. gaji = (gaji_pokok / HK) x (hari_kerja)
2. upah_lembur = ((gaji_pokok + tj_masa_kerja + tunjangan)/173) x (lm_count + lembur_count) -> dibulatkan keatas 100
3. premi_hadir = (premi / HK) x (hari_kerja)
4. revisi = kosongkan,
5. gaji_kotor = gaji + upah_lembur + premi_hadir + tunjangan

6. bpjs_tk = employee_bpjs->bpjs_tk_karyawan ?? 0
7. bpjs_kes = employee_bpjs->bpjs_kes_karyawan ?? 0
8. bpjs_pen = employee_bpjs->bpjs_pensiun ?? 0
   (bpjs_tk + bpjs_kes + bpjs_pen) -> didapat dari pengelolaan bpjs belum kita buat,
9. pph = employee_pph->pph_bulanan ?? 0,
10. cashbon = 0,
11. pot_kehadiran = deduct_day \* (gaji_pokok / HK),
12. pblt = (pembulatan 100 dari (gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + pph + cashbon + pot_kehadiran))) - (gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + pph + cashbon + pot_kehadiran))
13. gaji_bersih = gaji_kotor - (bpjs_tk + bpjs_kes + bpjs_pen + pph + cashbon + pot_kehadiran) + pblt

## hitungan is_split = false

sama seperti hitungan split part 2.
