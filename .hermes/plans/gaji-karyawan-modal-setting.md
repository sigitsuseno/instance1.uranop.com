# Plan: Modal Setting + Section Table untuk Gaji Karyawan

> Tanggal: 2026-06-25
> Tujuan: Tambah modal konfigurasi di halaman Gaji Karyawan + restruktur tabel jadi per-section

---

## Overview

Halaman Gaji Karyawan saat ini menampilkan satu tabel flat semua karyawan.  
Akan dirombak menjadi **2 section** dengan section label + section total + grand total.  
Mapping grup ke section diatur via **modal Setting** (komponen terpisah).

## Section Mapping

| Section | Label | Default Grup |
|---|---|---|
| A | KARYAWAN ALL IN | GRP-ALLIN, GRP-SPR |
| B | KARYAWAN BULANAN PRINT | GRP-GD, GRP-SS, GRP-PS1 |

- Grup di luar mapping di atas → tidak tampil (group penggajian = 5 grup ini aja)
- Section B: perhitungan lembur TETAP normal (bukan Rp 0)
- Zero overtime groups? Tidak relevan untuk section mapping — semua section dihitung normal

## Phase 1: Backend — Config API

### 1.1 Gunakan `report_configs` yang sudah ada
- `report_type = 'gaji_karyawan'`
- `employee_groups` → JSON array grup yang termasuk penggajian (opsional, untuk validasi)
- `config` → JSON:
  ```json
  {
    "sections": {
      "A": ["GRP-ALLIN", "GRP-SPR"],
      "B": ["GRP-GD", "GRP-SS", "GRP-PS1"]
    }
  }
  ```

### 1.2 Daftarkan default config di `ReportConfigService::getDefaultConfig()`
- Tambah case `'gaji_karyawan'` dengan default config di atas

### 1.3 Tidak perlu controller baru
- API `GET/PUT /api/v1/settings/report-configs/gaji_karyawan` sudah ada
- Dipakai oleh `ReportConfigApiController` + `ReportConfigService`

### 1.4 Session API untuk period-dependent data (opsional)
- Periods, employee groups, dll sudah di-fetch terpisah
- Tidak ada API baru untuk modal setting

## Phase 2: Frontend — Modal Setting Component

### 2.1 File baru: `resources/js/Components/ReportPage/settings/GajiKaryawanSettings.vue`

Props:
- `config` (Object) — current config from server
- `extraData` (Object) — `{ periods, employeeGroups }`

Fitur:
- List grup penggajian (5 grup: ALLIN, SPR, GD, SS, PS1) — hardcode atau dari `employeeGroups`
- Per grup: radio/select Section A atau B
- Preview: tampilkan ringkasan "Section A: GRP-ALLIN, GRP-SPR" / "Section B: GRP-GD, GRP-SS, GRP-PS1"
- Tombol Simpan → PUT config

### 2.2 Update `Index.vue` (GajiKaryawan)

**Template:**
- Ganti tabel flat → 2 section dengan `v-for="section in sections"`
- Setiap section: label bar + `<table>` + section total row
- Grand total bar di bawah

**Script:**
- Tambah state: `showSettings = false`
- Load config dari `GET /api/v1/settings/report-configs/gaji_karyawan` di `onMounted`
- Computed `sections` → group `records` by employee group → section A/B
- Function `onSettingsSaved(payload)` → update sections mapping, refresh

**Yang dihapus:**
- Tidak ada lagi modal "Kalkulasi & Kunci Gaji" dengan checkbox zero overt
- Tombol "Kalkulasi & Kunci" tetap ada tapi modalnya jadi Setting (atau tetap pakai modal approve tapi tanpa zero overtime checkbox?)

> **⚠️ Perlu klarifikasi:** Modal "Kalkulasi & Kunci Gaji" yang sekarang (dengan checkbox zero overtime) — apakah tetap dipertahankan TERPISAH dari modal Setting, atau digabung?

## Phase 3: Table Restructure

### 3.1 Computed `sections`

```js
const sections = computed(() => {
  const mapping = sectionMapping.value // dari config
  const result = [
    { key: 'A', label: 'A. KARYAWAN ALL IN', data: [], totals: null },
    { key: 'B', label: 'B. KARYAWAN BULANAN PRINT', data: [], totals: null },
  ]
  
  for (const record of filteredRecords.value) {
    // Cari section berdasarkan group karyawan
    const sectionKey = findSectionForEmployee(record)
    if (sectionKey) {
      result.find(s => s.key === sectionKey).data.push(record)
    }
  }
  
  // Hitung section totals
  for (const section of result) {
    section.totals = computeTotals(section.data)
  }
  
  return result
})
```

### 3.2 Template structure per section

```html
<template v-for="section in sections" :key="section.key">
  <!-- Section Label -->
  <div class="px-4 py-2 bg-(--primary)/10 font-bold text-sm ...">
    {{ section.label }} ({{ section.data.length }} Karyawan)
  </div>
  
  <!-- Table -->
  <table class="w-full text-xs">
    <!-- ... same 26-col header ... -->
    <!-- ... same 26-col body ... -->
    <!-- Section Total row -->
  </table>
</template>

<!-- Grand Total -->
<div class="px-4 py-3 bg-(--primary)/5 ...">
  TOTAL KESELURUHAN ...
</div>
```

### 3.3 Fungsi mapping employee → section

```js
function findSectionForEmployee(record) {
  // Ambil groups si employee dari data tambahan
  const empGroups = employeeGroupMap.value[record.id] || []
  const mapping = sectionMapping.value
  
  for (const group of empGroups) {
    if (mapping.A?.includes(group)) return 'A'
    if (mapping.B?.includes(group)) return 'B'
  }
  return null // skip — bukan group penggajian
}
```

> **⚠️ Masalah:** API `GajiKaryawanController@index` tidak return `employee_groups` per record.  
> Opsi:
> a. Tambah field `groups` di response API
> b. Fetch employee groups secara terpisah (endpoint `/api/v1/settings/employee-data/by-group/...`)
> c. Gabungin: fetch employee-groups map sekali, simpan di `employeeGroupMap`

## Phase 4: Cleanup

- Hapus state `employeeGroups` dan `selectedZeroOvertimeGroups` dari Index.vue
- Hapus/refactor modal "Kalkulasi & Kunci Gaji" (tergantung keputusan)
- Update Sidebar jika perlu (tidak ada perubahan route)

---

## Files yang Terlibat

| Action | File |
|---|---|
| **NEW** | `resources/js/Components/ReportPage/settings/GajiKaryawanSettings.vue` |
| **EDIT** | `resources/js/Pages/Admin/Payroll/GajiKaryawan/Index.vue` |
| **EDIT** | `app/Modules/Settings/Services/ReportConfigService.php` (tambah default config) |

## Open Questions

1. Modal "Kalkulasi & Kunci Gaji" yang sekarang — tetap pisah atau digabung dengan modal Setting?
2. Cara dapetin mapping employee → groups: tambah field di API atau fetch terpisah?
