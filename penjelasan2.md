# MODUL SUPERVISOR (SHADOW APP)

Modul Supervisor (`/supervisor`) dirancang sebagai "shadow application" (aplikasi bayangan) dari sistem HRIS utama. Modul ini memiliki struktur database, tabel, dan proses kalkulasi tersendiri yang mengadopsi logika dari folder `AuditSection`. Modul ini dibagi menjadi 8 fase pengerjaan berdasarkan menu di sidebar:

1. **DASHBOARD** (`/supervisor`)
2. **DATA MASTER** (`/supervisor/master`)
3. **DATA KARYAWAN** (`/supervisor/employee-data`)
4. **JADWAL KERJA** (`/supervisor/schedule`)
5. **PENGELOLAAN CUTI** (`/supervisor/leave`)
6. **ABSENSI** (`/supervisor/attendance`)
7. **PENGGAJIAN** (`/supervisor/salary`)
8. **LAPORAN** (`/supervisor/report`)

---

## PHASE 1: DASHBOARD (SELESAI)

- Menampilkan metrik ringkasan (Total Karyawan, Hadir, Cuti, Total Payroll bayangan, log aktivitas, dll).
- Memisahkan logika dashboard ke `app/Modules/Supervisor/Controllers/Api/V1/SupervisorDashboardController.php`.

## PHASE 2: DATA MASTER

- Pengelolaan data referensi dan pengaturan khusus supervisor (misal: aturan jam kerja bayangan, konfigurasi pajak bayangan, dan denda).
- Mengadopsi pengaturan referensi dari `AuditSection`.
- **Komponen:** `SupervisorMasterController`.

## PHASE 3: DATA KARYAWAN

- Manajemen data karyawan versi supervisor.

### SUBMENU KARYAWAN

1. Karyawan (/supervisor/employee-data/karyawan)
    - isinya CRUD Karyawan seperti di aplikasi utama frontend khusus supervisor.
2. Kontrak Kerja (/supervisor/employee-data/kontrak-kerja)
    - daftar kontrak kerja (daftar saja). di frontend daftar dibagi berdasarkan tab. Permanent dan kontrak
3. Gaji Karyawan (/supervisor/employee-data/gaji-karyawan)
    - isinya daftar karyawan dan gaji aktif (employee_salary)
4. Kompensasi (/supervisor/employee-data/kompensasi)
    - isinya sama persis dengan aplikasi utama.
5. BPJS Karyawan (/supervisor/employee-data/bpjs-karyawan)
    - isinya seperti keanggotaan di aplikasi utama (checkbox keanggotaan)
6. PPh Karyawan (/supervisor/employee-data/pph-karyawan)
    - isinya menampilkan tabel employee_pph

## PHASE 4: JADWAL KERJA

Penjadwalan UI untuk supervisor

### SUBMENU JADWAL KERJA

1. Jadwal Umum
    - UI copy aja dari aplikasi utama
2. Pola & Jadwal Kerja
    - UI copy aja dari aplikasi utama
3. shift
    - Seperti Aplikasi Utama
4. Buat Jadwal
    - Seperti Aplikasi Utama

## PHASE 5: PENGELOLAAN CUTI

- Melihat rekap pengajuan cuti dan penyesuaian khusus. Memanipulasi sisa cuti atau status cuti untuk keperluan penghitungan gaji bayangan.
- **Komponen:** `SupervisorLeaveController`.

## PHASE 6: ABSENSI

- Inti sistem absensi bayangan. Manajemen _raw log_ kehadiran, autolog, sinkronisasi kehadiran, _consecutive day_, lembur staf (_overtime_), hingga pembuatan _snapshot_ absensi bulanan.
- Bertugas menghitung kalkulasi _late_, _early leave_, dan lembur bayangan.
- Mengadopsi fungsionalitas dari `AttendanceSnapshotController`, `AttendanceAutologController`, dan `StaffOvertimeController` milik `AuditSection`.
- **Komponen:** `SupervisorAttendanceController`, Terintegrasi dengan model `AttendanceSnapshot`.

## PHASE 7: PENGGAJIAN

- Kalkulasi gaji bayangan (_Shadow Payroll_) berdasarkan data _snapshot_ absensi (Phase 6).
- Meliputi breakdown gaji, BPJS bayangan, perhitungan PPh21 bayangan, slip gaji, dan THR versi supervisor.
- Sepenuhnya mengadopsi logika `PayrollAudit`, `PengelolaanGajiController`, `SalaryBreakdownController` dari `AuditSection`.
- **Komponen:** `SupervisorPayrollController`, Terintegrasi dengan model `PayrollAudit`.

## PHASE 8: LAPORAN

- Pembuatan (generator) laporan ke dalam format Excel/PDF untuk rekapitulasi kehadiran, lembur bayangan, BPJS bayangan, rekap PPh21, dan Laporan Gaji (_Payroll_) Supervisor.
- Mengadopsi `Exports` dan `Reports` dari `AuditSection`.
- **Komponen:** `SupervisorReportController`.
