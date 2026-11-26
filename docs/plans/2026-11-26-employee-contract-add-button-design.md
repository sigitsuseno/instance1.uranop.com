# Tombol Tambah Kontrak di detail karyawan — design

Tanggal: 26 Nov 2026
Lingkup: frontend `kuskarangjati` saja. Backend tidak berubah.

## Maksud

HR perlu menambahkan record kontrak untuk seorang karyawan langsung dari
`/admin/employees/{id}`, tanpa harus pindah ke halaman Kontrak Kerja
(`/admin/employees/contracts`). Kasus pemicu: AGUS GUNARDI berstatus
`permanent` dengan kontrak PKWT lama yang tidak pernah diperpanjang, sehingga
tidak muncul di Rekap PPH & Kompensasi sejak periode Oktober 2026.

## Tujuan

- Tab **Kontrak** di `Karyawan/Show.vue` punya tombol **Tambah Kontrak**.
- Klik tombol → modal berisi form kontrak (mode create) → simpan → daftar
  kontrak di tab langsung diperbarui.
- Nomor kontrak dibuat otomatis dan tanggal mulai bisa diisi otomatis H+1 dari
  `end_date` kontrak terakhir, sehingga kontrak lanjutan cepat dibuat.

## Bukan tujuan

- Tidak menambah tombol edit/hapus/cetak kontrak di tab ini (tetap di halaman
  Kontrak Kerja).
- Tidak mengubah `ContractApiController`, validasi, atau model
  `EmployeeContract`.
- Tidak mengubah `employment_status` karyawan saat kontrak ditambahkan.
- Tidak memperbaiki logika filter kontrak di
  `RekapPphKompensasiController` — itu isu terpisah.

## Kendala

- `POST /api/v1/employees/{employee}/contracts` sudah ada dan sudah dipakai
  `Contracts/Form.vue`; nomor kontrak unik divalidasi server.
- `EmployeeContract::booted()` otomatis menjadikan kontrak baru `is_latest` dan
  menurunkan kontrak lain karyawan yang sama.
- `GET /api/v1/employees/{id}` mengembalikan `contracts` urut `start_date`
  menurun.
- `Get` list kontrak tetap milik halaman; `Show.vue` hanya memuat ulang data
  karyawan setelah simpan.

## Pendekatan

- **A. Pakai ulang `Contracts/Form.vue` di dalam `BaseModal` (dipilih).**
  Satu form, satu validasi, perilaku create identik dengan halaman Kontrak
  Kerja; perubahan hanya di `Show.vue` plus satu prop opsional di `Form.vue`.
- **B. Membuat form baru khusus halaman detail.** Ditolak: menduplikasi form
  dan validasi yang sudah ada, rawan berbeda perilaku.
- **C. Navigasi ke `/admin/employees/contracts` dengan modal terbuka.**
  Ditolak: keluar dari konteks karyawan, dan halaman itu belum mendukung
  deep-link ke form karyawan tertentu.

## Keputusan yang sudah diambil

### `Karyawan/Show.vue`

- Import `BaseModal`, `ContractForm` dari
  `../../Contracts/Form.vue`, dan `usePermissionStore`.
- State: `showContractModal` (boolean).
- Di tab Kontrak, baris header berisi judul **Riwayat Kontrak** dan tombol
  **Tambah Kontrak** di kanan; tombol hanya tampil bila
  `permission.can('create employees')` — sama dengan halaman Kontrak Kerja.
- Tombol tampil untuk **semua** karyawan, bukan hanya
  `employment_status === 'contract'`, karena karyawan permanent bisa punya
  riwayat kontrak.
- Modal `BaseModal` (`title="Tambah Kontrak"`, `size="lg"`) berisi
  `<ContractForm :employee="employee" :default-start-date="suggestedStartDate" />`.
- `suggestedStartDate` = tanggal setelah `end_date` kontrak dengan
  `is_latest === true` (fallback: kontrak pertama pada daftar yang urut
  `start_date` menurun); `null` bila tidak ada `end_date`.
- `@success` → tutup modal, panggil `fetchEmployee()` agar kontrak baru tampil.
- `@cancel` → tutup modal.

### `Contracts/Form.vue`

- Prop baru opsional `defaultStartDate` (`String`, default `null`).
- Saat mode create dan `form.start_date` masih kosong, isi dengan
  `defaultStartDate`; pada mode edit prop ini diabaikan.
- Tidak ada perubahan payload, validasi, atau alur simpan.

## Yang diserahkan ke pelaksana

- Markup dan gaya mengikuti tombol/tabel yang sudah ada di `Show.vue` dan
  `BaseButton`; jangan merombak tampilan tab.
- Nama state/fungsi mengikuti gaya `Show.vue` setempat.
- Verifikasi: (1) tombol muncul sesuai permission dan membuka modal;
  (2) submit menambah kontrak, menutup modal, dan baris baru muncul paling
  atas dengan status benar; (3) tanggal mulai terisi otomatis H+1 untuk
  karyawan yang punya kontrak ber-`end_date`, dan kosong bila tidak ada;
  (4) mode edit kontrak di halaman Kontrak Kerja tidak berubah.
