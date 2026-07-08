# Plan: ManualSync — Pagination Per Employee

## Problem
Record di `att_manual_detect` di-sort `date, employee_id`. Akibatnya 1 karyawan cuma muncul 1-2 record per halaman karena tercampur karyawan lain. Harusnya: **1 karyawan full record, baru lanjut karyawan berikutnya**.

## Target
- Tanpa filter: pagination **per 10 karyawan** (1 halaman = 10 karyawan × semua record mereka dalam rentang tanggal)
- Dengan filter/search: tampil single karyawan (full record)

## Changes

### 1. Backend — `ManualSyncController::returnRecords()`
- **Ubah logika pagination**: dari per-record menjadi per-employee
- Ambil distinct `employee_id` dari `att_manual_detect` dalam rentang tanggal
- Paginate list employee_id (10 per halaman)
- Query semua record untuk 10 employee tersebut, ORDER BY `employee_id, date`
- Update response: `total` = jumlah karyawan, `last_page` = halaman karyawan

### 2. Frontend — `ManualSync.vue`
- `perPage` default: 10 (bukan 200)
- Opsi `perPage`: [10, 20, 50, 100]
- Label pagination: "10 karyawan/hal" bukan "row/hal"
- Info text: "{{ totalKaryawan }} karyawan" bukan "{{ totalRecords }} total record"
- `loadDataInternal`: kirim `per_page` sebagai jumlah karyawan per halaman
- Mode `display` & `fetch` sama-sama pakai pagination per-employee

### 3. Edge Cases
- **Filter employee aktif**: pagination tetep jalan (tapi cuma 1 karyawan = 1 page)
- **Search employee**: sama, tetep pagination per karyawan
- **Mode fetch**: fetch tetap proses SEMUA roster (tanpa pagination), cuma return-nya yang di-paginate per employee
