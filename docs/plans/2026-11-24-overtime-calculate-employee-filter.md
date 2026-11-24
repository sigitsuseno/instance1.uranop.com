# Filter karyawan & batas periode pada Calculate Overtime — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tombol Calculate Overtime di `/admin/attendance/overtime-calculation` membuka modal pilih karyawan (pemilik roster) dan hanya menghitung karyawan terpilih, dengan rentang tanggal dibatasi satu periode gaji.

**Architecture:** Endpoint baru `GET attendance/prepare/overtime-employees` mengembalikan pemilik roster dalam rentang; endpoint `hitung-lembur` menerima `employee_ids[]` opsional dan meneruskannya ke `bulkHitungLembur` sebagai `whereIn('employee_id', …)`. Frontend menambah dropdown periode (dari endpoint settings yang ada) yang mengisi dan membatasi tanggal, lalu modal `EmployeePickerModal.vue` mengirim pilihan ke endpoint hitung. Rumus kalkulasi, tabel rekap, export, dan halaman Detail tidak berubah.

**Tech Stack:** Laravel 13 (PHP 8.3), Vue 3 `<script setup>`, Tailwind v4, PHPUnit (SQLite `:memory:`).

**Spec:** `docs/plans/2026-11-24-overtime-calculate-employee-filter-design.md`

## Global Constraints

- Hanya repo **kuskarangjati**; jangan menyentuh `kusmajalengka2`.
- **Rumus kalkulasi tidak berubah**: `AttendanceCalculatorService` dan `AttendanceService::hitungLembur` tidak disentuh.
- **Tabel rekap, Export Excel, Export Roster, halaman Detail tidak berubah.**
- `employee_ids` **tidak dikirim** → perilaku lama: seluruh record dalam rentang. `employee_ids` **dikirim kosong** → 422.
- Daftar kandidat = **pemilik roster** (`sch_employee_shift_rosters`) dalam rentang, **tanpa** saringan `is_active`/`resign_date`.
- Dropdown periode: hanya periode dengan `start_date` & `end_date`; tanggal dikunci `min`/`max` ke batas periode dan di-clamp saat Terapkan; default = periode yang memuat hari ini; tanpa periode → dropdown disembunyikan dan tanggal bebas.
- Modal: semua karyawan tercentang saat dibuka; "Pilih semua" berlaku pada daftar hasil pencarian; gagal → modal tetap terbuka.
- Tidak ada migrasi, kolom, atau tabel baru.
- Test di SQLite `:memory:` (`phpunit.xml`); tidak ada factory — buat model langsung seperti `AttendanceScanImportTest`.

## Review Focus

- **Pemilik roster tanpa baris `att_prepares`** tetap muncul di modal (0 record dihitung, bukan kosong/error) — dipin oleh `test_hanya_pemilik_roster_dalam_rentang_tanpa_duplikat` (Task 1).
- **Roster terhapus lunak** tidak boleh memunculkan karyawan hantu — dipin oleh `test_roster_terhapus_lunak_tidak_ikut` (Task 1).
- **Karyawan nonaktif yang masih punya roster** di periode tetap muncul (baris kehadirannya mungkin perlu dihitung) — dipin oleh `test_karyawan_nonaktif_dengan_roster_tetap_muncul` (Task 1).
- **`employee_ids: []` dari klien basi** harus 422, bukan diam-diam menghitung semua — dipin oleh `test_employee_ids_kosong_ditolak_422` (Task 2).
- **Tanggal dipersempit** di dalam periode: daftar modal dan kalkulasi hanya menyentuh rentang itu — dipin oleh `test_rentang_di_luar_roster_mengembalikan_kosong` (Task 1) dan `test_hitung_hanya_karyawan_terpilih` (Task 2).
- **Periode tanpa `start_date`/`end_date`** tidak boleh merusak dropdown — frontend menyaringnya (Task 3, langkah `fetchPeriods`; verifikasi manual).

---

### Task 1: Backend — endpoint daftar pemilik roster

**Files:**
- Create: `tests/Feature/OvertimeCalculateFilterTest.php`
- Modify: `app/Modules/Attendance/Controllers/Api/V1/AttendanceApiController.php` (tambah method setelah `prepareEmployeeGroups`, sekitar baris 490)
- Modify: `app/Modules/Attendance/Routes/api.php:52-56` (daftar route `overtime-*`)

**Interfaces:**
- Consumes: `App\Modules\Schedule\Models\EmployeeShiftRoster` (tabel `sch_employee_shift_rosters`, soft delete, kolom `employee_id`, `date`); relasi `Employee::department()`.
- Produces: `GET /api/v1/attendance/prepare/overtime-employees?start_date=Y-m-d&end_date=Y-m-d` → `{ data: [{ id, employee_code, name, department }] }`, urut `employee_code`. Nama route `attendance.prepare.overtime-employees`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/OvertimeCalculateFilterTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OvertimeCalculateFilterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/attendance/prepare/overtime-employees';

    private const START = '2026-10-12';

    private const END = '2026-10-18';

    private User $user;

    private Employee $budi;

    private Employee $siti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test HR',
            'email' => 'hr@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->budi = $this->employee('A001', 'BUDI');
        $this->siti = $this->employee('A002', 'SITI');
        $this->employee('A003', 'AGUS'); // tanpa roster

        $this->roster($this->budi, '2026-10-14');
        $this->roster($this->budi, '2026-10-15'); // dua baris, satu karyawan
        $this->roster($this->siti, '2026-10-16');

        $terhapus = $this->employee('A004', 'TERHAPUS');
        $this->roster($terhapus, '2026-10-14')->delete();
    }

    private function employee(string $code, string $name, bool $active = true): Employee
    {
        return Employee::create([
            'employee_code' => $code,
            'name' => $name,
            'gender' => 'P',
            'employment_status' => 'contract',
            'join_date' => '2026-09-01',
            'is_active' => $active,
        ]);
    }

    private function roster(Employee $employee, string $date): EmployeeShiftRoster
    {
        return EmployeeShiftRoster::create([
            'uuid' => (string) Str::uuid(),
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => 'scheduled',
        ]);
    }

    private function url(string $start = self::START, string $end = self::END): string
    {
        return self::URL . '?' . http_build_query([
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    private function codes($response): array
    {
        return collect($response->json('data'))->pluck('employee_code')->all();
    }

    public function test_hanya_pemilik_roster_dalam_rentang_tanpa_duplikat(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $response->assertOk();
        $this->assertSame(['A001', 'A002'], $this->codes($response));
    }

    public function test_roster_terhapus_lunak_tidak_ikut(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $this->assertNotContains('A004', $this->codes($response));
    }

    public function test_karyawan_nonaktif_dengan_roster_tetap_muncul(): void
    {
        $nonaktif = $this->employee('A005', 'NONAKTIF', false);
        $this->roster($nonaktif, '2026-10-15');

        $response = $this->actingAs($this->user, 'sanctum')->getJson($this->url());

        $this->assertContains('A005', $this->codes($response));
    }

    public function test_rentang_di_luar_roster_mengembalikan_kosong(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('2026-10-20', '2026-10-25'));

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_tanggal_wajib_dan_end_harus_setelah_start(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson(self::URL)
            ->assertStatus(422);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->url('2026-10-18', '2026-10-12'))
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OvertimeCalculateFilterTest`
Expected: FAIL — route belum ada (404), bukan 200/422.

- [ ] **Step 3: Implementasi `prepareOvertimeEmployees` di controller**

Tambahkan method berikut di `AttendanceApiController`, setelah `prepareEmployeeGroups`:

```php
/**
 * GET /api/v1/attendance/prepare/overtime-employees
 * Karyawan pemilik roster dalam rentang tanggal (kandidat Calculate Overtime).
 * Kriterianya roster, bukan is_active: karyawan nonaktif yang masih punya
 * baris roster di periode tetap ikut.
 */
public function prepareOvertimeEmployees(Request $request): JsonResponse
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
    ]);

    $employeeIds = \App\Modules\Schedule\Models\EmployeeShiftRoster::whereBetween('date', [
            $request->input('start_date'),
            $request->input('end_date'),
        ])
        ->distinct()
        ->pluck('employee_id');

    $employees = \App\Modules\Employee\Models\Employee::with('department:id,name')
        ->whereIn('id', $employeeIds)
        ->orderBy('employee_code')
        ->get(['id', 'employee_code', 'name', 'department_id']);

    return response()->json([
        'data' => $employees->map(fn ($employee) => [
            'id'            => $employee->id,
            'employee_code' => $employee->employee_code,
            'name'          => $employee->name,
            'department'    => $employee->department?->name,
        ]),
    ]);
}
```

- [ ] **Step 4: Daftarkan route**

Di `app/Modules/Attendance/Routes/api.php`, setelah baris `overtime-summary` (baris 52), tambahkan:

```php
Route::get('overtime-employees', [AttendanceApiController::class, 'prepareOvertimeEmployees'])
    ->name('attendance.prepare.overtime-employees');
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=OvertimeCalculateFilterTest`
Expected: PASS (5 test).

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/OvertimeCalculateFilterTest.php app/Modules/Attendance/Controllers/Api/V1/AttendanceApiController.php app/Modules/Attendance/Routes/api.php
git commit -m "feat(attendance): add overtime employee picker endpoint"
```

---

### Task 2: Backend — saring `hitung-lembur` dengan `employee_ids`

**Files:**
- Modify: `tests/Feature/OvertimeCalculateFilterTest.php` (tambah test)
- Modify: `app/Modules/Attendance/Controllers/Api/V1/AttendanceApiController.php:390-411` (`prepareHitungLembur`)
- Modify: `app/Modules/Attendance/Services/AttendanceService.php:527-555` (`bulkHitungLembur`)

**Interfaces:**
- Consumes: `AttendancePrepare` (tabel `att_prepares`), endpoint dari Task 1 tidak dipakai di sini.
- Produces: `POST /api/v1/attendance/prepare/hitung-lembur` menerima body `{ start_date, end_date, employee_ids?: [int] }`. `AttendanceService::bulkHitungLembur(string $startDate, string $endDate, AttendanceCalculatorService $calculator, ?array $employeeIds = null): array`, balikan tetap `{ updated: int, skipped: int }`.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan import `use App\Modules\Attendance\Models\AttendancePrepare;` di bagian atas file test, lalu tambahkan konstanta dan test berikut di dalam kelas:

```php
private const HITUNG_URL = '/api/v1/attendance/prepare/hitung-lembur';

private function prepare(Employee $employee, string $date): AttendancePrepare
{
    return AttendancePrepare::create([
        'employee_id'   => $employee->id,
        'date'          => $date,
        'periode_start' => '2026-10-01',
        'periode_end'   => '2026-10-31',
        'check_in'      => $date . ' 08:00:00',
        'check_out'     => $date . ' 17:30:00',
        'status'        => 'hadir',
        'is_locked'     => false,
        'lm'            => 999,
        'lm_count'      => 999,
        'overtime'      => 999,
        'overtime_count' => 999,
    ]);
}

public function test_hitung_hanya_karyawan_terpilih(): void
{
    $budiPrepare = $this->prepare($this->budi, '2026-10-14');
    $sitiPrepare = $this->prepare($this->siti, '2026-10-15');

    $response = $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
        'start_date'   => self::START,
        'end_date'     => self::END,
        'employee_ids' => [$this->budi->id],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.updated', 1);
    $this->assertNotSame(999, $budiPrepare->fresh()->lm);
    $this->assertSame(999, $sitiPrepare->fresh()->lm);
}

public function test_tanpa_employee_ids_semua_dihitung(): void
{
    $budiPrepare = $this->prepare($this->budi, '2026-10-14');
    $sitiPrepare = $this->prepare($this->siti, '2026-10-15');

    $response = $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
        'start_date' => self::START,
        'end_date'   => self::END,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.updated', 2);
    $this->assertNotSame(999, $budiPrepare->fresh()->lm);
    $this->assertNotSame(999, $sitiPrepare->fresh()->lm);
}

public function test_employee_ids_kosong_ditolak_422(): void
{
    $budiPrepare = $this->prepare($this->budi, '2026-10-14');

    $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
        'start_date'   => self::START,
        'end_date'     => self::END,
        'employee_ids' => [],
    ])->assertStatus(422);

    $this->assertSame(999, $budiPrepare->fresh()->lm);
}

public function test_employee_ids_bukan_array_ditolak_422(): void
{
    $this->actingAs($this->user, 'sanctum')->postJson(self::HITUNG_URL, [
        'start_date'   => self::START,
        'end_date'     => self::END,
        'employee_ids' => 'A001',
    ])->assertStatus(422);
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=OvertimeCalculateFilterTest`
Expected: FAIL — `test_hitung_hanya_karyawan_terpilih` mendapat `data.updated` 2 (bukan 1); test 422 mendapat 200.

- [ ] **Step 3: Teruskan `employee_ids` dari controller**

Di `prepareHitungLembur`, ubah validasi dan pemanggilan service menjadi:

```php
$request->validate([
    'start_date'     => 'required|date',
    'end_date'       => 'required|date|after_or_equal:start_date',
    'employee_ids'   => 'nullable|array|min:1',
    'employee_ids.*' => 'integer',
]);

$calculator = new AttendanceCalculatorService();
$service    = new AttendanceService();
$result     = $service->bulkHitungLembur(
    $request->start_date,
    $request->end_date,
    $calculator,
    $request->input('employee_ids')
);
```

- [ ] **Step 4: Saring query di `bulkHitungLembur`**

Ubah signature dan query menjadi:

```php
public function bulkHitungLembur(string $startDate, string $endDate, AttendanceCalculatorService $calculator, ?array $employeeIds = null): array
{
    // Ambil semua record unlocked yang punya data scan lengkap.
    // $employeeIds null/kosong = seluruh karyawan (perilaku lama).
    $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
        ->where('is_locked', false)
        ->whereNotNull('check_in')
        ->whereNotNull('check_out')
        ->when(! empty($employeeIds), fn ($q) => $q->whereIn('employee_id', $employeeIds))
        ->get();
```

Sisa method, aturan skip cuti, dan bentuk balikan tidak berubah.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=OvertimeCalculateFilterTest`
Expected: PASS (9 test).

- [ ] **Step 6: Jalankan regresi modul attendance**

Run: `php artisan test --filter=Attendance`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
git add tests/Feature/OvertimeCalculateFilterTest.php app/Modules/Attendance/Controllers/Api/V1/AttendanceApiController.php app/Modules/Attendance/Services/AttendanceService.php
git commit -m "feat(attendance): limit overtime recalculation to selected employees"
```

---

### Task 3: Frontend — dropdown periode membatasi rentang tanggal

**Files:**
- Modify: `resources/js/Pages/Admin/Attendance/OvertimeCalculation/Index.vue` (kartu periode baris 72-110; state & fungsi di `<script setup>`)

**Interfaces:**
- Consumes: `GET /api/v1/settings/employee-data/pay-periods` → `{ data: [{ id, name, start_date, end_date }] }`, urut terbaru dulu; `useApi().get`; `useRoute()`/`useRouter()` yang sudah ada.
- Produces: query URL `period_id`, `start_date`, `end_date`; state `selectedPeriodId`, `selectedPeriod`, `periods`, fungsi `clampDates()`.

- [ ] **Step 1: Tambah import dan state periode**

Ubah baris import Vue menjadi `import { ref, computed, onMounted } from 'vue'`, lalu setelah `const searchQuery = ref(...)`:

```js
const periods = ref([])
const selectedPeriodId = ref(null)
const selectedPeriod = computed(
  () => periods.value.find(p => p.id === selectedPeriodId.value) || null
)
```

- [ ] **Step 2: Tambah `fetchPeriods`, `resolveInitialPeriod`, `clampDates`, `onPeriodChange`**

Di `<script setup>`, sebelum `applyPeriod`:

```js
async function fetchPeriods() {
  try {
    const res = await get('/api/v1/settings/employee-data/pay-periods')
    // Periode tanpa tanggal tidak bisa membatasi rentang — jangan ditawarkan.
    periods.value = (res.data || []).filter(p => p.start_date && p.end_date)
  } catch (e) {
    console.error('Gagal fetch periode:', e)
    periods.value = []
  }
}

function resolveInitialPeriod() {
  const today = new Date().toISOString().slice(0, 10)
  const queryPeriodId = Number(route.query.period_id) || null

  let period = queryPeriodId
    ? periods.value.find(p => p.id === queryPeriodId)
    : null

  if (!period && route.query.start_date) {
    period = periods.value.find(
      p => p.start_date <= route.query.start_date && p.end_date >= route.query.start_date
    )
  }
  if (!period) {
    period = periods.value.find(p => p.start_date <= today && p.end_date >= today)
  }
  if (!period && periods.value.length) {
    period = periods.value[0] // endpoint urut terbaru dulu
  }
  if (!period) return

  selectedPeriodId.value = period.id

  if (!route.query.start_date || !route.query.end_date) {
    startDate.value = period.start_date
    endDate.value = period.end_date
  } else {
    clampDates() // URL lama / dari halaman Detail: pertahankan, tarik ke batas
  }
}

function clampDates() {
  const period = selectedPeriod.value
  if (!period) return
  if (!startDate.value || startDate.value < period.start_date) {
    startDate.value = period.start_date
  }
  if (!endDate.value || endDate.value > period.end_date) {
    endDate.value = period.end_date
  }
  if (startDate.value > endDate.value) {
    endDate.value = startDate.value
  }
}

function onPeriodChange() {
  const period = selectedPeriod.value
  if (!period) return
  startDate.value = period.start_date
  endDate.value = period.end_date
  applyPeriod()
}
```

- [ ] **Step 3: Kirim `period_id` dan clamp di `applyPeriod`**

Ganti isi `applyPeriod` menjadi:

```js
function applyPeriod() {
  clampDates()
  const query = {
    ...route.query,
    start_date: startDate.value,
    end_date: endDate.value,
    search: searchQuery.value,
  }
  if (selectedPeriodId.value) query.period_id = selectedPeriodId.value
  router.push({ query })
  fetchData(1)
}
```

- [ ] **Step 4: Muat periode saat halaman dibuka**

Ganti `onMounted` menjadi:

```js
onMounted(async () => {
  await fetchPeriods()
  resolveInitialPeriod()
  fetchData()
})
```

- [ ] **Step 5: Render dropdown dan batasi input tanggal**

Di kartu periode, sisipkan blok berikut **sebelum** `<div>` berisi label "Tanggal Mulai":

```html
<div v-if="periods.length > 0">
  <label class="block text-xs font-medium text-(--text-muted) mb-1">
    <i class="bx bx-calendar-check mr-1"></i>
    Periode
  </label>
  <select
    v-model="selectedPeriodId"
    @change="onPeriodChange"
    class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
  >
    <option :value="null" disabled>Pilih periode</option>
    <option v-for="period in periods" :key="period.id" :value="period.id">
      {{ period.name }} ({{ period.start_date }} — {{ period.end_date }})
    </option>
  </select>
</div>
```

Pada kedua `<input type="date">`, tambahkan atribut:

```html
:min="selectedPeriod?.start_date"
:max="selectedPeriod?.end_date"
```

- [ ] **Step 6: Build frontend**

Run: `npm run build`
Expected: build selesai tanpa error.

- [ ] **Step 7: Verifikasi di aplikasi**

Buka `/admin/attendance/overtime-calculation`. Periksa:
- Dropdown periode terisi, default = periode yang memuat hari ini; tanggal mulai/akhir = batas periode itu.
- Ganti periode → tanggal ikut berganti dan tabel dimuat ulang.
- Tanggal di luar periode tidak bisa dipilih (picker dibatasi); mengetik manual lalu **Terapkan** → nilai ditarik ke batas periode.
- Buka halaman dengan URL lama (`?start_date=…&end_date=…` tanpa `period_id`, dari tombol kembali di Detail) → periode yang memuat tanggal itu terpilih.
- Bila tidak ada periode bertanggal (kosongkan data uji), dropdown tidak tampil dan halaman tetap bisa dipakai seperti sebelumnya.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Admin/Attendance/OvertimeCalculation/Index.vue
git commit -m "feat(attendance): constrain overtime calculation range to pay period"
```

---

### Task 4: Frontend — modal pilih karyawan sebelum kalkulasi

**Files:**
- Create: `resources/js/Pages/Admin/Attendance/OvertimeCalculation/EmployeePickerModal.vue`
- Modify: `resources/js/Pages/Admin/Attendance/OvertimeCalculation/Index.vue` (import; tombol Calculate baris 29-41; state & fungsi; modal di akhir template)

**Interfaces:**
- Consumes: `GET /api/v1/attendance/prepare/overtime-employees` (Task 1), `POST /api/v1/attendance/prepare/hitung-lembur` dengan `employee_ids` (Task 2), `BaseModal` (`show`, `title`, `size`, `@close`), `BaseButton` (`variant`, `loading`, `disabled`).
- Produces: tidak ada konsumen lain. Emit `confirm(employeeIds: number[])`.

- [ ] **Step 1: Buat `EmployeePickerModal.vue`**

```vue
<template>
  <BaseModal :show="show" title="Pilih Karyawan" size="lg" @close="emit('close')">
    <div class="space-y-3">
      <p class="text-sm text-(--text-muted)">
        <span v-if="periodLabel">{{ periodLabel }} · </span>{{ startDate }} — {{ endDate }}
      </p>

      <div class="relative">
        <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)"></i>
        <input
          v-model="search"
          type="text"
          placeholder="Cari kode atau nama karyawan..."
          class="w-full pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
        />
      </div>

      <div v-if="loading" class="py-8 text-center text-sm text-(--text-muted)">Memuat karyawan...</div>

      <div v-else-if="employees.length === 0" class="py-8 text-center text-sm text-(--text-muted)">
        Tidak ada karyawan yang punya roster pada rentang ini.
      </div>

      <template v-else>
        <label class="flex items-center gap-2 px-1 text-sm font-medium text-(--text-main)">
          <input type="checkbox" :checked="allFilteredSelected" @change="toggleSelectAll" />
          Pilih semua ({{ filteredEmployees.length }} tampil)
        </label>

        <div class="max-h-72 overflow-y-auto divide-y divide-(--border-soft) border border-(--border-soft) rounded-lg">
          <label
            v-for="employee in filteredEmployees"
            :key="employee.id"
            class="flex items-center gap-3 px-3 py-2 hover:bg-(--bg-elevated)/50 cursor-pointer"
          >
            <input v-model="selectedIds" type="checkbox" :value="employee.id" />
            <span class="text-sm text-(--text-main)">{{ employee.employee_code }} · {{ employee.name }}</span>
            <span class="text-xs text-(--text-muted) ml-auto">{{ employee.department || '-' }}</span>
          </label>
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
      </template>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <span class="text-sm text-(--text-muted)">
          {{ selectedIds.length }} dari {{ employees.length }} terpilih
        </span>
        <div class="flex gap-3">
          <BaseButton variant="ghost" :disabled="submitting" @click="emit('close')">Batal</BaseButton>
          <BaseButton
            variant="primary"
            :loading="submitting"
            :disabled="selectedIds.length === 0"
            @click="emit('confirm', [...selectedIds])"
          >
            Mulai Kalkulasi Lembur
          </BaseButton>
        </div>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import BaseModal from '@/Components/BaseModal.vue'
import BaseButton from '@/Components/BaseButton.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  periodLabel: { type: String, default: '' },
  startDate: { type: String, default: '' },
  endDate: { type: String, default: '' },
  employees: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
const emit = defineEmits(['close', 'confirm'])

const search = ref('')
const selectedIds = ref([])

const filteredEmployees = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.employees
  return props.employees.filter(e =>
    (e.employee_code || '').toLowerCase().includes(q) ||
    (e.name || '').toLowerCase().includes(q)
  )
})

// "Pilih semua" berlaku pada daftar yang sedang tampil (hasil pencarian).
const allFilteredSelected = computed(
  () => filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every(e => selectedIds.value.includes(e.id))
)

function toggleSelectAll() {
  const ids = filteredEmployees.value.map(e => e.id)
  if (allFilteredSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
  } else {
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...ids]))
  }
}

// Setiap daftar baru dimuat (modal dibuka ulang), semua tercentang.
watch(() => props.employees, (list) => {
  search.value = ''
  selectedIds.value = list.map(e => e.id)
}, { immediate: true })
</script>
```

- [ ] **Step 2: Tambah state dan fungsi di `Index.vue`**

Tambahkan import: `import EmployeePickerModal from './EmployeePickerModal.vue'`.

Setelah state `calculateResult`:

```js
const showPicker = ref(false)
const pickerEmployees = ref([])
const pickerLoading = ref(false)
const pickerError = ref('')
```

Ganti `handleCalculate()` dengan:

```js
async function openPicker() {
  if (!startDate.value || !endDate.value) {
    calculateResult.value = { success: false, message: 'Silakan pilih rentang tanggal terlebih dahulu.' }
    return
  }
  showPicker.value = true
  pickerError.value = ''
  await loadPickerEmployees()
}

async function loadPickerEmployees() {
  pickerLoading.value = true
  try {
    const res = await get(`/api/v1/attendance/prepare/overtime-employees?start_date=${startDate.value}&end_date=${endDate.value}`)
    pickerEmployees.value = res.data || []
  } catch (error) {
    pickerEmployees.value = []
    pickerError.value = error.message || 'Gagal memuat daftar karyawan.'
  } finally {
    pickerLoading.value = false
  }
}

async function confirmPicker(employeeIds) {
  if (!employeeIds.length) return
  isCalculating.value = true
  pickerError.value = ''
  calculateResult.value = null
  try {
    const res = await post('/api/v1/attendance/prepare/hitung-lembur', {
      start_date: startDate.value,
      end_date: endDate.value,
      employee_ids: employeeIds,
    })
    calculateResult.value = {
      success: res.success !== false,
      message: res.message || 'Hitung lembur selesai.',
    }
    showPicker.value = false
    fetchData(pagination.value.current_page)
  } catch (error) {
    // Modal tetap terbuka agar pilihan tidak hilang.
    pickerError.value = error.response?.data?.message || error.message || 'Gagal mengalkulasi.'
  } finally {
    isCalculating.value = false
  }
}
```

- [ ] **Step 3: Tombol Calculate membuka modal**

Pada `BaseButton` Calculate, ganti `@click="auth.isManajemen ? null : handleCalculate()"` menjadi:

```html
@click="auth.isManajemen ? null : openPicker()"
```

- [ ] **Step 4: Render modal di akhir template**

Setelah `<BaseModal :show="showSettings" ...>` (modal Setting) dan sebelum `</div>` penutup halaman:

```html
<EmployeePickerModal
  :show="showPicker"
  :period-label="selectedPeriod?.name || ''"
  :start-date="startDate"
  :end-date="endDate"
  :employees="pickerEmployees"
  :loading="pickerLoading"
  :submitting="isCalculating"
  :error="pickerError"
  @close="showPicker = false"
  @confirm="confirmPicker"
/>
```

- [ ] **Step 5: Build frontend**

Run: `npm run build`
Expected: build selesai tanpa error.

- [ ] **Step 6: Verifikasi di aplikasi**

Buka `/admin/attendance/overtime-calculation`, pilih periode, klik **Calculate Overtime**. Periksa:
- Modal terbuka, daftar = karyawan yang punya roster di rentang itu (bukan seluruh karyawan aktif), semua tercentang.
- Cari nama → daftar menyempit; **Pilih semua** hanya mencentang hasil pencarian; melepas centang lalu membuka modal ulang mengembalikan semua tercentang.
- Kosongkan semua centang → **Mulai Kalkulasi Lembur** nonaktif.
- Mulai kalkulasi → modal tertutup, banner hijau "… record dihitung", tabel dimuat ulang. Bandingkan kolom LM/Lembur satu karyawan terpilih vs karyawan yang tidak dipilih: hanya yang dipilih berubah.
- Panggil dengan pilihan yang memicu 422 (mis. lewat devtools kirim `employee_ids: []`) → modal tetap terbuka dan pesan error tampil di dalamnya.
- Rentang tanpa roster → modal menampilkan "Tidak ada karyawan yang punya roster pada rentang ini."
- Banner lama (Setting → simpan) dan tombol Export/Export Roster tetap berfungsi.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Admin/Attendance/OvertimeCalculation/EmployeePickerModal.vue resources/js/Pages/Admin/Attendance/OvertimeCalculation/Index.vue
git commit -m "feat(attendance): pick employees before calculating overtime"
```
