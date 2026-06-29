gini, di DataFixImport ini ada 3 proses penting,

1. ambil jadwal (untuk jam berangkat dan jam pulang)
2. ambil lembur di tanggal tersebut
3. generate check_in dan check_out berdasarkan jadwal tersebut di sesuaikan dengan lemburnya.

**intinya ada di processRow()**
foreach ($dates as $colIndex => $date) 
        $status = trim($rowValues[$colIndex]); // Status dari kolom tanggal
$lemburRaw = trim($rowValues[$colIndex + 1]); // Lembur dari kolom sebelahnya

sebelum C. Penentuan Employee Type pecah dahulu karyawan berdasarkan group.

1. GRP-JKT
   yang ini langsung skip aja. karena ada service lain yang handle ini.

2. [GRP-ALLIN, GRP-PS1, GRP-SPR, GRP-SS, GRP-GD] (yang ini ambil dari excel)

- disini baru masuk ke C. Penentuan Employee Type, tapi disini saya mau benar-benar dibagi berdasarkan 3 pattern.
  A. FIXED - filter :
  a.1. minggu hanya tambahkan status 'off'.
  a.2. holiday hanya tambahkan status 'off'.
  a.3. work_day (senin - jumat) ambil lembur, ambil actual_in dan actual_out (jadwal), sesuaikan check_in dan check_out, berdasarkan lembur (selalu tambahkan lembur setelah actual_out).
  a.4. Sabtu ambil lembur, ambil actual_in dan actual_out (jadwal), sesuaikan check_in dan check_out, berdasarkan lembur (selalu tambahkan lembur setelah actual_out).
  a.5. cuti / sakit / izin, ada actual_in dan actual_out, status berdasarkan status dari excel
  B. FLEX-SHIFT - filter :
  b.1. minggu hanya tambahkan status 'off'.
  b.2. holiday hanya tambahkan status 'off'.
  b.3. work_day (senin - jumat) ambil lembur, ambil actual_in dan actual_out (jadwal), sesuaikan check_in dan check_out, berdasarkan lembur, Jika shift == 'P' (pagi) maka sesuaikan check_out, jika shift == 'S' (Siang), sesuaikan check_in (check_in - lembur), jadwal jam 15.00 - 23.00.
  b.4. Sabtu ambil lembur, ambil actual_in dan actual_out (jadwal), sesuaikan check_in dan check_out, berdasarkan lembur (selalu tambahkan lembur setelah actual_out).
  b.5. cuti / sakit / izin, ada actual_in dan actual_out, status berdasarkan status dari excel
  C. SHIFT (satpam)
  filter : - berdasarkan status dari excel,
  c.1. jika status == 'H',
  c.1.3. jika work_day (senin - jumat) tambah actual_in dan actual_out, dan check_in dan check_out seperti biasa.
  c.1.4. jika sabtu tambah actual_in dan actual_out, dan check_in dan check_out seperti biasa, tambahkan lembur.
  c.2. jika status == 'L',
  c.2.1. tambah actual_in dan actual_out, dan check_in dan check_out seperti biasa, tambahkan lembur.
  c.3. jika status == 'OFF',
  c.3.1. hanya tambahkan status 'off'.
