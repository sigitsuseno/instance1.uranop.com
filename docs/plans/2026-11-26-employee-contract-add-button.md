# Tombol Tambah Kontrak di Detail Karyawan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan tombol **Tambah Kontrak** di tab Kontrak halaman detail karyawan yang membuka form kontrak (mode create) dan menyegarkan daftar setelah simpan.

**Architecture:** Halaman detail memakai ulang `Contracts/Form.vue` di dalam `BaseModal`; endpoint `POST /api/v1/employees/{employee}/contracts` sudah ada dan tidak diubah. Satu prop opsional baru (`defaultStartDate`) ditambahkan ke form agar tanggal mulai kontrak lanjutan terisi H+1 dari kontrak terakhir.

**Tech Stack:** Vue 3 (`<script setup>`), Vue Router, Pinia, Tailwind, Vite.

**Spec:** `docs/plans/2026-11-26-employee-contract-add-button-design.md`

## Global Constraints

- Lingkup **frontend saja**; tidak ada perubahan PHP, route, atau migrasi.
- Tombol digating `permission.can('create employees')`, sama seperti `Contracts/Index.vue`.
- Tombol tampil untuk **semua** karyawan, termasuk `employment_status = 'permanent'`.
- Tanggal prefill diformat lokal `YYYY-MM-DD`; **jangan** memakai `toISOString()` (bergeser satu hari di zona WIB).
- Repo tidak punya test runner JavaScript: verifikasi otomatis = `npm run build` sukses; sisanya checklist manual di browser.
- Jangan menjalankan formatter massal.

## Review Focus

- Karyawan tanpa kontrak sama sekali: tombol tetap berfungsi, tanggal mulai kosong (bukan error).
- Kontrak terakhir tanpa `end_date` (PKWTT): tidak ada prefill, bukan crash.
- Rollover akhir bulan/tahun: `end_date` 2026-12-31 → prefill 2027-01-01.
- Zona waktu: `end_date` 2026-09-08 → prefill tepat 2026-09-09.
- Submit gagal validasi (mis. nomor kontrak duplikat): modal tetap terbuka dan pesan error tampil.

---

### Task 1: Prop `defaultStartDate` di form kontrak

**Files:**
- Modify: `resources/js/Pages/Admin/Employees/Contracts/Form.vue`

**Interfaces:**
- Consumes: tidak ada.
- Produces: prop opsional `defaultStartDate: String` (default `null`) pada komponen `Form.vue`. Nilainya hanya dipakai saat mode create; mode edit tetap memakai data kontrak.

- [ ] **Step 1: Tambahkan prop**

Di `defineProps` (`Form.vue:9-18`), tambahkan setelah `employee`:

```js
  defaultStartDate: {
    type: String,
    default: null
  }
```

- [ ] **Step 2: Pakai prop saat mode create**

Di `initForm()` cabang `else` (`Form.vue:59-70`), ganti `form.start_date = ''` menjadi:

```js
    form.start_date = props.defaultStartDate || ''
```

Cabang `if (props.contract)` tidak diubah.

- [ ] **Step 3: Verifikasi build**

Run: `npm run build`
Expected: build selesai tanpa error Vue/ESLint.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Admin/Employees/Contracts/Form.vue
git commit -m "feat(employee): allow prefilled start date on contract form"
```

---

### Task 2: Tombol dan modal Tambah Kontrak di halaman detail

**Files:**
- Modify: `resources/js/Pages/Admin/Employees/Karyawan/Show.vue`

**Interfaces:**
- Consumes: `ContractForm` dari Task 1 (`:employee`, `:default-start-date`, `@success`, `@cancel`).
- Produces: tidak ada (halaman akhir).

- [ ] **Step 1: Tambahkan import dan store permission**

Setelah import `IconChevronLeft` (`Show.vue:9`), tambahkan:

```js
import BaseModal from '../../../../Components/BaseModal.vue'
import ContractForm from '../Contracts/Form.vue'
import { usePermissionStore } from '../../../../Stores/permission'
```

Setelah `const notification = useNotificationStore()`, tambahkan:

```js
const permission = usePermissionStore()
```

- [ ] **Step 2: Tambahkan state dan computed tanggal prefill**

Setelah `const activeTab = ref('kontrak')`:

```js
const showContractModal = ref(false)

const suggestedStartDate = computed(() => {
  const contracts = employee.value?.contracts || []
  const latest = contracts.find(c => c.is_latest) || contracts[0]
  if (!latest?.end_date) return null

  const [y, m, d] = String(latest.end_date).split('T')[0].split('-').map(Number)
  if (!y || !m || !d) return null

  const next = new Date(y, m - 1, d + 1)
  const pad = n => String(n).padStart(2, '0')
  return `${next.getFullYear()}-${pad(next.getMonth() + 1)}-${pad(next.getDate())}`
})
```

Catatan: `new Date(y, m - 1, d + 1)` memakai waktu lokal dan otomatis menangani rollover bulan/tahun; jangan diganti `toISOString()`.

- [ ] **Step 3: Tambahkan handler sukses**

Setelah `fetchSalaryComponents()`:

```js
async function handleContractSuccess() {
  showContractModal.value = false
  await fetchEmployee()
}
```

- [ ] **Step 4: Tambahkan header tab Kontrak dengan tombol**

Di dalam `<div v-if="activeTab === 'kontrak'">` (`Show.vue:240`), tepat sebelum `<table ...>`, tambahkan:

```html
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-(--text-main)">Riwayat Kontrak</h3>
              <BaseButton
                v-if="permission.can('create employees')"
                variant="primary"
                @click="showContractModal = true"
              >
                <template #icon-left>
                  <i class="bx bx-plus"></i>
                </template>
                Tambah Kontrak
              </BaseButton>
            </div>
```

- [ ] **Step 5: Tambahkan modal form**

Di akhir blok `<template v-else-if="employee">`, setelah `</BaseCard>` penutup (`Show.vue:394`), tambahkan:

```html
      <BaseModal
        :show="showContractModal"
        title="Tambah Kontrak"
        size="lg"
        @close="showContractModal = false"
      >
        <ContractForm
          :employee="employee"
          :default-start-date="suggestedStartDate"
          @success="handleContractSuccess"
          @cancel="showContractModal = false"
        />
      </BaseModal>
```

- [ ] **Step 6: Verifikasi build**

Run: `npm run build`
Expected: build selesai tanpa error.

- [ ] **Step 7: Verifikasi manual di browser**

Jalankan aplikasi, buka `/admin/employees/266` (AGUS GUNARDI), tab Kontrak:

1. Tombol **Tambah Kontrak** tampil dan membuka modal.
2. Tanggal Mulai terisi **2026-09-09** (H+1 dari `end_date` 2026-09-08).
3. Isi tanggal berakhir, submit → modal tertutup, kontrak baru muncul paling atas, badge status benar.
4. Buka karyawan tanpa kontrak → tanggal mulai kosong, form tetap bisa disubmit.
5. Cek rollover: karyawan dengan kontrak berakhir 31 Des 2026 (mis. id dengan `end_date` 2026-12-31) → prefill **2027-01-01**.
6. Login role tanpa `create employees` (mis. manajemen) → tombol tidak tampil.
7. Buka `/admin/employees/contracts` → form edit kontrak tetap memakai tanggal kontraknya, bukan prefill.
8. Submit nomor kontrak duplikat → modal tetap terbuka dan pesan error tampil.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Admin/Employees/Karyawan/Show.vue
git commit -m "feat(employee): add contract button on employee detail"
```
