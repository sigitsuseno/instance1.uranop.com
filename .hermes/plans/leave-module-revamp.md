# Plan: Revamp Module Leave — Dari Tab-Based ke Multi-Halaman

> **Status:** ✅ Completed — 18 Juni 2026
> **Tanggal:** 18 Juni 2026

---

## Ringkasan

Module Leave dirombak total:
- Dari 1 halaman dengan 5 tab → 5 halaman terpisah
- Fitur "Perubahan Cuti" dihapus (diganti mekanisme: cancel → submit ulang)
- Setiap submenu jadi route mandiri, dengan entri sidebar sendiri

---

## 1. Analisis Database — NOL Perubahan ❌

**Tidak ada tabel baru, tidak ada kolom baru, tidak ada migrasi baru.**

Tabel existing yang tetap digunakan:

| Tabel | Fungsi | Keterangan |
|---|---|---|
| `leave_types` | Tipe cuti (cuti tahunan, sakit, izin, dll) | Tidak berubah |
| `leave_policies` | Kebijakan cuti (jatah, syarat) | Tidak berubah |
| `leave_periods` | Periode cuti | Tidak berubah |
| `leave_requests` | Pengajuan cuti (`pending`, `approved`, `rejected`, `cancelled`) | Tidak berubah |
| `employee_leaves` | Ledger saldo (increment/decrement) | Tidak berubah |
| `leave_documents` | Lampiran dokumen | Tidak berubah |

Tabel yang **tidak akan digunakan lagi** di frontend baru:
| Tabel | Keterangan |
|---|---|
| `leave_change_requests` | Fitur "Perubahan Cuti" dihapus. Tabel **dibiarkan tetap ada** (data historis), tapi fitur frontend tidak akan mengaksesnya lagi. |

**Kesimpulan:** Struktur database ZERO perubahan. Semua mekanisme (submit, approve, cancel, generate, recap) sudah ter-cover oleh tabel yang ada.

---

## 2. Struktur Baru — 5 Submenu Terpisah

```
Sidebar > Pengelolaan Cuti:
├── Generate Cuti Tahunan    → /admin/leave/generate
├── Pengajuan Cuti           → /admin/leave/requests   (include bulk approve + single approve/reject)
├── Pembatalan Cuti          → /admin/leave/cancellations
├── Saldo Cuti               → /admin/leave/balances
├── Rekap Cuti               → /admin/leave/recap
└── Pengaturan Cuti          → /admin/leave/settings (tetap)
```

### Halaman yang DIPERTAHANKAN:
- **Pengaturan Cuti** (`/admin/leave/settings`) — tetap, tidak berubah

### Halaman yang DIHAPUS:
- **Cuti & Izin** (`/admin/leave`) — dipecah jadi 5 halaman
- **Approval Cuti** (`/admin/leave/approvals`) — dilebur ke halaman Pengajuan Cuti
- Tab **Perubahan Cuti** — fitur dihapus total
- Tab **Generate Cuti** — pindah ke halaman sendiri
- Tab **Saldo Cuti** — pindah ke halaman sendiri
- Tab **Rekap Cuti** — pindah ke halaman sendiri

---

## 3. Detail Per Halaman

### 3.1 Generate Cuti Tahunan (/admin/leave/generate)

**Fitur:**
- Form pembuatan periode cuti (tanggal awal, tanggal akhir, nama periode, carry forward)
- Tabel daftar periode (CRUD)
- Generate kuota massal per periode + per kebijakan cuti
- Hanya diakses oleh superadmin & HR

**Data source:**
- `leave_periods` — CRUD periode
- `leave_policies` — pilihan kebijakan untuk generate
- `employee_leaves` — hasil generate

### 3.2 Pengajuan Cuti (/admin/leave/requests)

**Fitur:**
- Tabel daftar pengajuan cuti, filter per periode + status
- Tombol "Ajukan Cuti" → modal (sama kayak sekarang, tanpa mode edit)
- **Bulk approve**: Klik tombol "Approve" → checkbox muncul → pilih → konfirmasi bulk approve
- Single approve/reject per row
- Export Excel & PDF
- Search NIP / Nama

**Data source:**
- `leave_requests` — list, filter, create
- `employee_leaves` — dipotong saat approve

### 3.3 Pembatalan Cuti (/admin/leave/cancellations)

**Fitur:**
- Tabel daftar cuti yang bisa dibatalkan (status `approved` atau `pending`)
- Filter per periode
- **Bulk cancel**: Klik "Batalkan" → checkbox muncul → pilih → konfirmasi
- Single cancel per row
- Semua proses di tangan HR/Superadmin (tanpa employee submission)
- Export Excel & PDF

**Mekanisme cancel (sama seperti sekarang):**
1. Update `leave_requests.status = 'cancelled'`
2. Jika sebelumnya `approved` → inject increment ke `employee_leaves` (kembalikan kuota)

### 3.4 Saldo Cuti (/admin/leave/balances)

**Fitur:**
- Tabel saldo cuti karyawan per periode
- Filter per periode
- Export Excel & PDF
- Nggak ada proses edit/tambah/kurang (read-only)

**Data source:**
- `employee_leaves` — agregat per employee + leave_type + period

### 3.5 Rekap Cuti (/admin/leave/recap)

**Fitur:**
- Informasi periode aktif (nama, status, carry forward)
- Preview sisa jatah karyawan (balance > 0)
- Tombol "Akhiri & Tutup Periode" → hanguskan sisa saldo (jika carry forward false)
- Hanya diakses superadmin & HR

**Mekanisme (sama seperti sekarang):**
1. Update `leave_periods.status = 'closed'`
2. Jika `is_carry_forward = false` → inject decrement untuk seluruh sisa saldo

---

## 4. API Endpoints — yang Berubah

### Endpoint yang TETAP:

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/v1/leave/periods` | List periode |
| POST | `/api/v1/leave/periods` | Create periode (dari Generate page) |
| PUT | `/api/v1/leave/periods/{id}` | Update periode |
| DELETE | `/api/v1/leave/periods/{id}` | Delete periode |
| GET | `/api/v1/leave/requests` | List pengajuan (tetap) |
| POST | `/api/v1/leave/requests` | Submit pengajuan baru (tetap) |
| PUT | `/api/v1/leave/requests/{id}` | Edit pengajuan pending (tetap) |
| POST | `/api/v1/leave/requests/{id}/approve` | Single approve (tetap) |
| POST | `/api/v1/leave/requests/{id}/reject` | Single reject (tetap) |
| POST | `/api/v1/leave/requests/{id}/cancel` | Single cancel (tetap) |
| GET | `/api/v1/leave/balances` | Saldo cuti (tetap) |
| POST | `/api/v1/leave/generate-quota` | Generate kuota (tetap) |
| POST | `/api/v1/leave/recap-period` | Tutup periode (tetap) |

### Endpoint BARU:

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/v1/leave/requests/bulk-approve` | Bulk approve (array ids) |
| POST | `/api/v1/leave/requests/bulk-reject` | Bulk reject (array ids + reason) |
| POST | `/api/v1/leave/requests/bulk-cancel` | Bulk cancel (array ids) |

### Endpoint yang DIHAPUS dari frontend:

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/api/v1/leave/change-requests` | Tidak dipakai lagi |
| POST | `/api/v1/leave/requests/{id}/change` | Tidak dipakai lagi |
| POST | `/api/v1/leave/change-requests/{id}/approve` | Tidak dipakai lagi |
| POST | `/api/v1/leave/change-requests/{id}/reject` | Tidak dipakai lagi |

> **Catatan**: Endpoint change request tidak dihapus dari routes/api.php maupun controller. Hanya tidak dipanggil dari frontend baru. Data historis `leave_change_requests` tetap ada di database.

---

## 5. Perubahan di Frontend

### Files DIHAPUS:

| File | Keterangan |
|---|---|
| `resources/js/Pages/Admin/Leave/Index.vue` | Dipecah jadi 5 halaman |

### Files BARU:

| File | Keterangan |
|---|---|
| `resources/js/Pages/Admin/Leave/Generate.vue` | Generate cuti + periode CRUD |
| `resources/js/Pages/Admin/Leave/Requests.vue` | Pengajuan cuti |
| `resources/js/Pages/Admin/Leave/Cancellations.vue` | Pembatalan cuti |
| `resources/js/Pages/Admin/Leave/Balances.vue` | Saldo cuti |
| `resources/js/Pages/Admin/Leave/Recap.vue` | Rekap & tutup periode |

### Files TETAP:

| File | Keterangan |
|---|---|
| `resources/js/Pages/Admin/Leave/Settings.vue` | Pengaturan cuti |
| `resources/js/Pages/Admin/Leave/Approvals.vue` | Approval cuti |

### Perubahan di file lain:

| File | Keterangan |
|---|---|
| `resources/js/Layouts/Admin/Sidebar.vue` | Restruktur menu "Pengelolaan Cuti" — 5 submenu baru |
| `resources/js/router/index.js` | Tambah 5 route baru, hapus route `/admin/leave` lama |

---

## 6. Rencana Eksekusi — Per Phase

### Phase 1: Database & API
- [x] ~~Tidak ada migrasi~~ — skip
- [ ] Tambah endpoint bulk-approve, bulk-reject, bulk-cancel di controller
- [ ] Tambah route baru di `app/Modules/Leave/Routes/api.php`

### Phase 2: Halaman Generate Cuti Tahunan
- [ ] Buat `Generate.vue` — periode CRUD + generate kuota
- [ ] Daftarkan route `/admin/leave/generate`
- [ ] Update sidebar

### Phase 3: Halaman Pengajuan Cuti
- [ ] Buat `Requests.vue` — tabel + modal create + bulk/single approve + export
- [ ] Daftarkan route `/admin/leave/requests`
- [ ] Update sidebar

### Phase 4: Halaman Pembatalan Cuti
- [ ] Buat `Cancellations.vue` — tabel + bulk/single cancel + export
- [ ] Daftarkan route `/admin/leave/cancellations`
- [ ] Update sidebar

### Phase 5: Halaman Saldo & Rekap Cuti
- [ ] Buat `Balances.vue` — tabel saldo + export
- [ ] Buat `Recap.vue` — rekap + tutup periode
- [ ] Daftarkan route `/admin/leave/balances` & `/admin/leave/recap`
- [ ] Update sidebar

### Phase 6: Cleanup
- [ ] Hapus file `Index.vue` lama
- [ ] Hapus route `/admin/leave` dari router
- [ ] Uji semua halaman

---

## 7. Keputusan Final (Confirmed)

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Approval Cuti digabung? | ✅ **Ya** — dilebur ke halaman Pengajuan Cuti. Approve/reject bisa dilakukan dari sana (bulk + single). |
| 2 | Supervisor ikut dirombak? | ❌ **Nanti nyusul** — fokus Admin dulu. `Supervisor/Leave/Index.vue` tetap seperti sekarang. |
| 3 | Period selector | **Independen** — setiap halaman punya dropdown periode sendiri. |
| 4 | Format export | **Summary/rekap** — ringkas, bukan semua kolom mentah. |

### Yang TIDAK berubah:
- Halaman Supervisor (`Supervisor/Leave/Index.vue`) — tidak disentuh
- Halaman Pengaturan Cuti (`Admin/Leave/Settings.vue`) — tidak disentuh
- Semua tabel database — tidak ada migrasi baru
