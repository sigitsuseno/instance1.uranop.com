# Tab per group di Manual Sync — design

Tanggal: 24 Nov 2026
Lingkup: **hanya `kuskarangjati`**. `kusmajalengka2` tidak disentuh.

## Maksud

Halaman `/admin/attendance/manual-sync` menampilkan baris scan untuk **semua**
karyawan dalam rentang tanggal. HR ingin meninjau per kelompok kerja tanpa
membaca tabel campuran. Kelompok yang dipakai adalah group karyawan hasil
impor, yaitu semua master group dengan `group_label = 'Imported Shift/Group'`.

## Tujuan

- Pita tab di atas tabel: **Semua** + satu tab per group aktif pada label
  `Imported Shift/Group`, urut `code`, label tab `KODE · Nama`.
- Tab aktif **menyaring data di server** sehingga hitungan
  `Total … karyawan`, jumlah record, dan paginasi per karyawan ikut benar.
- Ganti tab → kembali ke halaman 1 lalu muat ulang bila data sudah pernah
  dimuat. Tab **Semua** = tanpa saringan (perilaku hari ini).
- Daftar tab dimuat sekali saat halaman dibuka; tidak ada tab yang dibuat
  manual dan tidak ada halaman pengaturan tab.

## Bukan tujuan

- **Push Prepare tidak diubah** — tetap memproses seluruh record dalam rentang.
- **Fetch Data tidak diubah** — tetap memindai seluruh roster; tab hanya
  menyaring baris yang dikembalikan sesudahnya.
- Tidak merombak gaya halaman ke kelas `ui-*`; tab memakai gaya pita tab yang
  sudah dipakai halaman Reports (`border-b-2`).
- Tidak menyentuh kusmajalengka2 dan tidak menambah fitur tab manual seperti
  di sana.

## Kendala

- Group tidak punya dimensi waktu: keanggotaan dibaca apa adanya, bukan per
  tanggal. Penyaring = `employee.groups.reference_code` ∈ kode tab.
- Endpoint daftar group yang sudah ada: `GET /api/v1/settings/employee-data/groups`
  (mengembalikan seluruh master; saring `group_label` + `is_active` di klien).
- API data: `GET /api/v1/attendance/manual-sync/data` dengan `mode=fetch|display`;
  penyaringan karyawan ada di `ManualSyncController::returnRecords()`.

## Pendekatan

- **A. Saring di server lewat `group_codes[]`** (dipilih). Sama dengan pola
  filter group di Roster dan halaman Reports: hitungan total dan paginasi
  akurat, karena `returnRecords()` memaginasi per karyawan sebelum mengambil
  baris.
- **B. Saring di klien atas `syncData` yang sudah dimuat.** Ditolak: paginasi
  per karyawan terjadi di server, jadi tab hanya akan menyaring sebagian
  karyawan halaman itu dan angka total berbohong.
- **C. Checkbox multi-group seperti Roster.** Ditolak untuk sekarang: pemilik
  produk meminta tab, dan tab satu-group-sekaligus lebih ringkas. Bisa
  ditambahkan kemudian tanpa membuang penyaring server.

## Keputusan yang sudah diambil

- **Tampilan tabel tidak berubah sama sekali** (kolom, radio jam scan, status,
  paginasi, tombol). Satu-satunya perubahan perilaku: karyawan yang tampil
  disaring menurut group tab aktif.
- Backend: `getData` menerima `group_codes` (`nullable|array|max:100`,
  `group_codes.*` string maks 50) dan meneruskannya ke `returnRecords()`;
  `empQuery` diberi
  `whereHas('employee.groups', fn ($q) => $q->whereIn('reference_code', $codes))`.
- Frontend: `groupTabs` diambil dari endpoint groups, disaring
  `group_label === 'Imported Shift/Group'` dan `is_active`, diurutkan per `code`.
  Tab pertama `Semua` (`code = null`).
- Pita tab tampil begitu daftar group dimuat (tidak menunggu Fetch Data);
  memilih tab sebelum data dimuat hanya mengubah pilihan, tanpa request.
- Memilih tab group mengirim satu `group_codes[]`; memilih **Semua** tidak
  mengirim apa pun.
- Bila label itu tidak punya group aktif, pita tab tidak dirender.

## Yang diserahkan ke pelaksana

- Susunan markup tab mengikuti pita tab halaman Reports yang sudah ada.
- Penamaan state/fungsi mengikuti gaya `ManualSync.vue` setempat.
- Test: feature test baru untuk `group_codes` (dua karyawan beda group →
  tab satu group hanya memuat karyawannya; tanpa `group_codes` memuat kedua).
