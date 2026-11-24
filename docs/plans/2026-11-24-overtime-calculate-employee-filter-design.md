# Filter karyawan & batas periode pada Calculate Overtime — design

Tanggal: 24 Nov 2026
Lingkup: **hanya `kuskarangjati`**. `kusmajalengka2` tidak disentuh.

## Maksud

Tombol **Calculate Overtime** di `/admin/attendance/overtime-calculation`
saat ini langsung memproses **semua** baris `att_prepares` yang tidak terkunci
dan punya scan masuk/keluar di rentang tanggal. HR ingin bisa memperbaiki atau
menghitung ulang **sebagian karyawan** saja, dan ingin rentang tanggal tidak
melewati batas satu periode gaji.

## Tujuan

- Klik Calculate Overtime → modal pilih karyawan → tombol **Mulai Kalkulasi
  Lembur** → hanya karyawan terpilih yang dihitung.
- Daftar di modal = **pemilik roster** di rentang tanggal itu
  (`sch_employee_shift_rosters`), bukan seluruh karyawan aktif.
- Dropdown **Periode** membatasi tanggal mulai/akhir agar tidak keluar dari
  satu periode gaji.

## Bukan tujuan

- Rumus kalkulasi tidak disentuh: `AttendanceCalculatorService` dan
  `AttendanceService::hitungLembur` tetap apa adanya.
- Tabel rekap, Export Excel, Export Roster, dan halaman Detail tidak berubah
  (tabel rekap tetap berbasis karyawan aktif seperti sekarang).
- Tidak ada kolom/migrasi baru, tidak ada tabel penampung pilihan.
- Tidak ada work base; project ini tidak memilikinya.
- Tidak menyentuh `kusmajalengka2`.

## Kendala

- Kandidat karyawan harus dibaca dari roster, bukan dari `employees.is_active`
  seperti `overtime-summary`. Roster memakai soft delete dan punya index
  `date`.
- `pay_periods.start_date`/`end_date` boleh NULL — periode tanpa tanggal tidak
  bisa dipakai membatasi rentang.
- Endpoint periode yang sudah ada:
  `GET /api/v1/settings/employee-data/pay-periods` → `id, name, start_date,
  end_date`, urut `start_date` terbaru dulu.
- Halaman sekarang default rentang = awal bulan s.d. hari ini; dengan dropdown
  periode defaultnya berubah menjadi periode yang memuat hari ini.
- Tombol Calculate sekarang memakai `confirm()`; dialog itu diganti modal.
- Pembatasan role yang ada (`auth.isManajemen` membuat tombol nonaktif) tetap
  berlaku.

## Pendekatan

- **A. Daftar roster lewat endpoint ringan + `employee_ids[]` ke endpoint
  hitung** (dipilih). Sumber daftar persis seperti yang diminta, payload modal
  kecil (satu query `distinct`), dan penyaringan benar-benar terjadi di server
  sehingga hanya baris karyawan terpilih yang ditulis.
- **B. Pakai ulang `overtime-summary` sebagai sumber modal.** Ditolak:
  daftarnya karyawan aktif, bukan pemilik roster, jadi tetap harus diubah; dan
  payload-nya berat karena menghitung agregat LM semua karyawan hanya untuk
  mengisi checkbox.
- **C. Frontend memanggil `hitung-lembur` sekali per karyawan.** Ditolak:
  ratusan request berurutan, rawan terputus di tengah dan meninggalkan hasil
  separuh jadi.

## Keputusan yang sudah diambil

### Backend

- Endpoint baru `GET /api/v1/attendance/prepare/overtime-employees`
  (`start_date`, `end_date` wajib; `end_date` ≥ `start_date`).
  - `employee_id` distinct dari roster dalam rentang (baris terhapus lunak
    otomatis tidak ikut).
  - `Employee` diambil `with('department:id,name')`, urut `employee_code`,
    hanya `id, employee_code, name, department_id`; balikan
    `{data: [{id, employee_code, name, department}]}`.
  - **Tanpa saringan `is_active`/`resign_date`**: kriterianya adalah pemilik
    roster. Karyawan yang sudah nonaktif tetapi masih punya roster di periode
    itu tetap muncul karena baris kehadirannya mungkin perlu dihitung.
  - Rute ditempatkan di prefix `attendance/prepare`, nama
    `attendance.prepare.overtime-employees`.
- `POST /api/v1/attendance/prepare/hitung-lembur` menerima `employee_ids`
  opsional: `nullable|array|min:1`, `employee_ids.*` integer.
  - Tidak dikirim → perilaku lama: seluruh record dalam rentang.
  - Dikirim kosong → 422 (ditolak validasi), bukan diam-diam "hitung semua".
- `AttendanceService::bulkHitungLembur` mendapat parameter keempat
  `?array $employeeIds = null` dan menambah
  `->when(! empty($employeeIds), fn ($q) => $q->whereIn('employee_id', $employeeIds))`.
  Aturan skip cuti, filter `is_locked`, dan pesan respons tidak berubah.
- Pilihan karyawan **tidak** memakai `period_id` di API; batas periode murni
  urusan UI (tanggal yang dikirim sudah di dalam periode).

### Frontend — `OvertimeCalculation/Index.vue`

- Dropdown **Periode** di kartu periode atas:
  - Data `GET /api/v1/settings/employee-data/pay-periods`; hanya periode yang
    punya `start_date` dan `end_date` yang bisa dipilih.
  - Default saat halaman dibuka: periode yang memuat hari ini; bila tidak ada,
    periode terbaru; bila tidak ada periode sama sekali, dropdown disembunyikan
    dan tanggal bebas seperti perilaku sekarang.
  - Memilih periode mengisi tanggal mulai/akhir dengan batas periode dan
    menaruh `period_id` di query URL.
  - Kedua input tanggal diberi `min`/`max` batas periode. Saat **Terapkan**,
    tanggal di luar batas ditarik ke batas periode.
  - Membuka URL lama tanpa `period_id`: pilih periode yang memuat `start_date`
    lalu tarik tanggal ke batasnya.
- Tombol Calculate Overtime membuka modal, bukan `confirm()`.
- Modal `EmployeePickerModal.vue` (di folder yang sama dengan `Index.vue`),
  memakai `BaseModal`:
  - Daftar dimuat ulang **setiap kali modal dibuka**, untuk rentang tanggal
    aktif.
  - **Semua karyawan tercentang saat modal terbuka** (meniru perilaku lama
    "hitung semua"); HR melepas centang yang tidak ingin dihitung.
  - Pencarian di klien pada `employee_code` dan `name`; **Pilih semua**
    berlaku pada daftar yang sedang tampil (hasil pencarian), bukan seluruh
    daftar.
  - Footer: "n dari N terpilih", tombol **Batal** dan **Mulai Kalkulasi
    Lembur** (nonaktif bila 0 terpilih, loading saat proses berjalan).
  - Sukses → modal ditutup, banner hasil `calculateResult` tampil, tabel rekap
    dimuat ulang.
  - Gagal → modal tetap terbuka, pesan error tampil di dalam modal, pilihan
    tidak hilang.
  - Menampilkan nama periode dan rentang tanggal sebagai konfirmasi sebelum
    memproses.

## Yang diserahkan ke pelaksana

- Markup dan gaya mengikuti `BaseModal`, `BaseButton`, dan kartu periode yang
  sudah ada; jangan merombak tampilan halaman.
- Nama state/fungsi mengikuti gaya `Index.vue` setempat.
- Test feature baru (pola `AttendanceScanImportTest`): (1) endpoint
  `overtime-employees` hanya mengembalikan pemilik roster dalam rentang, tanpa
  duplikat; (2) `hitung-lembur` dengan `employee_ids` hanya mengubah baris
  karyawan terpilih — baris karyawan lain tetap sama; (3) tanpa `employee_ids`
  kedua karyawan dihitung.
