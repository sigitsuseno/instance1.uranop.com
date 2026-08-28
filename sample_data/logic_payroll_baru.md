# Logic Payroll Baru — Plan Endpoint Gaji Karyawan (2 Mode + Lifecycle)

**Tanggal:** 2026-08-07 (update: keputusan Open Items #1–#11)
**Status:** Plan — SPEC (keputusan sudah dikunci, siap implementasi)
**Berdasarkan kesepakatan:** obrolan 2026-08-07 (Sigit + Paijo)

---

## 1. FLOW LENGKAP (kesepakatan)

```
HR buka Payroll → Gaji Karyawan
  │
  ├─ Halaman KOSONG (belum pilih periode)
  │
  └─ Pilih periode
       │
       ├─ Muncul tombol [SIMPAN]
       │     └─ klik → SNAPSHOT tabel on_the_fly → pay_records (status: draft)
       │            • copy SEMUA kolom yang tampil (data masukan + hari_kerja + hitungan)
       │            • TANPA perhitungan ulang — murni menyimpan apa yang ada
       │
       ├─ now() <= end_date  →  tabel perhitungan ON_THE_FLY ditampilkan
       │       (hitungan live dari att_prepares; hari_kerja = count record − minggu − holiday − absent; pph 0)
       │
       └─ now() > end_date   →  muncul tombol [FINALISASI]
             └─ klik → finalisasi():
                   • hitung ulang dengan rumus SAMA (hari_kerja pakai hitungan LAMA:
                     fixed_days/25 − (izin tak dibayar + absent))
                   • pph dihitung, dsb.
                   • mode berubah: on_the_fly → ON_RECORD (baca dari pay_records)
             └─ lalu tombol [LOCK]
                   • semua fungsi & form CRUD pay_records MATI
                   • unlock: pakai password (plain text) yang tersimpan di setting
```

### Status lifecycle pay_records

```
draft ──(Simpan)──> generated ──(Lock)──> locked
                       ▲                    │
                       └──────(Unlock, password)──┘
```

> Data sekarang: status pay_records cuma `generated`. `draft` & `locked` perlu ditambahkan ke enum/validasi.

---

## 2. Endpoint

### 2.1 `GET /api/v1/payroll/gaji-karyawan?period_id=X&segment=Y`

**Satu endpoint, dua mode** — branch di `GajiKaryawanController::index()`:

```php
public function index(Request $request)
{
    $validated = $request->validate([
        'period_id' => 'required|exists:pay_periods,id',
        'segment'   => 'nullable|in:A,B',
    ]);

    $period = PayPeriod::findOrFail($validated['period_id']);
    $segment = $validated['segment'] ?? null;

    // end_date BELUM lewat (periode masih berjalan) → ON_THE_FLY
    if ($period->end_date && Carbon::today()->lte($period->end_date)) {
        return $this->onTheFly($period, $segment);   // hitung live, TIDAK baca pay_records
    }

    // end_date SUDAH lewat → ON_RECORD (baca pay_records)
    return $this->onRecord($period, $segment);
}
```

**Response:**

```json
{
  "mode": "on_the_fly" | "on_record",
  "period": { "id": ..., "name": ..., "is_split": ..., "segment": ..., "end_date": "Y-m-d" },
  "data": [ ... ]
}
```

`mode: "on_the_fly"` → UI tampilkan badge "ESTIMASI — periode belum berakhir".

### 2.2 `POST /api/v1/payroll/gaji-karyawan/simpan` — SNAPSHOT ON_THE_FLY

```json
// Request
{ "period_id": 12, "segment": null }

// Proses: TIDAK menghitung ulang apa pun.
// 1. Hitung data on_the_fly (hari_kerja, gaji, upah_lembur, gaji_bersih, dll — rumus on_the_fly)
// 2. Copy SEMUA kolom hasil on_the_fly itu ke pay_records apa adanya (status: draft)
for each karyawan (isGroupGaji() + activeInPeriod):
    PayRecord::updateOrCreate(
        { employee_id, pay_period_id, segment },
        {
            status: 'draft',
            // data masukan
            gaji_pokok, premi, tj_masa_kerja, tunjangan,
            bpjs_tk, bpjs_kes, bpjs_pen,
            // hasil hitungan on_the_fly (dicopy, bukan dihitung ulang)
            hari_kerja, lm, lm_count, lembur_count,
            gaji, upah_lembur, premi_hadir, revisi,
            gaji_kotor, pblt, gaji_bersih, pph(0), cashbon(0),
        }
    )
```

> Simpan = bekuin tampilan estimasi. Data di pay_records masih status `draft` & masih bisa diubah.

### 2.3 `POST /api/v1/payroll/gaji-karyawan/finalisasi` — HITUNG FINAL + GANTI MODE

```json
// Request
{ "period_id": 12, "segment": null }

// Prasyarat: now() > end_date; pay_records sudah ada (status draft/generated)
// Proses: hitung ulang dengan rumus LAMA (referensi recapApprove sekarang):
//   hari_kerja  = max(0, fixed_days(25) - (izin_tidak_dibayar + absent))
//   gaji        = (gaji_pokok / fixed_working_day) * hari_kerja
//   lm/lembur   = sum att_prepares (full periode)
//   upah_lembur = ceil(((gapok+tjmk+tunj)/173) * jam / 100) * 100
//   premi_hadir = (premi / fixed_working_day) * hari_kerja
//   pph         = PphCalculationService (dihitung beneran)
//   gaji_kotor  = gaji + tunjangan + upah_lembur + premi_hadir + revisi + tj_masa_kerja
//   pblt / gaji_bersih → pembulatan 100, rumus existing
// Lalu status: generated

// SETELAH finalisasi: mode berubah on_the_fly → ON_RECORD
// (UI mulai baca dari pay_records, bukan hitung live lagi)
```

### 2.4 `POST /api/v1/payroll/gaji-karyawan/lock`

```json
// Request
{ "period_id": 12, "segment": null }

// Proses: PayRecord::where(pay_period_id, segment)->update(['status' => 'locked'])
// Efek: semua PUT/CRUD pay_records periode ini ditolak (guard di controller)
// Hanya pay_records yang dikunci — att_records TIDAK ikut (#11)
```

### 2.5 `POST /api/v1/payroll/gaji-karyawan/unlock`

```json
// Request
{ "period_id": 12, "segment": null, "password": "..." }

// Proses: bandingkan password (PLAIN TEXT) dengan SystemSetting key payroll_lock_password (#10)
//   cocok  → status: generated (buka lock), boleh edit lagi
//   salah  → 403 "Password salah"
```

---

## 3. Mode ON_THE_FLY (sebelum end_date)

### 3.1 Filter karyawan — KEPUTUSAN #5: `isGroupGaji()`

```php
$employees = Employee::with(['department', 'position', 'groups'])
    ->activeInPeriod($period->start_date, $period->end_date)
    ->whereHas('shiftRosters', fn($q) => $q->whereBetween('date', [$period->start_date, $period->end_date]))
    ->get()
    ->filter(fn($emp) => $emp->isGroupGaji())   // whitelist 5 group (GRP-JKT otomatis tidak lolos)
    ->sortBy('no_urut')->sortBy('nip')
    ->values();
```

> Sama dengan filter mode final — konsisten di kedua mode.

### 3.2 Hari kerja — KEPUTUSAN #8: count record aktual

```php
$now = Carbon::today();

// Ambil semua record att_prepares karyawan (start_date → NOW)
$prepares = AttendancePrepare::where('employee_id', $emp->id)
    ->whereBetween('date', [$period->start_date, $now])
    ->get();

// Ambil daftar holiday periode (KEPUTUSAN #9: dari tabel holiday, rentang start–end periode)
$holidays = Holiday::whereBetween('date', [$period->start_date, $period->end_date])
    ->pluck('date')->map(fn($d) => $d->toDateString())->toArray();

$hariKerja = $prepares->filter(function ($p) use ($holidays) {
    $date = $p->date->toDateString();
    if (Carbon::parse($date)->isSunday()) return false;   // Minggu → skip
    if (in_array($date, $holidays)) return false;          // holiday → skip
    if ($p->status === 'absent') return false;             // absent → skip
    return true;
})->count();
```

⚠️ Perhatikan: ini **count record aktual**, bukan loop tanggal kalender. Record yang tidak ada di att_prepares (mis. belum di-sync) tidak dihitung.

### 3.3 Kolom lain — KEPUTUSAN #1, #2, #3

```php
$lm          = $prepares->sum('lm');
$lmCount     = $prepares->sum('lm_count');
$lemburCount = $prepares->sum('overtime_count');

$fixedDays   = (int) SystemSetting::where('key', 'payroll_config')->first()->fixed_working_day; // 25

$gaji        = round(($gajiPokok / $fixedDays) * $hariKerja, 2);   // #1: fixed_working_day
$premiHadir  = round(($premi / $fixedDays) * $hariKerja, 2);
$upahLembur  = ceil((($gajiPokok + $tjMasaKerja + $tunjangan) / 173) * ($lmCount + $lemburCount) / 60 / 100) * 100; // #3: round-up 100
$revisi      = 0;
$gajiKotor   = $gaji + $tunjangan + $upahLembur + $premiHadir + $revisi + $tjMasaKerja;
$pph         = 0;        // ← beda dari final
$cashbon     = 0;
// pblt & gaji_bersih → pembulatan 100, rumus existing
```

### 3.4 GRP-GD & zero overtime — KEPUTUSAN #4

GRP-GD **ikut di-0-kan** untuk lm, lm_count, lembur_count (seperti GRP-ALLIN):

```php
$zeroOvertimeGroups = ['GRP-ALLIN', 'GRP-GD'];          // #4: GRP-GD masuk
$isZeroOvertime = $emp->groups()->whereIn('reference_code', $zeroOvertimeGroups)->exists();

if ($isZeroOvertime) {
    $lm = 0; $lmCount = 0; $lemburCount = 0;
    $upahLembur = 0;
} else {
    // GRP-SPR: LBR JAM = 0, upah lembur hanya dari LM (perilaku existing)
    ...
}
```

### 3.5 Split periode (is_split = true)

Branching tetap dari `end_date` TOTAL periode. `hari_kerja` dihitung **per segmen**:

```
Segmen A: start_date → endOfMonth(start_date)   hk: config split A
Segmen B: startOfMonth(end_date) → end_date     hk: config split B
```

Untuk tiap segmen: `hari_kerja = count record att_prepares per segmen − Minggu − holiday − absent` (sama aturan 3.2, range per segmen).

- Segmen yang udah lewat (contoh: segmen A berakhir 31 Mei, hari ini 5 Juni) → dihitung full.
- Segmen yang masih jalan (segmen B) → dihitung sampai hari ini.
- Kolom lain per segmen sama seperti final (revisi A = -tj_mk, bpjs B, dst), pph = 0.

---

## 4. Mode ON_RECORD (sesudah end_date, sudah finalisasi)

Persis logika sekarang (`recapApprove` → `pay_records`, `index()` baca pay_records):

- Query `PayRecord::with(['employee.department', 'employee.position', 'employee.groups'])`
- Grouping section A/B by payroll config
- ExtraEmployee tetap masuk All-In

**Tidak ada perubahan kode di jalur ini** (kecuali komposisi gaji_kotor #2 kalau berlaku juga di sini — perlu keputusan tambahan, lihat Konflik #2).

---

## 5. Guard CRUD berdasarkan status

Semua endpoint mutasi `pay_records` harus cek status:

| Status    | Edit lembur | Transfer info | Bulk cabang | Finalisasi | Lock |
| --------- | ----------- | ------------- | ----------- | ---------- | ---- |
| draft     | ✅          | ✅            | ✅          | ✅         | —    |
| generated | ✅          | ✅            | ✅          | ✅         | ✅   |
| locked    | ❌          | ❌            | ❌          | ❌         | ❌   |

Guard di awal controller:

```php
private function ensureEditable(PayRecord $record): void
{
    abort_if($record->status === 'locked', 403, 'Payroll sudah dikunci.');
}
```

---

## 6. Dampak ke Endpoint Lain — KEPUTUSAN #6, #7

| Endpoint                               | Dampak                                                                                                    |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `GET gaji-karyawan/export`             | TETAP baca database (pay_records) — TIDAK berubah. HR diinstruksikan klik Simpan dulu sebelum export (#6) |
| `PUT gaji-karyawan/{id}/upah-lembur`   | Tambah guard `status !== locked`.                                                                         |
| `PUT gaji-karyawan/{id}/transfer-info` | Tambah guard `status !== locked`.                                                                         |
| `PUT gaji-karyawan/bulk-update-cabang` | Tambah guard `status !== locked`.                                                                         |
| `POST attendance/recap/generate`       | DIHAPUS dari UI (tombol ilang), fungsi DI-PERTAHANKAN sebagai referensi perhitungan (#7)                  |
| `POST attendance/recap/approve`        | DIHAPUS dari UI (tombol ilang), fungsi DI-PERTAHANKAN sebagai referensi perhitungan (#7)                  |
| `GET payroll/periods`                  | TIDAK berubah. UI butuh `end_date` — sudah ada.                                                           |

---

## 7. Flow Frontend (ringkas)

```
Pilih periode
  │
  ├─ Tombol [SIMPAN] (visible kalau belum ada pay_records / masih draft)
  │
  ├─ mode on_the_fly:
  │     • tabel estimasi + badge "ESTIMASI"
  │     • tombol Edit Lembur disabled
  │     • Export: aktif tapi tetap baca DB — HR disuruh Simpan dulu (#6)
  │     • kalau now > end_date → tombol [FINALISASI] muncul
  │
  └─ mode on_record:
        • tabel normal (baca pay_records)
        • tombol [LOCK]
        • kalau locked → semua form disabled + tombol [UNLOCK] (minta password)
```

---

## 8. KEPUTUSAN OPEN ITEMS (final, 2026-08-07)

| #   | Item                           | Keputusan                                                                        |
| --- | ------------------------------ | -------------------------------------------------------------------------------- |
| 1   | Pembagi `gaji` & `premi_hadir` | **`fixed_working_day`** (25)                                                     |
| 2   | Komposisi `gaji_kotor`         | **`gaji + tunjangan + upah_lembur + premi_hadir + revisi + tj_masa_kerja`**      |
| 3   | `upah_lembur`                  | **Round-up kelipatan 100** (`ceil(x/100)*100`)                                   |
| 4   | GRP-GD                         | **Ikut di-0-kan** (lm, lm_count, lembur_count) ⚠️ beda dgn kode sekarang         |
| 5   | Filter karyawan                | **`isGroupGaji()`** (whitelist 5 group), sama utk kedua mode                     |
| 6   | Export mode on_the_fly         | **Tetap baca database** — HR diinstruksikan Simpan dulu sebelum export           |
| 7   | Endpoint lama generate/approve | **Dipertahankan sebagai referensi perhitungan** — dihapus nanti kalau sudah klop |
| 8   | `hari_kerja` on_the_fly        | **Count record att_prepares − Minggu − holiday − absent** (bukan loop kalender)  |
| 9   | Sumber holiday                 | **Tabel `sch_holidays`**, rentang start–end periode terpilih                     |
| 10  | Password unlock                | **Plain text** di SystemSetting, hanya role **superadmin** yang bisa lihat/ubah  |
| 11  | Lock scope                     | **Hanya `pay_records`** — att_records tidak ikut                                 |

---

## 9. Konflik yang Perlu Konfirmasi Tambahan

1. **#2 gaji_kotor** — kode sekarang: `gaji + tj_mk + upah_lembur + revisi + premi_hadir + tunjangan`. Keputusan baru: `gaji + tunjangan + premi_hadir + revisi`. → upah_lembur & tj_mk TIDAK masuk TOTAL. **Apakah berlaku juga untuk mode on_record (final)?** Kalau ya, kolom LEMBUR & TJ MK cuma info, dan angka total di export/laporan ikut berubah.
2. **#4 GRP-GD** — perubahan dari perilaku sekarang (GRP-GD lembur dihitung normal → sekarang di-0-kan). Konfirmasi sudah disengaja.

---

## 10. Checklist Implementasi

> **✅ SELESAI DIIMPLEMENTASIKAN 2026-08-07** (GajiKaryawanController + Index.vue + PayrollSettings.vue). Verifikasi: `php artisan route:list`, tinker smoke test (on_the_fly/on_record/simpan/guard finalisasi/unlock), `npm run build`.

- [x] Helper hitung hari kerja on_the_fly (count record − minggu − holiday − absent)
- [x] Refactor `GajiKaryawanController::index()` → `onTheFly()` + `onRecord()`
- [x] Endpoint baru: `simpan`, `finalisasi`, `lock`, `unlock`
- [x] Tambah `mode` di response index (+ `record_status`)
- [x] Status lifecycle draft → generated → locked
- [x] Key SystemSetting `payroll_lock_password` (plain text, superadmin only)
- [x] Guard CRUD: `status !== locked` di semua endpoint mutasi
- [x] UI: badge estimasi, tombol Simpan/Finalisasi/Lock/Unlock, disable aksi
- [x] Konfirmasi Konflik #2 (gaji_kotor) — **koreksi 2026-08-07**: upah_lembur TETAP masuk, tj_mk TIDAK. Rumus final di semua mode: `gaji + tunjangan + upah_lembur + premi_hadir + revisi`.
