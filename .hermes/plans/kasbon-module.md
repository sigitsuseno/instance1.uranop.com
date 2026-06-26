# Rencana Modul Pengelolaan Kasbon

> **Status**: Fase 1 — Menunggu review Sigit
> **Tanggal**: 26 Juni 2026
> **Pola Referensi**: Leave Module (app/Modules/Leave)

---

## 1. Ringkasan

Modul **Pengelolaan Kasbon** (Cash Advance) — karyawan bisa mengajukan pinjaman uang muka, atasan approve/reject, HR kelola pelunasan (potong gaji per periode), dan tracking riwayat.

**4 Submenu (dalam grup menu "Pengelolaan Kasbon"):**

| # | Submenu | Route | Deskripsi |
|---|---------|-------|-----------|
| 1 | Pengajuan Kasbon | `/admin/kasbon/requests` | List + form pengajuan baru |
| 2 | Persetujuan Kasbon | `/admin/kasbon/approvals` | Approve/reject pengajuan pending |
| 3 | Pelunasan Kasbon | `/admin/kasbon/repayments` | Tracking cicilan + potong gaji |
| 4 | Riwayat Kasbon | `/admin/kasbon/history` | History semua transaksi + filter |

**Penempatan Sidebar:** Di antara "Jadwal Kerja" dan "Pengelolaan Cuti".

---

## 2. Database

### 2.1 Tabel Baru: `kasbon_requests`

```sql
CREATE TABLE kasbon_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) UNIQUE NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 2) NOT NULL COMMENT 'Nominal pengajuan',
    tenor INT NOT NULL DEFAULT 1 COMMENT 'Jumlah bulan cicilan',
    reason TEXT NULL COMMENT 'Alasan pengajuan',
    status ENUM('pending', 'approved', 'rejected', 'disbursed', 'completed') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    disbursed_at TIMESTAMP NULL COMMENT 'Tanggal pencairan',
    remaining_amount DECIMAL(15, 2) DEFAULT 0 COMMENT 'Sisa yang belum dilunasi',
    notes TEXT NULL COMMENT 'Catatan internal',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);
```

### 2.2 Tabel Baru: `kasbon_installments`

Melacak cicilan per periode (auto-generated saat approve):

```sql
CREATE TABLE kasbon_installments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kasbon_request_id BIGINT UNSIGNED NOT NULL,
    pay_period_id BIGINT UNSIGNED NULL COMMENT 'Periode payroll terkait',
    installment_number INT NOT NULL COMMENT 'Cicilan ke-1,2,3...',
    amount DECIMAL(15, 2) NOT NULL COMMENT 'Nominal cicilan',
    status ENUM('pending', 'paid') DEFAULT 'pending',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (kasbon_request_id) REFERENCES kasbon_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (pay_period_id) REFERENCES pay_periods(id)
);
```

### 2.3 Relasi ke `employee_salary_components`

Kolom `kasbon` di `employee_salary_components` sudah ada (decimal, default 0). Kolom ini akan diisi otomatis saat pelunasan diproses per periode payroll (sebagai potongan).

---

## 3. Arsitektur Backend

### 3.1 Struktur Folder

```
app/Modules/Kasbon/
├── Controllers/
│   └── Api/
│       └── V1/
│           └── KasbonApiController.php    (semua endpoint)
├── Models/
│   ├── KasbonRequest.php
│   └── KasbonInstallment.php
├── Services/
│   ├── KasbonService.php                  (business logic: create, approve, repay)
│   └── KasbonInstallmentService.php       (generate cicilan, proses pelunasan)
├── Routes/
│   └── api.php
```

### 3.2 API Routes (`v1/kasbon`)

| Method | Path | Action | Deskripsi |
|--------|------|--------|-----------|
| GET | `/requests` | index | List pengajuan (filterable) |
| POST | `/requests` | store | Buat pengajuan baru |
| GET | `/requests/{id}` | show | Detail pengajuan |
| PUT | `/requests/{id}` | update | Edit pengajuan (pending only) |
| DELETE | `/requests/{id}` | destroy | Hapus pengajuan (pending only) |
| POST | `/requests/{id}/approve` | approve | Setujui + auto-generate cicilan |
| POST | `/requests/{id}/reject` | reject | Tolak pengajuan |
| POST | `/requests/{id}/disburse` | disburse | Tandai sudah dicairkan |
| GET | `/approvals` | approvals | List pengajuan pending (approval view) |
| GET | `/installments` | installments | List cicilan (pelunasan view) |
| POST | `/installments/{id}/pay` | payInstallment | Bayar 1 cicilan |
| POST | `/installments/bulk-pay` | bulkPay | Bayar multi cicilan |
| GET | `/history` | history | Riwayat semua transaksi |
| GET | `/export` | export | Export Excel |

Middleware: `auth:sanctum` + permission check.

### 3.3 Business Logic

**Flow:**
```
Create → Pending → Approve → (auto-generate installments) → Disburse → Bayar cicilan → Completed
                   ↘ Reject (selesai)
```

**1. Generate Cicilan (saat Approve):**
- Nominal per cicilan = amount / tenor (pembulatan)
- Cicilan terakhir menyerap selisih pembulatan
- `remaining_amount` = amount
- Status cicilan = 'pending'

**2. Pelunasan — Auto Deduct (auto_deduct = true):**
- Saat payroll diproses untuk suatu periode, sistem otomatis mencari cicilan `kasbon_installments` dengan `pay_period_id` yang match dan status 'pending'
- Set cicilan.status = 'paid', paid_at = now
- Kurangi `remaining_amount` di kasbon_request
- Update `kasbon` di `employee_salary_components` untuk periode payroll terkait
- Jika remaining = 0 → status kasbon = 'completed'

**2b. Pelunasan — Manual Push (auto_deduct = false):**
- Di halaman Pelunasan, HR memilih cicilan yang mau dibayar
- Klik "Push ke Payroll" → pilih periode payroll
- Update `pay_period_id` di cicilan + status = 'pending' (menunggu proses payroll)
- Saat payroll diproses, sama seperti auto-deduct

---

## 4. Arsitektur Frontend

### 4.1 Folder

```
resources/js/Pages/Admin/Kasbon/
├── Requests.vue       (Pengajuan — list + modal create/edit)
├── Approvals.vue      (Persetujuan — list + approve/reject action)
├── Repayments.vue     (Pelunasan — list cicilan + bayar)
├── History.vue        (Riwayat — list + filter)
```

### 4.2 Pola Halaman

Mengikuti pola Leave module:
- **Header**: Judul + deskripsi + dropdown periode (pakai PayPeriod)
- **Stats cards**: Total, Pending, Disetujui, Outstanding
- **Tabel**: DataTables-style dengan search, pagination, action buttons
- **Modal**: Form create/edit (untuk Requests)
- **Komponen shared**: `KasbonForm.vue` (create/edit form), `KasbonStats.vue` (stats cards)

### 4.3 Detail Halaman

#### 4.3.1 Requests.vue — Pengajuan Kasbon
- Dropdown filter: status, periode, karyawan
- Tabel: NIP, Nama, Bagian, Nominal, Tenor, Status, Aksi
- Tombol: + Pengajuan Baru (modal form), Edit, Hapus
- Stats: Total, Pending, Disetujui, Outstanding

#### 4.3.2 Approvals.vue — Persetujuan Kasbon
- Tabel: NIP, Nama, Nominal, Tenor, Alasan, Tanggal, Aksi
- Aksi: Setuju (checkmark), Tolak (×) — dengan konfirmasi
- Bulk approve/reject
- Stats: Total pending, Total nominal

#### 4.3.3 Repayments.vue — Pelunasan Kasbon
- Filter: karyawan, status cicilan
- Tabel cicilan: NIP, Nama, Cicilan ke-X, Nominal, Status, Periode Payroll
- Aksi: Bayar (per cicilan), Bayar Semua (bulk)
- Info outstanding per karyawan

#### 4.3.4 History.vue — Riwayat Kasbon
- Filter: karyawan, status, rentang tanggal
- Tabel: NIP, Nama, Nominal, Tenor, Status, Tgl Pengajuan, Tgl Pelunasan
- Export Excel

---

## 5. File yang Terlibat

### 5.1 File Baru

| File | Tipe |
|------|------|
| `database/migrations/xxxx_create_kasbon_requests_table.php` | Migration |
| `database/migrations/xxxx_create_kasbon_installments_table.php` | Migration |
| `app/Modules/Kasbon/Models/KasbonRequest.php` | Model |
| `app/Modules/Kasbon/Models/KasbonInstallment.php` | Model |
| `app/Modules/Kasbon/Controllers/Api/V1/KasbonApiController.php` | Controller |
| `app/Modules/Kasbon/Services/KasbonService.php` | Service |
| `app/Modules/Kasbon/Services/KasbonInstallmentService.php` | Service |
| `app/Modules/Kasbon/Routes/api.php` | Routes |
| `resources/js/Pages/Admin/Kasbon/Requests.vue` | Vue Page |
| `resources/js/Pages/Admin/Kasbon/Approvals.vue` | Vue Page |
| `resources/js/Pages/Admin/Kasbon/Repayments.vue` | Vue Page |
| `resources/js/Pages/Admin/Kasbon/History.vue` | Vue Page |

### 5.2 File Diedit

| File | Perubahan |
|------|-----------|
| `resources/js/Layouts/Admin/Sidebar.vue` | Tambah menu "Pengelolaan Kasbon" di antara Jadwal Kerja & Pengelolaan Cuti |
| `resources/js/router/index.js` | Tambah 4 route + lazy imports |
| `resources/js/Stores/permission.js` | Tambah permission mapping (kalau perlu) |
| `bootstrap/app.php` | Auto-discovery via glob (harusnya otomatis) |

---

## 6. Execution Phases

### Fase 1: Database + Model
- Migration `kasbon_requests`
- Migration `kasbon_installments`
- Model `KasbonRequest` + `KasbonInstallment` (relasi, casts, fillable)

### Fase 2: Backend API
- `KasbonApiController` — CRUD + approve/reject/disburse
- `KasbonService` + `KasbonInstallmentService`
- Routes `api.php`
- Test via artisan tinker / curl

### Fase 3: Frontend + Sidebar + Router
- 4 halaman Vue
- Komponen shared: `KasbonForm.vue`, `KasbonStats.vue`
- Tambah entry di Sidebar.vue
- Tambah route di router/index.js

### Fase 4: Integrasi Payroll
- Auto-potong kasbon di komponen gaji (update `employee_salary_components.kasbon`)
- Sinkronisasi pelunasan dengan periode payroll

---

## 6. Konfigurasi Dinamis (Settings Modal)

Pola: ReportSettingsModal-style — ikon gear di pojok kanan atas halaman.

### 6.1 Config Type: `kasbon`

Disimpan di tabel `payroll_configs` (karena terkait keuangan/payroll):

```json
{
  "limit_type": "salary_multiplier",  // "salary_multiplier" | "fixed" | "unlimited"
  "limit_value": 3,                    // 3x gaji pokok (kalau salary_multiplier)
  "max_tenor": 12,                     // Max bulan cicilan
  "interest_rate": 0,                  // Bunga (%)
  "allow_multi": false,                // Boleh >1 kasbon aktif?
  "auto_deduct": true,                 // Auto potong gaji di payroll?
  "approval_roles": ["hrmanager", "superadmin"]  // Role yang bisa approve
}
```

### 6.2 API

| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/api/v1/payroll/configs/kasbon` | Ambil config |
| PUT | `/api/v1/payroll/configs/kasbon` | Simpan config |

### 6.3 Frontend: KasbonSettingsModal

Komponen shared: `KasbonSettingsModal.vue` — berisi form:
- Limit type dropdown (salary_multiplier / fixed / unlimited)
- Limit value (muncul kalau bukan unlimited)
- Max tenor (bulan)
- Interest rate (%)
- Allow multi kasbon (toggle)
- Approval roles (multi-select)

### 6.4 Backend Validation

`KasbonService::validateRequest()`:
- Cek limit: kalau `salary_multiplier` → amount ≤ gaji_pokok × limit_value
- Cek tenor: tidak boleh > max_tenor
- Cek multi: tidak boleh create kalau masih ada outstanding kasbon (kecuali allow_multi = true)

---

## 7. Open Questions

1. **Referensi**: Tidak ada modul kasbon di sistem lama. Kalau ada referensi dari aplikasi lain atau screenshot UI, bisa jadi acuan desain.
2. **Supervisor access**: Apakah modul ini juga perlu ada di dashboard Supervisor? (sepertinya tidak, karena ini HR-only)
