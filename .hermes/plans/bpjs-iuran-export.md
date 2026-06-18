# BPJS Iuran Export

**Tanggal**: 2026-06-18
**Fitur**: Tombol export Excel di submenu Iuran BPJS

---

## Ringkasan

Export data iuran BPJS (yang tampil di tabel `Iuran/Index.vue`) ke file Excel.
Data source: `employee_bpjs` + enrich dinamis dari Employee model, sama persis seperti `iuranIndex()`.

## Phase 1 — Backend: Export Class + Controller + Route

### 1a. Buat Export Class
**File baru**: `app/Modules/Employee/Exports/BpjsIuranExport.php`

- Implements: `FromArray`, `WithHeadings`, `WithMapping`, `WithStyles`, `WithColumnWidths`, `WithEvents`
- Constructor: `($data, $periodName)` — data sudah di-enrich dari controller
- Headings (row 1): Title "LAPORAN IURAN BPJS" + nama periode
- Headings (row 2): No | Nama | Kode | Gaji Pokok | TJ MK | Tunjangan | Dasar BPJS | JHT (Perusahaan) | JKK (Perusahaan) | JKM (Perusahaan) | KES (Perusahaan) | JP (Perusahaan) | JHT (Karyawan) | KES (Karyawan) | JP (Karyawan)
- Mapping: row number + semua field
- Footer: Total Employer & Total Employee (gak pake grand total — biar sederhana)
- Styling: bold header, borders, number format `#,##0`, freeze pane di D3

### 1b. Tambah method `exportIuran` di Controller
**File**: `app/Modules/Employee/Controllers/Api/V1/Bpjs/BpjsEmployeeController.php`

- Validasi: `pay_period_id` (required)
- Ambil data dari `employee_bpjs` (sama kayak `iuranIndex` tapi NO pagination — all data)
- Enrich dengan dynamic values (gaji_pokok, tjMasaKerja, tunjangan — dari Employee model)
- Return `Excel::download(new BpjsIuranExport($data, $periodName), 'Iuran_BPJS_<periode>.xlsx')`

### 1c. Tambah Route
**File**: `app/Modules/Employee/Routes/api.php`

```
Route::get('/iuran/export', [BpjsEmployeeController::class, 'exportIuran']);
```

---

## Phase 2 — Frontend: Button + Blob Download

### 2a. Tambah tombol "Export Excel" di halaman Iuran
**File**: `resources/js/Pages/Admin/Payroll/Bpjs/Iuran/Index.vue`

- Tombol hijau dengan ikon 📥 di samping filter periode
- Pattern: `fetch(url, { headers: { Authorization: Bearer <token> } })` → blob download
- Nama file: `Iuran_BPJS.xlsx`

---

## Verifikasi

- [ ] Export jalan dengan periode terpilih
- [ ] Export jalan tanpa periode (semua data)
- [ ] File .xlsx bisa dibuka, ada header, data, footer
- [ ] Format angka benar (ribuan, desimal)
- [ ] Nama file sesuai periode
