# Manual Sync Attendance — Implementation Plan

> **For Hermes:** Use subagent-driven-development skill to implement this plan task-by-task.

**Goal:** Halaman Manual Sync untuk memilih check_in/check_out secara manual dari data att_raw_logs, dengan auto-detect berdasarkan work_pattern & shift, lalu simpan ke tabel att_manual_detect.

**Architecture:** Backend API ambil employee + roster + raw_logs (window 05:50-08:00 besok), jalankan auto-detect pakai AttendanceSyncService detector yang sudah ada, return ke frontend. Frontend tampilkan tabel dengan radio/select Jam Scan, WP, Shift, hasil auto-detect. User bisa override, lalu Save All simpan ke att_manual_detect.

**Tech Stack:** Laravel (migration, model, controller, service), Vue 3 SPA + Pinia, MySQL

---

## Task 1: Migration — Buat tabel att_manual_detect

**Objective:** Buat migration untuk tabel att_manual_detect dengan struktur yang sudah disepakati.

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHmmss_create_att_manual_detect_table.php`

**Struktur Tabel:**

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigIncrements | PK |
| uuid | string(36) | UUID unique |
| employee_id | unsignedBigInteger | FK ke employees |
| date | date | Tanggal absensi |
| roster_id | unsignedBigInteger | FK ke sch_employee_shift_rosters |
| work_pattern_id | unsignedBigInteger | FK ke sch_work_patterns |
| work_pattern_type | string(30) | Denormalized: FIXED/SHIFT/dll |
| shift_id | unsignedBigInteger | FK ke sch_shifts |
| check_in | datetime | Final check_in |
| check_out | datetime | Final check_out |
| time_scan_result | json | Semua log + auto-detect info |
| status | string(20) | draft / perhatian / lengkap |
| created_by | unsignedBigInteger | FK users |
| updated_by | unsignedBigInteger | FK users |
| timestamps | — | created_at, updated_at |

**Index:** employee_id + date (unique composite), roster_id, status

**Step 1: Buat migration file**

```bash
php artisan make:migration create_att_manual_detect_table
```

**Step 2: Isi migration**

```php
Schema::create('att_manual_detect', function (Blueprint $table) {
    $table->id();
    $table->string('uuid', 36)->unique();
    $table->unsignedBigInteger('employee_id');
    $table->date('date');
    $table->unsignedBigInteger('roster_id')->nullable();
    $table->unsignedBigInteger('work_pattern_id')->nullable();
    $table->string('work_pattern_type', 30)->nullable();
    $table->unsignedBigInteger('shift_id')->nullable();
    $table->dateTime('check_in')->nullable();
    $table->dateTime('check_out')->nullable();
    $table->json('time_scan_result')->nullable();
    $table->string('status', 20)->default('draft');
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('updated_by')->nullable();
    $table->timestamps();

    $table->unique(['employee_id', 'date'], 'uq_employee_date');
    $table->index('roster_id');
    $table->index('status');
});
```

**Step 3: Jalankan migration**

```bash
php artisan migrate
```

**Verification:** Cek tabel `att_manual_detect` ada di database.

---

## Task 2: Model — ManualDetect

**Objective:** Buat Eloquent model untuk att_manual_detect dengan relation dan konstanta status.

**Files:**
- Create: `app/Modules/Attendance/Models/ManualDetect.php`

**Step 1: Buat model**

```php
<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManualDetect extends Model
{
    protected $table = 'att_manual_detect';

    protected $fillable = [
        'uuid', 'employee_id', 'date', 'roster_id',
        'work_pattern_id', 'work_pattern_type', 'shift_id',
        'check_in', 'check_out', 'time_scan_result', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'time_scan_result' => 'array',
    ];

    // Status constants
    const STATUS_DRAFT     = 'draft';
    const STATUS_PERHATIAN = 'perhatian';
    const STATUS_LENGKAP   = 'lengkap';

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // Relations
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function roster()
    {
        return $this->belongsTo(EmployeeShiftRoster::class, 'roster_id');
    }

    public function workPattern()
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
```

**Verification:** Model bisa di-load tanpa error — `php artisan tinker` → `App\Modules\Attendance\Models\ManualDetect::class`

---

## Task 3: Controller — ManualSyncController

**Objective:** Buat controller untuk endpoint API manual sync.

**Files:**
- Create: `app/Modules/Attendance/Controllers/Api/V1/ManualSyncController.php`

**Endpoint yang perlu:**

| Method | Path | Function |
|---|---|---|
| GET | `/api/v1/attendance/manual-sync/data` | Ambil data employee + roster + raw_logs + auto-detect |
| POST | `/api/v1/attendance/manual-sync/save` | Save semua hasil ke att_manual_detect |

**Step 1: Buat controller dengan 2 method utama**

```php
<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\ManualDetect;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Services\AttendanceSyncService;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManualSyncController extends Controller
{
    protected AttendanceSyncService $syncService;

    public function __construct(AttendanceSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * GET /api/v1/attendance/manual-sync/data
     *
     * Query params:
     *   - period_id    (required) → pay_periods.id
     *   - employee_id  (optional) → filter specific employee
     *
     * Response:
     *   data: [{ employee, date, raw_logs[], roster, work_pattern, shift, auto_detect }]
     */
    public function getData(Request $request)
    {
        // ...
    }

    /**
     * POST /api/v1/attendance/manual-sync/save
     *
     * Body: { items: [{ employee_id, date, check_in, check_out }] }
     */
    public function save(Request $request)
    {
        // ...
    }
}
```

**Step 2: Implementasi getData() — detail logic**

1. Validasi `period_id` required, ambil PayPeriod
2. Ambil semua EmployeeShiftRoster dalam rentang period (start_date s/d end_date) — eager load employee, shift, workPattern
3. Untuk setiap roster:
   - Ambil raw_logs per employee_code di window: `tanggal 05:50:00` s/d `besok 08:00:00`
   - Jalankan auto-detect pakai `AttendanceSyncService` — bikin method wrapper atau panggil `detectShiftWorker` via reflection/public wrapper
   - Format hasil untuk frontend
4. Return JSON

**PENTING:** `detectShiftWorker()` adalah protected method. Kita perlu bikin public wrapper di AttendanceSyncService:

```php
// Tambahkan di AttendanceSyncService
public function detectForManualSync(
    Collection $logs,
    ?Shift $shift,
    string $dateStr,
    bool $isHoliday,
    bool $isSunday,
    string $workPatternType
): array {
    return match ($workPatternType) {
        'FIXED'       => $this->detectFixed($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'FLEX-SHIFT'  => $this->detectFlexShift($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'SHIFT'       => $this->detectShift($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'LONGSHIFT'   => $this->detectLongshift($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'SPLIT'       => $this->detectSplit($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'FLEXI'       => $this->detectFlexi($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'HOURLY'      => $this->detectHourly($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'ON_CAL'      => $this->detectOnCall($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        'SEASONAL'    => $this->detectSeasonal($logs, $shift, $dateStr, $isHoliday, $isSunday, /* roster dummy */),
        default       => $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday),
    };
}
```

**CATATAN:** Semua detector butuh `EmployeeShiftRoster $roster` sebagai parameter terakhir. Untuk manual sync kita perlu bikin roster "dummy" dari data yang ada, atau refactor kecil agar detector bisa terima `work_pattern_type` langsung tanpa roster. Karena detector saat ini hanya pakai roster untuk `work_pattern_type` (`detectFlexShift` juga akses `$roster->external_code`), kita bisa passing roster asli karena kita memang punya roster-nya.

**Step 3: Implementasi save() — detail logic**

1. Validasi input array items
2. Loop setiap item:
   - Cari existing record berdasarkan employee_id + date
   - Update atau create record di att_manual_detect
   - Tentukan status: `lengkap` jika check_in & check_out ada, `perhatian` jika salah satu null
3. Return success dengan count

**Verification:** Test endpoint via Postman/curl setelah route didaftarkan.

---

## Task 4: Route — Daftarkan API routes

**Objective:** Tambahkan route untuk manual-sync di api.php attendance module.

**Files:**
- Modify: `app/Modules/Attendance/Routes/api.php`

**Step 1: Tambahkan route group**

```php
// Di dalam prefix('v1')->middleware(['auth:sanctum']) group

// ========== MANUAL SYNC (Manual Detect) ==========
Route::prefix('attendance/manual-sync')->group(function () {
    Route::get('/data', [ManualSyncController::class, 'getData'])
        ->name('attendance.manual-sync.data');
    Route::post('/save', [ManualSyncController::class, 'save'])
        ->name('attendance.manual-sync.save');
});
```

**Step 2: Import controller di bagian atas**

```php
use App\Modules\Attendance\Controllers\Api\V1\ManualSyncController;
```

**Verification:** `php artisan route:list | grep manual-sync` — lihat route terdaftar.

---

## Task 5: Frontend — Rewrite ManualSync.vue

**Objective:** Ganti total isi ManualSync.vue dengan UI baru sesuai desain.

**Files:**
- Modify: `resources/js/Pages/Admin/Attendance/ManualSync.vue`

**Step 1: Struktur komponen**

Komponen baru terdiri dari:
1. **Header** — judul "Manual Sync"
2. **Filter Bar:**
   - Dropdown periode (pay_periods)
   - Dropdown/search karyawan
   - Rentang tanggal (start_date — end_date, auto dari period)
3. **Tabel Data:**
   - Kolom: NIP | Nama | Jam Scan | WP | Shift | Auto Detect | Action
   - Setiap row = 1 employee + 1 date
   - Kolom "Jam Scan" berisi list radio/select untuk check_in & check_out
   - Auto Detect = badge menunjukkan hasil deteksi otomatis
   - Action = tombol reset (kembalikan ke auto-detect) — opsional
4. **Tombol Save All** di bawah tabel

**Step 2: Kolom "Jam Scan" — desain per row**

Untuk setiap row, tampilkan list scan time yang tersedia:

```
┌─────────────────────────────────────────────┐
│ ☉ Check In:                                  │
│   ○ 06:30:15  (auto-detect ✓)               │
│   ○ 07:15:42                                 │
│   ○ — (tidak ada)                            │
│                                              │
│ ☉ Check Out:                                 │
│   ○ 16:45:10  (auto-detect ✓)               │
│   ○ 17:30:05                                 │
│   ○ — (tidak ada)                            │
└─────────────────────────────────────────────┘
```

Atau compact version:

```
Check In:  [dropdown: 06:30 ✓ | 07:15 | — ]
Check Out: [dropdown: 16:45 ✓ | 17:30 | — ]
```

**SARAN:** Pakai dropdown select, lebih compact dan nggak bikin tabel terlalu panjang.

**Step 3: Auto Detect badge**

```
<span class="badge">FIXED → In: 06:30, Out: 16:45</span>
```

Atau kalo auto-detect gagal (kosong):
```
<span class="badge text-red">Tidak terdeteksi</span>
```

**Step 4: Script section — data flow**

```js
// State
const periodId = ref('')
const searchEmployee = ref('')
const syncData = ref([])  // array of { employee, date, logs[], roster, wp, shift, autoDetect, selectedIn, selectedOut }
const isSaving = ref(false)

// Fetch data
async function fetchData() {
  const res = await get('/api/v1/attendance/manual-sync/data', {
    period_id: periodId.value,
    employee_id: selectedEmployeeId.value || null,
  })
  syncData.value = res.data.map(item => ({
    ...item,
    selectedIn: item.auto_detect?.check_in_log_id || null,
    selectedOut: item.auto_detect?.check_out_log_id || null,
    status: item.existing_record?.status || 'draft',
  }))
}

// Save All
async function saveAll() {
  const payload = {
    items: syncData.value.map(item => ({
      employee_id: item.employee.id,
      date: item.date,
      roster_id: item.roster?.id,
      work_pattern_id: item.wp?.id,
      work_pattern_type: item.wp?.employee_type,
      shift_id: item.shift?.id,
      check_in_log_id: item.selectedIn,
      check_out_log_id: item.selectedOut,
      time_scan_result: {
        all_logs: item.logs,
        auto_detect: item.autoDetect,
      },
    })),
  }
  await post('/api/v1/attendance/manual-sync/save', payload)
}
```

**Step 5: Template structure**

```html
<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1>Manual Sync</h1>
    </div>

    <!-- Filter Bar -->
    <div class="bg-(--bg-card) border rounded-xl p-6 mb-6">
      <!-- Period dropdown -->
      <!-- Employee search -->
      <!-- Date range (readonly, auto-filled from period) -->
    </div>

    <!-- Table -->
    <div class="bg-(--bg-card) border rounded-xl p-6">
      <div class="flex justify-between mb-4">
        <h3>Data Scan</h3>
        <BaseButton variant="primary" :loading="isSaving" @click="saveAll">
          Save All
        </BaseButton>
      </div>

      <table>
        <thead>
          <tr>
            <th>NIP</th>
            <th>Nama</th>
            <th width="250">Jam Scan</th>
            <th>WP</th>
            <th>Shift</th>
            <th>Auto Detect</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in syncData" :key="idx">
            <td>{{ row.employee.nip }}</td>
            <td>{{ row.employee.name }}</td>
            <td>
              <!-- Check In select -->
              <select v-model="row.selectedIn">
                <option :value="null">--</option>
                <option v-for="log in row.logs" :value="log.id">
                  {{ formatTime(log.scan_datetime) }}
                </option>
              </select>
              <!-- Check Out select -->
              <select v-model="row.selectedOut">
                <option :value="null">--</option>
                <option v-for="log in row.logs" :value="log.id">
                  {{ formatTime(log.scan_datetime) }}
                </option>
              </select>
            </td>
            <td>{{ row.wp?.employee_type }}</td>
            <td>{{ row.shift?.external_code || row.shift?.code }}</td>
            <td>
              <span v-if="row.autoDetect?.check_in_log_id">
                ✓ In: {{ formatTime(row.autoDetect.check_in_time) }}
                Out: {{ formatTime(row.autoDetect.check_out_time) }}
              </span>
              <span v-else class="text-red-500">Tidak terdeteksi</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
```

**Step 6: Styling & UX details**

- Loading state saat fetch data
- Empty state: "Tidak ada data roster untuk periode ini"
- Konfirmasi sebelum Save All: `confirm('Simpan semua data manual sync?')`
- Feedback setelah save: success/error banner
- Highlight row yang status-nya `perhatian` (warna kuning)
- Row yang sudah `lengkap` bisa dikasih centang hijau

**Verification:** Buka halaman di browser → pilih periode → tabel muncul → pilih check_in/check_out → Save All → cek DB att_manual_detect terisi.

---

## Task 6: AttendanceSyncService — Tambah public wrapper

**Objective:** Tambahkan method public `detectForManualSync()` ke AttendanceSyncService agar bisa dipanggil dari ManualSyncController.

**Files:**
- Modify: `app/Modules/Attendance/Services/AttendanceSyncService.php`

**Step 1: Tambahkan method (sebelum protected section)**

```php
/**
 * Public wrapper untuk auto-detect dari Manual Sync.
 * Detektor tidak butuh roster lengkap — kita passing data minimal.
 */
public function detectForManualSync(
    Collection $logs,
    ?Shift $shift,
    string $dateStr,
    bool $isHoliday,
    bool $isSunday,
    string $workPatternType
): array {
    // Bikin roster dummy dengan data minimal
    $roster = new EmployeeShiftRoster([
        'work_pattern_type' => $workPatternType,
        'external_code' => $shift?->external_code,
    ]);

    return match ($workPatternType) {
        'FIXED'       => $this->detectFixed($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'FLEX-SHIFT'  => $this->detectFlexShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'SHIFT'       => $this->detectShift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'LONGSHIFT'   => $this->detectLongshift($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'SPLIT'       => $this->detectSplit($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'FLEXI'       => $this->detectFlexi($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'HOURLY'      => $this->detectHourly($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'ON_CAL'      => $this->detectOnCall($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        'SEASONAL'    => $this->detectSeasonal($logs, $shift, $dateStr, $isHoliday, $isSunday, $roster),
        default       => $this->detectShiftWorker($logs, $shift, $dateStr, $isHoliday, $isSunday),
    };
}
```

**CATATAN PENTING:** `EmployeeShiftRoster` constructor menerima array fillable. Yang penting `work_pattern_type` dan `external_code` terisi, karena hanya itu yang diakses detector (selain dari $shift yang kita passing terpisah).

**Verification:** Panggil method dari tinker / test route.

---

## Task 7: Integration & Testing

**Objective:** Test full flow end-to-end.

**Step 1: Test endpoint getData**
```bash
curl -H "Authorization: Bearer <token>" \
  "http://instance1.uranop.com/api/v1/attendance/manual-sync/data?period_id=1"
```
Expected: JSON array berisi employee + roster + raw_logs + auto_detect result.

**Step 2: Test endpoint save**
```bash
curl -X POST -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"items": [{"employee_id":1,"date":"2026-07-05","check_in_log_id":101,"check_out_log_id":103,"roster_id":5,...}]}' \
  "http://instance1.uranop.com/api/v1/attendance/manual-sync/save"
```
Expected: Success response, record tersimpan di att_manual_detect.

**Step 3: Test UI**
- Buka `/attendance/manual-sync`
- Pilih periode
- Lihat tabel data
- Edit Jam Scan
- Klik Save All
- Verifikasi di database

**Step 4: Edge cases**
- Periode tanpa roster → pesan "Tidak ada data"
- Employee tanpa raw_logs → Jam Scan kosong, auto-detect kosong
- Save tanpa perubahan → tetap simpan
- Employee dengan banyak scan → semua tampil di dropdown
- Overnight shift → log next day (sampai 08:00) ikut diambil

---

## Summary — Urutan Eksekusi

| # | Task | Dependensi |
|---|---|---|
| 1 | Migration att_manual_detect | - |
| 2 | Model ManualDetect | Task 1 |
| 3 | Tambah detectForManualSync() di AttendanceSyncService | - |
| 4 | ManualSyncController (getData + save) | Task 2, 3 |
| 5 | Route API | Task 4 |
| 6 | Rewrite ManualSync.vue | Task 4, 5 |
| 7 | Integration testing | All |

---

## Open Questions / Notes

1. **Overnight merge:** `AttendanceSyncService.processRoster()` sudah handle overnight (merge next-day logs). Di manual sync kita ambil window 05:50-08:00 besok, jadi log overnight otomatis sudah include. Tapi untuk auto-detect overnight, method `findLogInWindow()` pakai `isOvernight=true` dan `check_out_overnight_start/end`. Ini sudah ter-handle oleh `detectShiftWorker()`.

2. **Holiday detection:** Di AttendanceSyncService ada `preloadHolidays()`. Untuk manual sync, kita harus panggil ini juga atau bisa langsung query Holiday model per tanggal (karena per employee per date).

3. **Performansi:** Jika 1 periode = 100 karyawan × 30 hari = 3000 row. Query raw_logs per employee per date bisa berat. Solusi: batch query per date (seperti yang sudah dilakukan `syncPeriod`).

4. **Save existing record:** Jika record udah ada (employee_id + date), kita update, bukan insert baru. Status bisa berubah dari `draft` → `lengkap` atau sebaliknya.

5. **Sidebar:** Route sudah ada di `resources/js/router/index.js:167` dan sidebar seharusnya sudah include. No changes needed.

---

**Plan siap dieksekusi.** Setiap task independen dan bisa dikerjakan berurutan.
