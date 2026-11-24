# Tab per group di Manual Sync — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah pita tab di `/admin/attendance/manual-sync` yang menyaring karyawan per group ber-label `Imported Shift/Group`.

**Architecture:** Daftar group diambil klien dari endpoint settings yang sudah ada dan dirender sebagai tab; tab aktif mengirim `group_codes[]` ke `GET /api/v1/attendance/manual-sync/data`, yang menyaring `employee.groups.reference_code` di `returnRecords()` sebelum paginasi per karyawan. Tabel dan perilaku lain tidak berubah.

**Tech Stack:** Laravel 13 (PHP 8.3), Vue 3 `<script setup>`, Tailwind v4, PHPUnit (SQLite `:memory:`).

**Spec:** `docs/plans/2026-11-24-manual-sync-group-tabs-design.md`

## Global Constraints

- Hanya repo **kuskarangjati**; jangan menyentuh `kusmajalengka2`.
- **Tampilan tabel tidak berubah**: kolom, radio jam scan, status, paginasi, tombol tetap sama. Satu-satunya efek baru = penyaringan karyawan per tab.
- Penyaringan **di server**, bukan di klien (paginasi terjadi per karyawan di server).
- Label sumber tab: `Imported Shift/Group`, hanya group `is_active`, urut `code`; tab pertama `Semua`.
- Fetch Data tetap memindai seluruh roster; **Push Prepare tidak diubah**.
- Tidak ada migrasi baru, tidak ada endpoint baru, tidak ada halaman pengaturan tab.
- Test berjalan di SQLite `:memory:` (lihat `phpunit.xml`); gunakan `whereDate`-safe query seperti kode yang ada.

## Review Focus

- Karyawan **tanpa group** harus tetap muncul di tab `Semua` dan tidak muncul di tab group mana pun — dipin oleh test `test_semua_tanpa_group_codes_memuat_karyawan_tanpa_group`.
- `group_codes` **kosong (`[]`)** harus berperilaku sama dengan `Semua`, bukan menyaring semuanya — dipin oleh test `test_group_codes_kosong_sama_dengan_semua`.
- **Kode group tak dikenal** harus menghasilkan 0 karyawan tanpa error 500 — dipin oleh test `test_kode_group_tak_dikenal_mengembalikan_nol`.
- Group dari **label lain** (mis. `MANAJEMEN GAJI`) tidak boleh muncul sebagai tab walau `is_active` — frontend hanya mengambil `group_label === 'Imported Shift/Group'` (Task 2, langkah filter).
- Group **nonaktif** tidak boleh jadi tab; karyawannya tetap muncul di `Semua` (Task 2, filter `is_active`).

---

### Task 1: Backend — terima dan terapkan `group_codes[]`

**Files:**
- Modify: `app/Modules/Attendance/Controllers/Api/V1/ManualSyncController.php:51-60` (validasi `getData`), `:503-520` (filter di `returnRecords`)
- Test: `tests/Feature/ManualSyncGroupFilterTest.php` (baru)

**Interfaces:**
- Consumes: relasi `Employee::groups()` → `EmployeeGroup.reference_code`; endpoint `GET /api/v1/attendance/manual-sync/data`.
- Produces: query param `group_codes[]` (array of string, maks 100 item, tiap item maks 50 karakter). Tanpa param atau array kosong = tanpa saringan. Bentuk respons tidak berubah (`data[]`, `pagination.total`, `pagination.last_page`).

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/ManualSyncGroupFilterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Modules\Attendance\Models\ManualDetect;
use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Settings\Models\EmployeeGroup;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualSyncGroupFilterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance/manual-sync/data';

    private User $user;
    private PayPeriod $period;
    private Employee $groupA;
    private Employee $groupB;
    private Employee $noGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test HR',
            'email' => 'hr@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->period = PayPeriod::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Oktober 2026',
            'period_year' => 2026,
            'period_month' => 10,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'status' => 'active',
        ]);

        EmployeeGroupMaster::create([
            'group_label' => 'Imported Shift/Group',
            'name' => 'Group A',
            'code' => 'GRP-A',
            'is_active' => true,
        ]);
        EmployeeGroupMaster::create([
            'group_label' => 'Imported Shift/Group',
            'name' => 'Group B',
            'code' => 'GRP-B',
            'is_active' => true,
        ]);

        $this->groupA = $this->employee('A001', 'KARYAWAN A', 'GRP-A');
        $this->groupB = $this->employee('B001', 'KARYAWAN B', 'GRP-B');
        $this->noGroup = $this->employee('C001', 'KARYAWAN C');
    }

    private function employee(string $code, string $name, ?string $groupCode = null): Employee
    {
        $employee = Employee::create([
            'employee_code' => $code,
            'name' => $name,
            'gender' => 'P',
            'employment_status' => 'contract',
            'join_date' => '2026-09-01',
            'is_active' => true,
        ]);

        if ($groupCode) {
            EmployeeGroup::create([
                'employee_id' => $employee->id,
                'reference_code' => $groupCode,
            ]);
        }

        ManualDetect::create([
            'employee_id' => $employee->id,
            'date' => '2026-10-14',
            'status' => ManualDetect::STATUS_DRAFT,
        ]);

        return $employee;
    }

    private function names($response): array
    {
        return collect($response->json('data'))->pluck('employee.name')->unique()->sort()->values()->all();
    }

    private function url(array $query = []): string
    {
        return self::URL . '?' . http_build_query(array_merge([
            'period_id' => $this->period->id,
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-18',
            'mode' => 'display',
            'per_page' => 10,
        ], $query));
    }

    public function test_semua_tanpa_group_codes_memuat_karyawan_tanpa_group(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 3);
        $this->assertSame(['KARYAWAN A', 'KARYAWAN B', 'KARYAWAN C'], $this->names($response));
    }

    public function test_satu_group_code_hanya_memuat_anggota_group_itu(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => ['GRP-A']]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 1);
        $this->assertSame(['KARYAWAN A'], $this->names($response));
    }

    public function test_beberapa_group_code_memuat_gabungan_anggota(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => ['GRP-A', 'GRP-B']]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 2);
        $this->assertSame(['KARYAWAN A', 'KARYAWAN B'], $this->names($response));
    }

    public function test_group_codes_kosong_sama_dengan_semua(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => []]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 3);
    }

    public function test_kode_group_tak_dikenal_mengembalikan_nol(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => ['GRP-TIDAK-ADA']]));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 0);
        $this->assertSame([], $this->names($response));
    }

    public function test_group_codes_harus_array(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url(['group_codes' => 'GRP-A']))
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ManualSyncGroupFilterTest`
Expected: FAIL — `group_codes` tidak tersaring (test satu-group mendapat 3 karyawan, bukan 1; test validasi mendapat 200, bukan 422).

- [ ] **Step 3: Implementasi validasi di `getData`**

Di `ManualSyncController::getData`, tambahkan dua aturan setelah `'search'`:

```php
'group_codes'   => 'nullable|array|max:100',
'group_codes.*' => 'nullable|string|max:50',
```

- [ ] **Step 4: Implementasi filter di `returnRecords`**

Di `returnRecords()`, setelah blok `search` dan **sebelum** `$totalEmployees = ...`, tambahkan:

```php
// Urutan baku: group adalah saringan isi halaman. Keanggotaan group tidak
// punya dimensi waktu, jadi dibaca apa adanya.
$groupCodes = array_values(array_filter((array) ($filters['group_codes'] ?? [])));
if (!empty($groupCodes)) {
    $empQuery->whereHas('employee.groups', function ($q) use ($groupCodes) {
        $q->whereIn('reference_code', $groupCodes);
    });
}
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ManualSyncGroupFilterTest`
Expected: PASS (6 test).

- [ ] **Step 6: Jalankan regresi modul attendance**

Run: `php artisan test --filter=Attendance`
Expected: PASS semua (tidak ada perilaku lama yang berubah).

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/ManualSyncGroupFilterTest.php app/Modules/Attendance/Controllers/Api/V1/ManualSyncController.php
git commit -m "feat(attendance): filter manual-sync rows by employee group"
```

---

### Task 2: Frontend — pita tab group di halaman Manual Sync

**Files:**
- Modify: `resources/js/Pages/Admin/Attendance/ManualSync.vue` (template sebelum `<div v-if="syncData.length > 0" ...>`; state & fungsi di `<script setup>`)

**Interfaces:**
- Consumes: `GET /api/v1/settings/employee-data/groups` → `{ data: [{ code, name, group_label, is_active }] }`; param `group_codes[]` dari Task 1.
- Produces: tidak ada konsumen lain.

- [ ] **Step 1: Tambah state dan pemuat group**

Di `<script setup>`, setelah `const syncData = ref([])`:

```js
const groups = ref([])
const activeGroup = ref(null)
```

Lalu, di dekat `fetchPayPeriods`, tambahkan:

```js
async function fetchGroups() {
  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    const all = res.data || []
    groups.value = all
      .filter(g => g.group_label === 'Imported Shift/Group' && g.is_active !== false && g.is_active !== 0)
      .map(g => ({ code: g.code, name: g.name }))
      .filter(g => g.code)
      .sort((a, b) => String(a.code).localeCompare(String(b.code)))
  } catch (e) {
    console.error('Gagal fetch groups:', e)
    groups.value = []
  }
}
```

- [ ] **Step 2: Tambah daftar tab dan handler pindah tab**

Setelah `fetchGroups`:

```js
// Tab penyaring: `null` = Semua (tanpa saringan group).
const groupTabs = computed(() => [
  { code: null, label: 'Semua' },
  ...groups.value.map(g => ({ code: g.code, label: `${g.code} · ${g.name}` })),
])

function selectGroup(code) {
  if (activeGroup.value === code) return
  activeGroup.value = code
  if (!hasLoaded.value) return
  currentPage.value = 1
  loadDataInternal('display', 1)
}
```

- [ ] **Step 3: Kirim tab aktif ke API**

Di `loadDataInternal`, setelah blok `if (selectedEmployee.value)`:

```js
// Tab group aktif — server menyaring sebelum paginasi per karyawan.
if (activeGroup.value) {
  params.append('group_codes[]', activeGroup.value)
}
```

- [ ] **Step 4: Muat group saat halaman dibuka**

Di `onMounted` yang sudah ada:

```js
onMounted(() => {
  window.addEventListener('click', onWindowClick)
  fetchGroups()
})
```

- [ ] **Step 5: Render pita tab di template**

Sisipkan tepat **sebelum** `<div v-if="syncData.length > 0" class="bg-(--bg-card) ...">` (kartu tabel). Gaya mengikuti pita tab halaman Reports; tidak mengubah isi kartu tabel:

```html
<!-- Pita tab group: menyaring karyawan; isi tabel tidak berubah. -->
<div v-if="groups.length > 0" class="border-b border-(--border-soft) mb-4">
  <nav class="flex gap-0 -mb-px overflow-x-auto">
    <button
      v-for="tab in groupTabs"
      :key="tab.code ?? 'all'"
      @click="selectGroup(tab.code)"
      :class="[
        'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md whitespace-nowrap',
        activeGroup === tab.code
          ? 'text-(--primary) border-(--primary)'
          : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
      ]"
    >
      {{ tab.label }}
    </button>
  </nav>
</div>
```

- [ ] **Step 6: Build frontend**

Run: `npm run build`
Expected: build selesai tanpa error (tidak ada unknown component / sintaks Vue).

- [ ] **Step 7: Verifikasi di aplikasi**

Buka `/admin/attendance/manual-sync`, pilih periode + rentang, klik **Tampilkan Data**. Periksa:
- Pita tab memuat `Semua` + tab per group label `Imported Shift/Group`, urut kode.
- Klik satu tab → jumlah baris & `Total … karyawan` berubah sesuai anggota group; tabel, radio jam scan, dan tombol tetap sama.
- Klik `Semua` → kembali ke seluruh karyawan.
- Tab group tanpa anggota → tabel kosong dengan pesan "Tidak ada data roster untuk rentang ini.", tanpa error.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Admin/Attendance/ManualSync.vue
git commit -m "feat(attendance): add per-group tabs to manual sync page"
```
