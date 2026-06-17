# Report Configs System — Detailed Implementation Plan

> **Status:** Phase 0 ✅ | Phase 1 ✅ | Phase 2 ✅ | Phase 3 ⏳ | Phase 4-11 ⏳
> **Last updated:** 2026-06-17
> **Urutan pengerjaan:** Phase 0 → Phase 1 → Phase 2 → Phase 3 → ... → Phase 11

---

## 📐 Arsitektur Keseluruhan

```
┌──────────────────────────────────────────────────────────────────┐
│                        report_configs                            │
│──────────────────────────────────────────────────────────────────│
│  report_type (PK slug)    employee_groups (JSON)   config (JSON) │
│──────────────────────────────────────────────────────────────────│
│  lembur_uang_makan   →    ["KABAG","KASHIFT",...]  {rates...}   │
│  absensi             →    ["GROUP_A","GROUP_B"]    {toleransi}   │
│  pph                 →    ["ALL"]                  {brackets}    │
│  ...                                                             │
└──────────────────────┬───────────────────────────────────────────┘
                       │
          ┌────────────▼─────────────┐
          │   ReportConfigService    │  ← Cache per report_type
          │   get() / set()          │
          └────────────┬─────────────┘
                       │
     ┌─────────────────┼──────────────────┐
     ▼                 ▼                  ▼
  Halaman           Halaman            Halaman
  Lembur&UM         Absensi            PPH
  ┌──────────┐     ┌──────────┐       ┌──────────┐
  │⚙️ Setting│     │⚙️ Setting│       │⚙️ Setting│
  └──────────┘     └──────────┘       └──────────┘
```

Tiap halaman laporan punya struktur:
```
┌──────────────────────────────────────────────┐
│  Judul Laporan                    [⚙️ Setting]│
│  Deskripsi singkat                           │
│──────────────────────────────────────────────│
│  [Filter Area: periode, dll]                 │
│──────────────────────────────────────────────│
│  [Tab: Resume | Detail | ...]                │
│  [Content Area]                              │
└──────────────────────────────────────────────┘
```

Klik ⚙️ Setting → modal:
```
┌──────────────────────────────────────────┐
│  ⚙️ Pengaturan Laporan: {nama}           │
│──────────────────────────────────────────│
│  👥 Group Karyawan:                      │
│  ☑ KABAG   ☑ KASHIFT   ☐ ALL IN   ...   │
│──────────────────────────────────────────│
│  🔧 Pengaturan Spesifik Laporan:         │
│  (form dinamis per laporan)              │
│──────────────────────────────────────────│
│  Last updated: ... by ...                │
│              [Batal]  [💾 Simpan]        │
└──────────────────────────────────────────┘
```

---

# PHASE 0: Tabel `report_configs` + Infrastruktur Backend

**Tujuan:** Bikin fondasi — tabel, model, service, API controller, seeder.

**Durasi estimasi:** ~30-45 menit

---

## 0.1 — Migration

**File:** `database/migrations/2026_06_16_000000_create_report_configs_table.php`

```php
Schema::create('report_configs', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->string('report_type', 50)->unique()->comment('Slug unik per laporan');
    $table->json('employee_groups')->nullable()->comment('Array group karyawan yang ditampilkan di laporan');
    $table->json('config')->nullable()->comment('Pengaturan spesifik per laporan');
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
```

**Kenapa `employee_groups` disimpan di sini (bukan global)?**

Setiap laporan bisa menampilkan group karyawan yang BERBEDA:
- Lembur & UM: KABAG, KASHIFT, ALL IN
- Absensi: Karyawan Jakarta, All In, Printing
- BPJS: semua group

Jadi `employee_groups` adalah **"group mana saja yang ditampilkan di laporan INI"**, bukan daftar semua group yang ada di sistem.

---

## 0.2 — Model

**File:** `app/Modules/Settings/Models/ReportConfig.php`

```php
<?php
namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReportConfig extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'employee_groups' => 'array',
        'config'          => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationship
    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
```

---

## 0.3 — Service Class

**File:** `app/Modules/Settings/Services/ReportConfigService.php`

```php
<?php
namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\ReportConfig;
use Illuminate\Support\Facades\Cache;

class ReportConfigService
{
    const CACHE_TTL = 86400; // 24 jam

    /**
     * Ambil 1 row report_config (dengan cache).
     */
    public function get(string $reportType): ?ReportConfig
    {
        return Cache::remember("report_config:{$reportType}", self::CACHE_TTL, function () use ($reportType) {
            return ReportConfig::where('report_type', $reportType)->first();
        });
    }

    /**
     * Ambil config JSON saja (udah di-decode jadi array).
     * Kalau ga ada di DB → fallback ke default.
     */
    public function getConfig(string $reportType): array
    {
        $row = $this->get($reportType);
        return $row?->config ?? $this->getDefaultConfig($reportType);
    }

    /**
     * Ambil employee_groups saja.
     */
    public function getGroups(string $reportType): array
    {
        $row = $this->get($reportType);
        return $row?->employee_groups ?? [];
    }

    /**
     * Simpan/update config.
     */
    public function set(string $reportType, array $data, ?int $userId = null): ReportConfig
    {
        $config = ReportConfig::updateOrCreate(
            ['report_type' => $reportType],
            [
                'employee_groups' => $data['employee_groups'] ?? [],
                'config'          => $data['config'] ?? [],
                'updated_by'      => $userId,
            ]
        );

        Cache::forget("report_config:{$reportType}");

        return $config;
    }

    /**
     * Default values — fallback kalo DB kosong.
     * Ini hardcode satu-satunya yang boleh ada.
     */
    public function getDefaultConfig(string $reportType): array
    {
        return match ($reportType) {
            'lembur_uang_makan' => [
                'KABAG'   => ['weekday' => 15000, 'sabtu_dua' => 55000, 'sabtu_full' => 110000, 'minggu_half' => 110000, 'minggu_full' => 220000],
                'KASHIFT' => ['weekday' => 15000, 'sabtu_dua' => 52522, 'sabtu_full' => 105000, 'minggu_half' => 105000, 'minggu_full' => 210000],
                'ALL IN'  => ['weekday' => 15000, 'sabtu_dua' => 50000, 'sabtu_full' => 100000, 'minggu_half' => 100000, 'minggu_full' => 200000],
            ],
            default => [],
        };
    }
}
```

**Cache strategy:**
- Cache key: `report_config:{report_type}`
- Invalidasi: setiap `set()` → `Cache::forget(...)`
- TTL 24 jam → data laporan jarang berubah, tapi tetep fresh

---

## 0.4 — API Controller

**File:** `app/Modules/Settings/Controllers/Api/V1/ReportConfigApiController.php`

```php
<?php
namespace App\Modules\Settings\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\ReportConfigService;
use Illuminate\Http\Request;

class ReportConfigApiController extends Controller
{
    public function __construct(
        private ReportConfigService $service
    ) {}

    /**
     * GET /v1/settings/report-configs
     * List semua report config yang ada.
     */
    public function index()
    {
        $configs = \App\Modules\Settings\Models\ReportConfig::all()
            ->map(fn($c) => [
                'report_type'     => $c->report_type,
                'employee_groups' => $c->employee_groups,
                'config'          => $c->config,
                'updated_by'      => $c->updatedBy?->name,
                'updated_at'      => $c->updated_at?->toDateTimeString(),
            ]);

        return response()->json(['data' => $configs]);
    }

    /**
     * GET /v1/settings/report-configs/{reportType}
     * Ambil 1 config. Kalo belum ada → return default.
     */
    public function show(string $reportType)
    {
        $row = $this->service->get($reportType);

        return response()->json([
            'report_type'     => $reportType,
            'employee_groups' => $row?->employee_groups ?? [],
            'config'          => $row?->config ?? $this->service->getDefaultConfig($reportType),
            'updated_by'      => $row?->updatedBy?->name,
            'updated_at'      => $row?->updated_at?->toDateTimeString(),
        ]);
    }

    /**
     * PUT /v1/settings/report-configs/{reportType}
     * Simpan config.
     */
    public function update(Request $request, string $reportType)
    {
        $validated = $request->validate([
            'employee_groups' => 'nullable|array',
            'employee_groups.*' => 'string',
            'config' => 'nullable|array',
        ]);

        $config = $this->service->set(
            $reportType,
            $validated,
            auth()->id()
        );

        return response()->json([
            'message'    => 'Pengaturan laporan disimpan.',
            'updated_by' => auth()->user()?->name,
            'updated_at' => $config->updated_at->toDateTimeString(),
        ]);
    }
}
```

---

## 0.5 — Routes

**File:** `app/Modules/Settings/Routes/api.php` (tambah di akhir)

```php
// Report Configs
Route::prefix('report-configs')->group(function () {
    Route::get('/',              [ReportConfigApiController::class, 'index']);
    Route::get('/{reportType}',  [ReportConfigApiController::class, 'show']);
    Route::put('/{reportType}',  [ReportConfigApiController::class, 'update']);
});
```

---

## 0.6 — Seeder

**File:** `database/seeders/ReportConfigSeeder.php`

```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Settings\Models\ReportConfig;

class ReportConfigSeeder extends Seeder
{
    public function run(): void
    {
        ReportConfig::updateOrCreate(
            ['report_type' => 'lembur_uang_makan'],
            [
                'employee_groups' => ['KABAG', 'KASHIFT', 'ALL IN'],
                'config' => [
                    'KABAG'   => ['weekday' => 15000, 'sabtu_dua' => 55000, 'sabtu_full' => 110000, 'minggu_half' => 110000, 'minggu_full' => 220000],
                    'KASHIFT' => ['weekday' => 15000, 'sabtu_dua' => 52522, 'sabtu_full' => 105000, 'minggu_half' => 105000, 'minggu_full' => 210000],
                    'ALL IN'  => ['weekday' => 15000, 'sabtu_dua' => 50000, 'sabtu_full' => 100000, 'minggu_half' => 100000, 'minggu_full' => 200000],
                ],
            ]
        );
    }
}
```

---

## 0.7 — Verifikasi Phase 0

```bash
php artisan migrate
php artisan db:seed --class=ReportConfigSeeder

# Test via API (setelah login):
curl -H "Authorization: Bearer {token}" http://localhost/api/v1/settings/report-configs/lembur_uang_makan
# Expected: JSON dengan employee_groups dan config
```

---

# PHASE 1: Template Halaman Laporan + Modal Setting

**Tujuan:** Bikin komponen dasar yang reusable untuk SEMUA halaman laporan #1 s/d #10.

**Durasi estimasi:** ~45-60 menit

---

## 1.1 — ReportPageLayout.vue

**File:** `resources/js/Components/ReportPage/ReportPageLayout.vue`

Wrapper halaman laporan. Semua halaman laporan pakai ini.

```vue
<template>
  <div class="report-page p-4">
    <!-- Header Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
      <div>
        <h1 class="text-xl font-bold text-(--text-primary)">{{ title }}</h1>
        <p v-if="description" class="text-sm text-(--text-muted) mt-1">{{ description }}</p>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <!-- Slot: extra actions (export, print, dll) -->
        <slot name="actions" />

        <!-- Tombol Setting -->
        <button
          @click="$emit('openSettings')"
          class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5"
          title="Pengaturan Laporan"
        >
          <i class="bx bx-cog text-lg"></i>
          <span class="hidden sm:inline">Setting</span>
        </button>
      </div>
    </div>

    <!-- Filter Area (opsional) -->
    <div v-if="$slots.filter" class="mb-4">
      <slot name="filter" />
    </div>

    <!-- Main Content -->
    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
});

defineEmits(['openSettings']);
</script>
```

---

## 1.2 — ReportSettingsModal.vue

**File:** `resources/js/Components/ReportPage/ReportSettingsModal.vue`

Modal pengaturan laporan. Dipakai semua laporan — isinya dinamis.

```vue
<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="$emit('close')">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto m-4">
        
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-(--border-soft)">
          <div class="flex items-center gap-3">
            <i class="bx bx-cog text-2xl text-(--text-muted)"></i>
            <div>
              <h2 class="text-lg font-semibold">Pengaturan Laporan</h2>
              <p class="text-sm text-(--text-muted)">{{ reportLabel }}</p>
            </div>
          </div>
          <button @click="$emit('close')" class="p-1.5 rounded-lg hover:bg-(--bg-hover)">
            <i class="bx bx-x text-xl"></i>
          </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-4 space-y-6">
          
          <!-- Loading -->
          <div v-if="loading" class="text-center py-8 text-(--text-muted)">
            <i class="bx bx-loader-alt animate-spin text-2xl"></i>
            <p class="mt-2">Memuat pengaturan...</p>
          </div>

          <template v-else>
            <!-- Section 1: Group Karyawan -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <i class="bx bx-group text-lg"></i> Group Karyawan yang Ditampilkan
              </h3>
              <p class="text-xs text-(--text-muted) mb-3">
                Pilih group karyawan yang akan muncul di laporan ini.
              </p>
              <div class="flex flex-wrap gap-3">
                <label
                  v-for="group in availableGroups"
                  :key="group"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors"
                  :class="selectedGroups.includes(group)
                    ? 'bg-blue-50 border-blue-300 dark:bg-blue-900/20 dark:border-blue-700'
                    : 'border-(--border-soft) hover:bg-(--bg-hover)'"
                >
                  <input
                    type="checkbox"
                    :value="group"
                    v-model="selectedGroups"
                    class="rounded"
                  />
                  <span class="text-sm font-medium">{{ group }}</span>
                </label>
              </div>
              <p v-if="selectedGroups.length === 0" class="text-xs text-amber-600 mt-2">
                ⚠️ Tidak ada group dipilih. Laporan mungkin kosong.
              </p>
            </div>

            <hr class="border-(--border-soft)" />

            <!-- Section 2: Config Spesifik Laporan (SLOT) -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <i class="bx bx-slider text-lg"></i> Pengaturan Spesifik
              </h3>
              <slot name="config" :config="localConfig" :update-config="updateLocalConfig" />
            </div>
          </template>

        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-(--border-soft) bg-(--bg-soft) rounded-b-xl">
          <div v-if="lastUpdated" class="text-xs text-(--text-muted)">
            Terakhir diubah: {{ lastUpdated }}
            <span v-if="lastUpdatedBy"> oleh {{ lastUpdatedBy }}</span>
          </div>
          <div v-else></div>

          <div class="flex items-center gap-2">
            <button
              @click="resetToDefault"
              class="px-3 py-2 text-sm text-(--text-muted) hover:text-red-600"
              :disabled="saving"
            >
              Reset Default
            </button>
            <button
              @click="$emit('close')"
              class="px-4 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover)"
              :disabled="saving"
            >
              Batal
            </button>
            <button
              @click="save"
              class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
              :disabled="saving"
            >
              <i v-if="saving" class="bx bx-loader-alt animate-spin mr-1"></i>
              {{ saving ? 'Menyimpan...' : '💾 Simpan' }}
            </button>
          </div>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { get, put } from '@/composables/useApi';

const props = defineProps({
  reportType: { type: String, required: true },
  reportLabel: { type: String, required: true },
  availableGroups: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(true);
const saving = ref(false);
const selectedGroups = ref([]);
const localConfig = ref({});
const lastUpdated = ref('');
const lastUpdatedBy = ref('');

onMounted(async () => {
  try {
    const res = await get(`/settings/report-configs/${props.reportType}`);
    const data = res.data || res;
    selectedGroups.value = data.employee_groups || [];
    localConfig.value = data.config || {};
    lastUpdated.value = data.updated_at || '';
    lastUpdatedBy.value = data.updated_by || '';
  } catch (e) {
    console.error('Gagal memuat pengaturan:', e);
  } finally {
    loading.value = false;
  }
});

function updateLocalConfig(newConfig) {
  localConfig.value = { ...localConfig.value, ...newConfig };
}

async function save() {
  saving.value = true;
  try {
    const res = await put(`/settings/report-configs/${props.reportType}`, {
      employee_groups: selectedGroups.value,
      config: localConfig.value,
    });
    lastUpdated.value = res.updated_at || new Date().toLocaleString();
    lastUpdatedBy.value = res.updated_by || '';
    emit('saved', { employee_groups: selectedGroups.value, config: localConfig.value });
  } catch (e) {
    console.error('Gagal menyimpan:', e);
    alert('Gagal menyimpan pengaturan.');
  } finally {
    saving.value = false;
  }
}

function resetToDefault() {
  if (!confirm('Reset ke pengaturan default? Perubahan yang belum disimpan akan hilang.')) return;
  // Re-fetch tanpa row → backend akan return default
  selectedGroups.value = [];
  localConfig.value = {};
}
</script>
```

---

## 1.3 — Cara Pakai di Halaman Laporan

Contoh di halaman Lembur & Uang Makan:

```vue
<template>
  <ReportPageLayout
    title="Lembur & Uang Makan"
    description="Rekapitulasi lembur dan uang makan karyawan"
    @openSettings="showSettings = true"
  >
    <!-- Filter Periode -->
    <template #filter>
      <LemburUangMakanFilter v-model="filter" @apply="fetchData" />
    </template>

    <!-- Actions tambahan di header (export, dll) -->
    <template #actions>
      <ExportButton @click="exportExcel" />
    </template>

    <!-- Konten Utama -->
    <TabResume :data="resumeData" />
    <TabDetail :data="detailData" />
    <TabHarian :data="harianData" />

    <!-- Modal Setting -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="lembur_uang_makan"
      report-label="Lembur & Uang Makan"
      :available-groups="['KABAG', 'KASHIFT', 'ALL IN', 'Default']"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    >
      <template #config="{ config, updateConfig }">
        <LemburUangMakanSettingsTable
          :rates="config"
          @update="updateConfig"
        />
      </template>
    </ReportSettingsModal>
  </ReportPageLayout>
</template>
```

---

## 1.4 — Verifikasi Phase 1

- Buka halaman Lembur & Uang Makan
- Header muncul dengan judul + tombol ⚙️ Setting
- Klik Setting → modal terbuka
- Group checkboxes muncul
- Slot config kosong (belum ada isi per laporan)
- Klik Batal → modal tertutup

---

# PHASE 2: Laporan #1 — Lembur & Uang Makan (LENGKAP)

**Tujuan:** Halaman laporan lengkap + setting + migrasi hardcode.

**Durasi estimasi:** ~60-90 menit

---

## 2.1 — LemburUangMakanSettingsTable.vue

**File:** `resources/js/Components/ReportPage/settings/LemburUangMakanSettingsTable.vue`

Tabel rate editor untuk Lembur & Uang Makan.

```
┌──────────┬──────────┬───────────┬────────────┬─────────────┬─────────────┐
│ Group    │ Weekday  │ Sabtu 2j  │ Sabtu Full │ Minggu Half │ Minggu Full │
├──────────┼──────────┼───────────┼────────────┼─────────────┼─────────────┤
│ KABAG    │ [15000]  │ [55000]   │ [110000]   │ [110000]    │ [220000]    │
│ KASHIFT  │ [15000]  │ [52522]   │ [105000]   │ [105000]    │ [210000]    │
│ ALL IN   │ [15000]  │ [50000]   │ [100000]   │ [100000]    │ [200000]    │
└──────────┴──────────┴───────────┴────────────┴─────────────┴─────────────┘
```

Setiap cell editable number input. Ketik → langsung update local state.

---

## 2.2 — Refactor Halaman Laporan (existing → new)

**File:** `resources/js/Pages/Admin/Reports/LemburUangMakan/Index.vue`

Yang diubah:
1. Bungkus dengan `<ReportPageLayout>`
2. Tambah state `showSettings`
3. Tambah `<ReportSettingsModal>` dengan `<LemburUangMakanSettingsTable>`
4. `@saved` → `fetchData()` reload data laporan

---

## 2.3 — Migrasi Hardcode Backend → DB

### File 1: `UangMakanReportController.php`

**Hapus method:**
```php
// ❌ HAPUS — line 503-541
private function getGroupRates(string $groupName, float $gajiPokok): array
{
    // ... 40 lines hardcode
}
```

**Ganti semua pemanggilan:**
```php
// ❌ Before:
$rates = $this->getGroupRates($groupName, $gajiPokok);

// ✅ After:
$rates = app(ReportConfigService::class)->getConfig('lembur_uang_makan');
$groupRates = $rates[$groupName] ?? $rates['ALL IN'] ?? [];
```

### File 2: `LaporanLemburController.php`

**Hapus method:**
```php
// ❌ HAPUS — line 1224-1262
private function getUangMakanRates(string $groupName, float $gajiPokok): array
{
    // ... 40 lines hardcode — IDENTIK
}
```

**Ganti dengan service call yang sama.**

### Hardcode `15000` spot:

| Lokasi | Diganti dengan |
|---|---|
| `UangMakanReportController:605` — `$nominal = 15000;` | `$rates['weekday'] ?? 15000` (dari config) |
| `LaporanLemburController:990` — `$uangMakanNominal = 15000;` | `$umRates['weekday'] ?? 15000` |
| `UangMakanReportController:506` — `$rateWeekday = 15000;` | dihapus (method dibuang) |
| `LaporanLemburController:1227` — `$rateWeekday = 15000;` | dihapus (method dibuang) |

---

## 2.4 — Verifikasi Phase 2

1. Buka halaman Lembur & Uang Makan → data tampil normal
2. Klik ⚙️ Setting → modal muncul
3. Group checkboxes: KABAG, KASHIFT, ALL IN tercentang
4. Rate table menampilkan nilai dari seeder
5. Ubah rate KABAG weekday 15000 → 20000, klik Simpan
6. Tutup modal, refresh data laporan → perhitungan pakai rate baru (20000)
7. Buka modal lagi → nilai 20000 tersimpan

---

# PHASE 3: Laporan #2 — Absensi (Kehadiran)

**Tujuan:** Refactor halaman Kehadiran/Index.vue pakai ReportPageLayout + modal Setting. Tambah kolom lm_count / overtime_count per tanggal dari att_prepare.

**Durasi estimasi:** ~60-90 menit

**Slug:** `absensi`

**Kelompok group:** 3 kelompok (seperti section Lembur & UM):
- **Jakarta:** `GRP-JKT`
- **ALL IN:** `GRP-ALLIN`, `GRP-GD`, `GRP-SPR`
- **Printing:** `GRP-PS1`, `GRP-SS`

---

## 3.1 — Refactor Kehadiran/Index.vue

**File:** `resources/js/Pages/Admin/Reports/Kehadiran/Index.vue`

Yang diubah:
1. Bungkus dengan `<ReportPageLayout>`
2. Tambah state `showSettings`
3. Tambah `<ReportSettingsModal>` 
4. Group dari `report_config` API (bukan hardcode GRP-PS1)
5. Hapus filter checkbox inline di halaman — pindah ke modal Setting
6. Tambah handler Print & Export Excel (yang sekarang cuma tombol kosong)

---

## 3.2 — Matrix Roster: Tambah Kolom Count per Tanggal

Per tanggal di matrix roster, tambah **1 kolom baru** (jadi total 2 kolom per tanggal):
- Kolom **Status** (tetap): H/A/L/C/I/S/Off (seperti sekarang)
- Kolom **Count**: `overtime_count` (hari kerja) atau `lm_count` (Minggu/holiday), `-` jika 0

**Sumber data:** tabel `att_prepares` → field `overtime_count` & `lm_count`

```
┌──────┬───────┬─────────────┬─────────────┬─────────────┐
│ NIP  │ Nama  │  1 (Sen)    │  2 (Sel)    │  3 (Min)    │
│      │       │  H  │  3.5  │  A  │   -   │  L  │  1.5  │
└──────┴───────┴─────┴───────┴─────┴───────┴─────┴───────┘
                    ↑       ↑
                 Status   Count (hari kerja: overtime_count,
                                 minggu/holiday: lm_count,
                                 0 → "-")
```

---

## 3.3 — Settings Component (AbsensiSettings.vue)

**File:** `resources/js/Components/ReportPage/settings/AbsensiSettings.vue`

Config spesifik untuk absensi — saat ini mungkin hanya group selection (config JSON bisa kosong atau berisi toleransi jam nantinya).

---

## 3.4 — Backend: Cek & Migrasi Hardcode

Cek apakah ada hardcode di backend controller laporan kehadiran yang perlu dimigrasi ke `ReportConfigService`.

---

## 3.5 — Verifikasi Phase 3

1. Buka halaman Kehadiran → header + tombol ⚙️ Setting muncul
2. Klik Setting → modal dengan group checkboxes
3. Group tercentang sesuai config (default: 3 kelompok)
4. Matrix roster muncul + kolom lm_count/overtime_count
5. Ubah group → simpan → reload → data sesuai
6. Print & Export berfungsi

---

# PHASE 4-11: Laporan #3 s/d #10

| Phase | # | Slug | Laporan | Kelompok Group |
|-------|---|------|---------|----------------|
| 4 | 3 | `bpjs` | BPJS | ? |
| 5 | 4 | `pph` | Rekap PPH | ? |
| 6 | 5 | `cortax` | Cortax | ? |
| 7 | 6 | `payroll` | Payroll + Resume | ? |
| 8 | 7 | `rekap_gaji` | Rekap Gaji | ? |
| 9 | 8 | `kompensasi` | Kompensasi | ? |
| 10 | 9 | `kerja` | Laporan Kerja | ? |
| 11 | 10 | `gaji_kus` | Gaji Kus | ? |

*(Detail per phase menyusul setelah Phase 3 selesai)*

---

# 📋 Open Questions

1. **`availableGroups`**: Dari mana daftar group checkbox diambil? Hardcode per laporan? Atau ambil dari master `employee_group_masters` table?

2. **Group yang TIDAK dicentang**: Apa efeknya? Data karyawan dari group itu di-skip (tidak muncul di laporan) atau tetap muncul tapi tanpa perhitungan?

3. **Reset Default**: Reset ke default service (hardcode) atau reset ke nilai terakhir yang di-seed?

---

# 📝 Actual Implementation Notes (Phase 0-2)

## Files Created

| Phase | File | Notes |
|---|---|---|
| 0 | `database/migrations/2026_06_16_000000_create_report_configs_table.php` | Tabel report_configs |
| 0 | `app/Modules/Settings/Models/ReportConfig.php` | Casts: employee_groups array, config array. Relationship: `updatedBy()` → `App\Modules\Auth\Models\User` |
| 0 | `app/Modules/Settings/Services/ReportConfigService.php` | Cache 24h, `getFull()` buat API, `getDefaultConfig()` fallback |
| 0 | `app/Modules/Settings/Controllers/Api/V1/ReportConfigApiController.php` | GET index, GET show, PUT update |
| 0 | `app/Modules/Settings/Routes/api.php` | 3 route di `v1/settings/report-configs` |
| 0 | `database/seeders/ReportConfigSeeder.php` | Seed lembur_uang_makan dgn 5 group (GRP-JKT..SPR) + 3 sub-group rate |
| 1 | `resources/js/Components/ReportPage/ReportPageLayout.vue` | Props: title, description. Emit: openSettings. Slots: actions, filter, default |
| 1 | `resources/js/Components/ReportPage/ReportSettingsModal.vue` | Props: reportType, reportLabel, availableGroups. Emit: close, saved. Slot: config |
| 2 | `resources/js/Components/ReportPage/settings/LemburUangMakanSettingsTable.vue` | Rate matrix editor: rows=KABAG/KASHIFT/ALL IN, cols=5 rate types |
| 2 | `resources/js/Pages/Admin/Reports/LemburUangMakan/Index.vue` | **Refactored:** pakai ReportPageLayout + modal setting. Group dari report_config API. Filter checkbox di halaman DIHAPUS. |

## Files Modified

| File | Change |
|---|---|
| `UangMakanReportController.php` | `getGroupRates()` (line 503-541) dihapus, ganti panggil `ReportConfigService`. `$nominal = 15000` → `$rates['weekday'] ?? 15000` |
| `LaporanLemburController.php` | `getUangMakanRates()` (line 1224-1262) dihapus, ganti service. `$uangMakanNominal = 15000` → `$umRates['weekday'] ?? 15000` |

## 🐛 Pitfalls & Fixes

| Bug | Cause | Fix |
|---|---|---|
| `__PHP_Incomplete_Class` | `Cache::remember()` nyimpen Model object → serialized class ga ke-load pas unserialize | Cache simpan **raw attributes** (`getAttributes()`), re-hydrate dgn `setRawAttributes()` + `$model->exists = true` |
| `json_decode() array given` | `toArray()` nge-cast JSON→array, tapi `setRawAttributes` expect string JSON | Pindah ke `getAttributes()` (raw, sebelum cast) |
| `Class "App\Models\User" not found` | Namespace User salah | Fix ke `App\Modules\Auth\Models\User` |
| Group filter checkbox ga muncul | Filter BaseCard dihapus dari Index.vue per request Sigit | Group sekarang di-load dari `report_configs.employee_groups` via API |
| Excel export corrupt / ga bisa dibuka | Matching logic `getUangMakanRates` / `getGroupRates` pakai loop `foreach ($config as $key)` — `"KEPALA SHIFT"` ga match `"KASHIFT"`, `"ALL-IN"` ga match `"ALL IN"` → rate jadi 0 → data corrupt | Fix: pakai alias matching eksplisit (`str_contains($upper, 'KEPALA SHIFT')` OR `'KASHIFT'`, dll) — persis kayak hardcode lama |

## UI Flow (Final)

```
Page mount
  ├─ GET /api/v1/settings/employee-data/groups → availableGroups (modal checkboxes)
  └─ GET /api/v1/settings/report-configs/lembur_uang_makan → employee_groups → selectedGroups
                                                                      │
                                                        TabDetail / TabResume
                                                        :groups="selectedGroups"
```

Halaman Lembur & UM sekarang:
- Header + tombol ⚙️ Setting (via ReportPageLayout)
- Tab Detail / Resume
- **Tidak ada** filter checkbox group inline — pindah ke modal Setting
- Modal Setting: group checkboxes + rate editor table

4. **Multiple config per report**: Ada kemungkinan 1 laporan butuh LEBIH dari 1 row config? Misal BPJS: satu row untuk BPJS Kesehatan, satu row untuk BPJS Ketenagakerjaan? Atau semua dijadiin 1 JSON besar?
