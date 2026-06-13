# Checklist Pengecekan Aplikasi HRIS — Untuk Tim HR

> **Dokumen ini dibuat untuk memudahkan tim HR mengecek fitur aplikasi secara mandiri.**
> Centang (✅) jika fitur berjalan sesuai harapan, beri tanda (❌) jika ada masalah.
> Tulis catatan di kolom "Keterangan" bila perlu.

**Tanggal Pengecekan:** _______________  
**Nama Pengecek:** _______________  
**Role Akun:** _______________  

---

## 1. Login & Akses Pengguna

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 1.1 | Login dengan akun HR | Buka halaman login, masukkan email & password HR | ☐ | |
| 1.2 | Menu sesuai role | Setelah login, lihat sidebar kiri — apakah menu yang tampil sesuai jobdesc HR? | ☐ | |
| 1.3 | Redirect otomatis | Jika sudah login, buka `/login` langsung — harusnya auto-redirect ke dashboard | ☐ | |
| 1.4 | Logout | Klik logout, pastikan kembali ke halaman login | ☐ | |
| 1.5 | Akses halaman tanpa login | Buka URL langsung (misal `/admin/employees`) tanpa login — harusnya redirect ke login | ☐ | |

---

## 2. Data Karyawan

### 2.1 Daftar Karyawan

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 2.1.1 | Lihat daftar karyawan | Menu **Data Karyawan → Karyawan** — semua karyawan tampil | ☐ | |
| 2.1.2 | Pencarian karyawan | Ketik nama/NIK di kolom search | ☐ | |
| 2.1.3 | Filter karyawan | Coba filter status (aktif/nonaktif), departemen, dll | ☐ | |
| 2.1.4 | Pagination | Scroll/ganti halaman data karyawan | ☐ | |
| 2.1.5 | Detail karyawan | Klik salah satu karyawan → lihat halaman detail | ☐ | |
| 2.1.6 | Tambah karyawan baru | Klik **Tambah Karyawan**, isi form lengkap, simpan | ☐ | |
| 2.1.7 | Edit karyawan | Edit data karyawan yang sudah ada, simpan perubahan | ☐ | |
| 2.1.8 | Hapus karyawan | Hapus satu karyawan (pastikan ada konfirmasi dulu) | ☐ | |
| 2.1.9 | Gender 'L' / 'P' | Saat tambah/edit, pastikan pilihan gender Laki-laki (L) dan Perempuan (P) | ☐ | |
| 2.1.10 | Import Excel | Menu **Data Karyawan → Import Karyawan** — upload file Excel, pastikan data masuk | ☐ | |
| 2.1.11 | Grouping Karyawan | Menu **Data Karyawan → Grouping Karyawan** — lihat/pindahkan grup karyawan | ☐ | |

### 2.2 Data Pendukung Karyawan

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 2.2.1 | Kontrak Kerja | Menu **Data Karyawan → Kontrak Kerja** — lihat daftar kontrak, tambah kontrak baru | ☐ | |
| 2.2.2 | Kompensasi Kontrak | Menu **Data Karyawan → Kompensasi** — lihat kompensasi per kontrak, tandai dibayar | ☐ | |
| 2.2.3 | Gaji Karyawan | Menu **Data Karyawan → Gaji Karyawan** — lihat komponen gaji per karyawan | ☐ | |
| 2.2.4 | Import Gaji | Menu **Data Karyawan → Gaji Karyawan → Import** — upload data gaji via Excel | ☐ | |
| 2.2.5 | Riwayat Pekerjaan | Menu **Data Karyawan → Riwayat Pekerjaan** — lihat history jabatan per karyawan | ☐ | |
| 2.2.6 | Keluarga & Tanggungan | Menu **Data Karyawan → Keluarga & Tanggungan** — lihat/tambah data keluarga | ☐ | |
| 2.2.7 | Dokumen | Menu **Data Karyawan → Dokumen** — upload/lihat dokumen karyawan | ☐ | |
| 2.2.8 | Resign & PHK | Menu **Data Karyawan → Resign & PHK** — proses terminasi karyawan | ☐ | |

---

## 3. Data Master (Organisasi)

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 3.1 | Departemen | Menu **Data Master → Bagian** — lihat, tambah, edit, hapus departemen | ☐ | |
| 3.2 | Pekerjaan / Jabatan | Menu **Data Master → Pekerjaan** — lihat, tambah, edit, hapus jabatan | ☐ | |
| 3.3 | Kalender Kerja | Menu **Data Master → Kalender** — lihat & atur hari libur nasional, hari kerja | ☐ | |
| 3.4 | Grade Gaji | Menu **Settings** (atau Organisation → Salary Grades) — lihat & atur grade gaji | ☐ | |

---

## 4. Jadwal Kerja

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 4.1 | Pola & Jadwal Kerja | Menu **Jadwal Kerja → Pola & Jadwal Kerja** — lihat pola kerja (FIXED, SHIFT, dll) | ☐ | |
| 4.2 | Shift | Menu **Jadwal Kerja → Shift** — lihat & atur jam shift | ☐ | |
| 4.3 | Jadwal Umum (Roster) | Menu **Jadwal Kerja → Jadwal Umum** — lihat roster karyawan | ☐ | |
| 4.4 | Buat Jadwal | Menu **Jadwal Kerja → Buat Jadwal** — generate roster untuk periode tertentu | ☐ | |
| 4.5 | Import Roster | Upload file roster via Excel | ☐ | |

---

## 5. Kehadiran (Attendance)

### 5.1 Import & Sync

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 5.1.1 | Import Kehadiran | Menu **Kehadiran → Import Kehadiran** — upload file Excel/fingerprint | ☐ | |
| 5.1.2 | Cek Log | Menu **Kehadiran → Cek Log** — lihat data log mentah yang sudah di-import | ☐ | |
| 5.1.3 | Sync Kehadiran | Menu **Kehadiran → Sync Kehadiran** — jalankan proses sync log → data kehadiran | ☐ | |
| 5.1.4 | Status sync sukses | Setelah sync, cek beberapa karyawan — status sudah terisi (hadir/absen/cuti/dll) | ☐ | |
| 5.1.5 | Lengkapi (auto-fill) | Jalankan **Lengkapi** — check_in/check_out kosong harus terisi otomatis | ☐ | |

### 5.2 Hitung Lembur

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 5.2.1 | Hitung Lembur | Menu **Kehadiran → Hitung Lembur** — jalankan kalkulasi | ☐ | |
| 5.2.2 | Hasil OT (overtime) | Cek karyawan dengan jam lebih — overtime terhitung benar | ☐ | |
| 5.2.3 | Hasil LM (hari libur) | Cek karyawan yang kerja di hari libur — LM terhitung dengan multiplier | ☐ | |
| 5.2.4 | Keterlambatan | Cek karyawan terlambat — late_minutes tercatat | ☐ | |
| 5.2.5 | Detail perhitungan | Lihat detail perhitungan lembur per karyawan — formula benar | ☐ | |

### 5.3 Resume & Consecutive

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 5.3.1 | Resume Kehadiran | Menu **Kehadiran → Resume Kehadiran** — rekap per periode | ☐ | |
| 5.3.2 | Generate resume | Klik Generate — data resume terisi (hari kerja, OT, LM, absen, dll) | ☐ | |
| 5.3.3 | Consecutive Day | Menu **Kehadiran → Consecutive Day** — deteksi hari kerja berturut-turut | ☐ | |

---

## 6. Cuti & Izin

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 6.1 | Daftar cuti/izin | Menu **Pengelolaan Cuti → Cuti & Izin** — lihat semua pengajuan | ☐ | |
| 6.2 | Tambah pengajuan | Buat pengajuan cuti/izin baru untuk karyawan | ☐ | |
| 6.3 | Approval cuti | Menu **Pengelolaan Cuti → Approval Cuti** — setujui/tolak pengajuan | ☐ | |
| 6.4 | Pengaturan cuti | Menu **Data Master → Pengaturan Cuti** — atur tipe cuti, kebijakan, kuota | ☐ | |
| 6.5 | Tipe cuti & izin | Pastikan ada kategori: cuti, izin, sakit, special | ☐ | |
| 6.6 | Saldo cuti | Cek saldo cuti karyawan berkurang setelah approval | ☐ | |

---

## 7. BPJS

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 7.1 | Keanggotaan BPJS | Menu **Pengelolaan BPJS → Keanggotaan** — lihat status kepesertaan karyawan | ☐ | |
| 7.2 | Centang BPJS TK/KES/PEN | Edit keanggotaan — centang/uncetang program yang diikuti | ☐ | |
| 7.3 | Iuran BPJS | Menu **Pengelolaan BPJS → Iuran BPJS** — lihat nominal iuran per karyawan | ☐ | |
| 7.4 | Generate Iuran | Klik **Generate Iuran** — kalkulasi otomatis dari persentase | ☐ | |
| 7.5 | Porsi Employer | Pastikan 5 kolom employer muncul (JHT, JKK, JKM, Kesehatan, JP) | ☐ | |
| 7.6 | Porsi Employee | Pastikan 3 kolom employee muncul (JHT, Kesehatan, JP) | ☐ | |
| 7.7 | Konfigurasi BPJS | Menu **Pengelolaan BPJS → Konfigurasi BPJS** — atur persentase & max cap | ☐ | |
| 7.8 | Cap maksimal upah | Cek karyawan bergaji di atas cap — iuran terpotong di batas max | ☐ | |
| 7.9 | Tanggungan kesehatan | Tambah tanggungan (0-5) — iuran kesehatan bertambah 1% per tanggungan | ☐ | |

---

## 8. PPh 21

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 8.1 | Pajak Karyawan | Menu **PPh 21 → Pajak Karyawan** — lihat data PTKP & pajak per karyawan | ☐ | |
| 8.2 | TER Bulanan | Menu **PPh 21 → TER Bulanan** — kalkulasi TER per bulan | ☐ | |
| 8.3 | PPh 21 Tahunan | Menu **PPh 21 → PPh 21 Tahunan** — kalkulasi tahunan, kurang/lebih bayar | ☐ | |
| 8.4 | Tarif TER | Pastikan tarif TER sesuai tabel pemerintah terbaru | ☐ | |
| 8.5 | PTKP | Pastikan nilai PTKP per status (TK, K0, K1, K2, K3) benar | ☐ | |

---

## 9. Payroll (Penggajian)

### 9.1 Periode & Generate Gaji

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 9.1.1 | Buat Periode Gaji | Menu **Payroll → Gaji Karyawan** atau **Data Master → Penggajian** — buat periode baru | ☐ | |
| 9.1.2 | Generate Gaji | Pilih periode, klik **Generate** — sistem menghitung gaji semua karyawan | ☐ | |
| 9.1.3 | Komponen gaji benar | Cek satu karyawan: gaji pokok, tunjangan, premi, masa kerja tampil sesuai | ☐ | |
| 9.1.4 | Data kehadiran masuk | Hari kerja, overtime, LM, absen dari modul Kehadiran masuk ke kalkulasi | ☐ | |
| 9.1.5 | Potongan BPJS & PPh | Cek potongan BPJS & PPh 21 di gaji karyawan | ☐ | |
| 9.1.6 | Denda keterlambatan | Cek karyawan yang sering terlambat — ada potongan denda | ☐ | |
| 9.1.7 | Edit manual | Edit komponen gaji manual (misal bonus tambahan) — recalculate otomatis | ☐ | |
| 9.1.8 | Lock periode | Setelah selesai review, **Lock** periode — tidak bisa diedit lagi | ☐ | |

### 9.2 Slip Gaji

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 9.2.1 | Lihat slip individu | Menu **Payroll → Slip Gaji** — pilih karyawan, lihat slip | ☐ | |
| 9.2.2 | Format slip lengkap | Slip menampilkan: pendapatan (rincian), potongan (rincian), gaji bersih | ☐ | |
| 9.2.3 | Export PDF (individu) | Download slip satu karyawan sebagai PDF | ☐ | |
| 9.2.4 | Export PDF (bulk) | Download semua slip satu periode sebagai ZIP | ☐ | |
| 9.2.5 | Export Excel | Download rekap gaji satu periode sebagai Excel | ☐ | |

### 9.3 THR

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 9.3.1 | Generate THR | Menu **Payroll → Perhitungan THR** — pilih tahun, klik Generate | ☐ | |
| 9.3.2 | Karyawan ≥ 12 bulan | Cek: dapat 1× gaji pokok | ☐ | |
| 9.3.3 | Karyawan < 12 bulan | Cek: proporsional (bulan/12 × gaji pokok) | ☐ | |
| 9.3.4 | Export THR | Download daftar THR sebagai Excel/PDF | ☐ | |

---

## 10. Laporan

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 10.1 | Laporan Lembur | Menu **Laporan → Laporan Lembur** — pilih periode, lihat & export | ☐ | |
| 10.2 | Lembur & Uang Makan | Menu **Laporan → Lembur & Uang Makan** — rekap OT + uang makan | ☐ | |
| 10.3 | Laporan Kehadiran | Menu **Laporan → Laporan Kehadiran** — rekap absensi per periode | ☐ | |
| 10.4 | Laporan Payroll | Menu **Laporan → Laporan Payroll** — rekap gaji per periode | ☐ | |
| 10.5 | Laporan Uang Makan | Menu **Laporan → Laporan Uang Makan** — rekap uang makan | ☐ | |
| 10.6 | Laporan Pajak | Menu **Laporan → Laporan Pajak** — rekap PPh 21 | ☐ | |
| 10.7 | Laporan BPJS | Menu **Laporan → Laporan BPJS** — rekap iuran BPJS | ☐ | |
| 10.8 | Filter laporan | Setiap laporan bisa difilter: tanggal, departemen, grup karyawan | ☐ | |
| 10.9 | Export Excel | Semua laporan bisa di-download sebagai Excel | ☐ | |
| 10.10 | Export PDF | Semua laporan bisa di-download sebagai PDF | ☐ | |

---

## 11. Pengaturan Sistem

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 11.1 | Pengaturan Umum | Menu **Settings** — akses halaman pengaturan | ☐ | |
| 11.2 | Konfigurasi Payroll | Menu **Data Master → Gaji & LTHR** — atur komponen gaji, overtime rate, dll | ☐ | |
| 11.3 | Profil Perusahaan | Menu **Perusahaan → Profil Perusahaan** — data perusahaan lengkap | ☐ | |

---

## 12. Notifikasi

| No | Yang Dicek | Cara Mengecek | ✅/❌ | Keterangan |
|----|-----------|---------------|------|------------|
| 12.1 | Lihat notifikasi | Menu **Notifikasi** (ikon lonceng di topbar) — klik & lihat daftar | ☐ | |
| 12.2 | Notifikasi muncul | Setelah approval cuti / generate payroll — muncul notifikasi? | ☐ | |
| 12.3 | Tandai sudah dibaca | Klik notifikasi → tandai read | ☐ | |

---

## 13. Uji Khusus: Satu Alur Penuh (End-to-End)

> **Jalankan satu skenario dari awal sampai akhir untuk memastikan semua modul terhubung.**

| No | Langkah | Yang Dicek | ✅/❌ | Keterangan |
|----|---------|------------|------|------------|
| 13.1 | Tambah karyawan baru | Input data lengkap (biodata, kontrak, gaji) | ☐ | |
| 13.2 | Atur jadwal | Masukkan karyawan ke roster/shift | ☐ | |
| 13.3 | Import absensi | Upload log fingerprint / Excel untuk karyawan tersebut | ☐ | |
| 13.4 | Sync + Lengkapi | Jalankan sync dan lengkapi | ☐ | |
| 13.5 | Hitung lembur | Jalankan kalkulasi lembur — overtime & LM muncul | ☐ | |
| 13.6 | Resume kehadiran | Generate resume — data rekap muncul | ☐ | |
| 13.7 | Generate gaji | Buka Payroll, generate gaji untuk periode tersebut | ☐ | |
| 13.8 | Cek slip | Lihat slip gaji karyawan baru — semua komponen benar | ☐ | |
| 13.9 | Generate THR | Cek THR untuk karyawan baru (proporsional jika < 1 tahun) | ☐ | |
| 13.10 | Laporan | Buka laporan kehadiran & payroll — data karyawan baru muncul | ☐ | |

---

## Catatan & Temuan

> **Tulis semua masalah, error, atau masukan di sini:**

| No | Masalah / Temuan | Modul / Halaman | Prioritas (Tinggi/Sedang/Rendah) |
|----|-----------------|-----------------|----------------------------------|
| 1  |                 |                 |                                  |
| 2  |                 |                 |                                  |
| 3  |                 |                 |                                  |
| 4  |                 |                 |                                  |
| 5  |                 |                 |                                  |

---

## Ringkasan Hasil

| Kategori | Total Item | ✅ Lulus | ❌ Gagal | Catatan |
|----------|-----------|----------|----------|---------|
| Login & Akses | 5 | | | |
| Data Karyawan | 19 | | | |
| Data Master | 4 | | | |
| Jadwal Kerja | 5 | | | |
| Kehadiran | 13 | | | |
| Cuti & Izin | 6 | | | |
| BPJS | 9 | | | |
| PPh 21 | 5 | | | |
| Payroll | 13 | | | |
| Laporan | 10 | | | |
| Pengaturan | 3 | | | |
| Notifikasi | 3 | | | |
| End-to-End | 10 | | | |
| **TOTAL** | **105** | | | |

---

> **Tips:** Fokus dulu ke skenario End-to-End (bagian 13) — kalau alur itu lancar, berarti mayoritas fitur sudah terhubung dengan benar.
> Setelah itu baru cek satu per satu fitur detail di bagian 1–12.
