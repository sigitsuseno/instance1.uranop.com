<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Attendance\Services\TimeParser;
use App\Modules\Attendance\Support\ScanlogCellParser;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Supervisor\Attendance\Exceptions\AttendanceScanFileException;
use App\Modules\Supervisor\Models\SupervisorEmployeeGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Import scanlog fingerprint langsung ke attendance_autologs.
 *
 * Beda dengan {@see AttendanceImportService} yang meng-generate absensi dari
 * roster + att_prepares, service ini membaca file export mesin fingerprint
 * apa adanya lalu memadatkannya jadi satu baris per (karyawan, tanggal).
 *
 * Aturan:
 *   - check_in  = scan paling awal di hari itu
 *   - check_out = scan paling akhir (null kalau cuma ada 1 scan)
 *   - status    = 'present'
 *   - actual_in/actual_out = jam shift roster hari itu (work_hour_start/end)
 *   - lembur    = max(0, check_out - actual_out) menit, dibulatkan ke kelipatan
 *                 30 menit (minimal 30; di bawah 30 jadi 0), maksimal 3 jam;
 *                 hari Minggu / libur masuk ke lm apa adanya, bukan lembur
 *   - check_out = actual_out + (lembur + lm) — jam pulang mengikuti jumlah
 *                 lemburnya, seperti AttendanceImportService
 *   - hanya karyawan yang terdaftar di supervisor_employee_groups untuk
 *     periode terpilih yang diimport
 *
 * Aturan hari (mengikuti AttendanceImportService::processFixedFlex) — hanya
 * untuk karyawan NON-SHIFT; satpam (pola SHIFT) memang kerja Minggu & libur
 * sehingga lm-nya nyata dan tidak disentuh:
 *   - Minggu atau libur nasional (tabel sch_holidays) → status 'off',
 *     semua jam dikosongkan, lembur & lm 0
 *   - Sabtu → status 'present' dengan jam scan apa adanya, tapi lembur 0
 *   - lm hanya diisi untuk pola SHIFT
 *   - baris yang sudah ada ditimpa per (employee_id, date); baris di luar file
 *     dibiarkan apa adanya
 *
 * Kolom I/O pada file RAW sengaja tidak dipakai: pada export nyata isinya
 * selalu 0, termasuk untuk scan pulang.
 */
class AttendanceScanImportService
{
    /** Jumlah baris per statement upsert */
    protected const CHUNK_SIZE = 500;

    /** Di bawah ini tidak dihitung lembur */
    protected const MIN_OVERTIME_MINUTES = 30;

    /** Lembur dibulatkan ke kelipatan ini */
    protected const OVERTIME_ROUNDING_INTERVAL = 30;

    /** Toleransi pembulatan di batas kelipatan (55 menit → 60) */
    protected const OVERTIME_ROUNDING_TOLERANCE = 5;

    /** Lembur hari kerja maksimal 3 jam */
    protected const MAX_OVERTIME_MINUTES = 180;

    protected string $importBatch;

    protected string $fileName;

    protected string $startDate;

    protected string $endDate;

    protected string $format;

    /** @var array<int, bool> employee_id yang terdaftar di grup periode terpilih */
    protected array $scopeEmployeeIds = [];

    /** @var array<string, EmployeeShiftRoster> "employee_id|date" => roster */
    protected array $rostersByKey = [];

    /** @var array<int, string> employee_id => work_pattern_type (fallback hari tanpa roster) */
    protected array $patternByEmployee = [];

    /** @var array<string, int> nip => employee_id */
    protected array $employeeByNip = [];

    /** @var array<string, bool> "Y-m-d" => true */
    protected array $holidaySet = [];

    /** @var array<string, bool> "employee_id|date" => true (baris sudah ada) */
    protected array $existingKeys = [];

    /** @var array<string, bool> "employee_id|date" => true (is_locked) */
    protected array $lockedKeys = [];

    protected int $inserted = 0;

    protected int $updated = 0;

    protected int $skipped = 0;

    protected int $locked = 0;

    /** Jumlah baris scan yang dibaca dari file (sebelum dipadatkan per hari) */
    protected int $rowsRead = 0;

    /** @var array<int, string> */
    protected array $warnings = [];

    /** @var array<string, bool> PIN yang tidak ketemu di tabel employees */
    protected array $unmatchedPins = [];

    /**
     * @return array{inserted:int, updated:int, skipped:int, locked:int, rows_read:int,
     *               format:string, warnings:array<int,string>, unmatched_pins:array<int,string>}
     */
    public function import(string $filePath, string $originalName, string $startDate, string $endDate): array
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        // Laravel menyimpan instance controller di objek Route, jadi service ini
        // bisa dipakai ulang lintas request dalam satu proses (test, worker).
        // State harus direset, kalau tidak hitungannya menumpuk.
        $this->resetState();

        $this->fileName = $originalName;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->importBatch = 'SCAN_'.date('YmdHis').'_'.uniqid();

        $rows = $this->readRows($filePath);
        $this->format = $this->resolveFormat($rows);

        Log::info('=== START ATTENDANCE SCAN IMPORT ===', [
            'batch' => $this->importBatch,
            'file' => $originalName,
            'format' => $this->format,
            'period' => [$startDate, $endDate],
            'total_rows' => count($rows),
        ]);

        $startRow = ScanlogCellParser::findDataStartRow($rows, $this->format);

        $this->loadScope();

        $dataRows = array_slice($rows, $startRow);
        $this->rowsRead = count($dataRows);

        $scansByKey = $this->collectScans($dataRows);

        if ($scansByKey === []) {
            Log::warning('Attendance scan import: tidak ada scan yang masuk scope', [
                'batch' => $this->importBatch,
                'skipped' => $this->skipped,
            ]);

            return $this->result();
        }

        $this->loadExistingKeys(array_keys($scansByKey));

        $records = $this->buildRecords($scansByKey);

        $this->persist($records);

        Log::info('Attendance scan import selesai', [
            'batch' => $this->importBatch,
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'locked' => $this->locked,
        ]);

        return $this->result();
    }

    // ═══════════════════════════════════════════════════════════════
    //  STATE
    // ═══════════════════════════════════════════════════════════════

    protected function resetState(): void
    {
        $this->scopeEmployeeIds = [];
        $this->rostersByKey = [];
        $this->patternByEmployee = [];
        $this->employeeByNip = [];
        $this->holidaySet = [];
        $this->existingKeys = [];
        $this->lockedKeys = [];

        $this->inserted = 0;
        $this->updated = 0;
        $this->skipped = 0;
        $this->locked = 0;
        $this->rowsRead = 0;

        $this->warnings = [];
        $this->unmatchedPins = [];
    }

    // ═══════════════════════════════════════════════════════════════
    //  BACA FILE
    // ═══════════════════════════════════════════════════════════════

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function readRows(string $filePath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (Throwable $e) {
            Log::error('Attendance scan import: gagal membaca file', [
                'file' => $this->fileName,
                'error' => $e->getMessage(),
            ]);

            throw new AttendanceScanFileException('File tidak dapat dibaca sebagai Excel. Pastikan file .xls/.xlsx yang valid.');
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function resolveFormat(array $rows): string
    {
        foreach (array_slice($rows, 0, 10) as $row) {
            $format = ScanlogCellParser::detectFormat($row);
            if ($format !== null) {
                return $format;
            }
        }

        throw new AttendanceScanFileException(
            'Format file tidak dikenali. Didukung: export scanlog mentah '
            .'(kolom "Tanggal scan"/"I/O") dan rekap per karyawan (kolom "PIN"/"Scan 1").'
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  SCOPE & CACHE
    // ═══════════════════════════════════════════════════════════════

    protected function loadScope(): void
    {
        // Scope: karyawan yang terdaftar di grup periode terpilih — sama dengan
        // filter halaman absensi supervisor dan AttendanceImportService, jadi
        // baris yang diimport selalu sejalan dengan yang tampil di halaman itu.
        $scopeIds = SupervisorEmployeeGroup::where('period_start', $this->startDate)
            ->where('period_end', $this->endDate)
            ->pluck('employee_id')
            ->unique();

        foreach ($scopeIds as $employeeId) {
            $this->scopeEmployeeIds[(int) $employeeId] = true;
        }

        // Roster tetap dimuat untuk mengambil jam shift per tanggal; karyawan di
        // grup yang tidak punya roster di tanggal tertentu tetap diimport, hanya
        // actual_in/actual_out-nya kosong.
        $rosters = EmployeeShiftRoster::with('shift')
            ->whereBetween('date', [$this->startDate, $this->endDate])
            ->get();

        foreach ($rosters as $roster) {
            $date = Carbon::parse($roster->date)->toDateString();
            $this->rostersByKey[$roster->employee_id.'|'.$date] = $roster;

            if ($roster->work_pattern_type) {
                $this->patternByEmployee[$roster->employee_id] = $roster->work_pattern_type;
            }
        }

        // Employee soft-deleted otomatis tersaring oleh global scope.
        $this->employeeByNip = Employee::query()
            ->whereNotNull('nip')
            ->where('nip', '<>', '')
            ->pluck('id', 'nip')
            ->all();

        $this->holidaySet = Holiday::whereBetween('date', [$this->startDate, $this->endDate])
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [Carbon::parse($date)->toDateString() => true])
            ->all();

        Log::info('Attendance scan import: scope', [
            'batch' => $this->importBatch,
            'group_employees' => count($this->scopeEmployeeIds),
            'rosters' => count($this->rostersByKey),
            'holidays' => count($this->holidaySet),
        ]);
    }

    /**
     * Baris existing diambil TANPA filter deleted_at: baris soft-deleted harus
     * ikut terdeteksi supaya dihidupkan kembali, bukan menabrak unique index
     * (employee_id, date).
     *
     * @param  array<int, string>  $keys
     */
    protected function loadExistingKeys(array $keys): void
    {
        $employeeIds = [];
        $dates = [];

        foreach ($keys as $key) {
            [$employeeId, $date] = explode('|', $key);
            $employeeIds[(int) $employeeId] = true;
            $dates[$date] = true;
        }

        $existing = DB::table('attendance_autologs')
            ->whereIn('employee_id', array_keys($employeeIds))
            ->whereIn('date', array_keys($dates))
            ->get(['employee_id', 'date', 'is_locked']);

        foreach ($existing as $row) {
            $key = $row->employee_id.'|'.Carbon::parse($row->date)->toDateString();
            $this->existingKeys[$key] = true;

            if ($row->is_locked) {
                $this->lockedKeys[$key] = true;
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  KUMPULKAN SCAN → (employee_id, date)
    // ═══════════════════════════════════════════════════════════════

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, array{employee_id:int, date:string, scans:array<string, Carbon>}>
     */
    protected function collectScans(array $rows): array
    {
        $scans = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            [$pin, $dateRaw, $scanValues] = $this->extractRow($row);

            if ($pin === null || trim((string) $pin) === '') {
                continue;
            }

            $date = ScanlogCellParser::parseDate($dateRaw);
            if (! $date) {
                $this->skipped++;

                continue;
            }

            $dateStr = $date->toDateString();
            if ($dateStr < $this->startDate || $dateStr > $this->endDate) {
                $this->skipped++;

                continue;
            }

            $employeeId = $this->employeeByNip[trim((string) $pin)] ?? null;
            if ($employeeId === null) {
                $this->unmatchedPins[trim((string) $pin)] = true;
                $this->skipped++;

                continue;
            }

            if (! isset($this->scopeEmployeeIds[$employeeId])) {
                $this->skipped++;

                continue;
            }

            $key = $employeeId.'|'.$dateStr;
            $scans[$key] ??= ['employee_id' => $employeeId, 'date' => $dateStr, 'scans' => []];

            foreach ($scanValues as $value) {
                if (ScanlogCellParser::isEmptyScan($value)) {
                    continue;
                }

                $parsed = TimeParser::parse($value, $date);
                if (! $parsed) {
                    if (count($this->warnings) < 20) {
                        $this->warnings[] = "Jam tidak terbaca '{$value}' ({$dateStr})";
                    }

                    continue;
                }

                // Key H:i:s sekaligus membuang scan kembar
                $scans[$key]['scans'][$parsed->format('H:i:s')] = $parsed;
            }
        }

        // Buang hari yang tidak punya satu pun scan valid
        return array_filter($scans, fn (array $entry) => $entry['scans'] !== []);
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{0: mixed, 1: mixed, 2: array<int, mixed>}
     */
    protected function extractRow(array $row): array
    {
        if ($this->format === ScanlogCellParser::FORMAT_RAW) {
            $pin = ScanlogCellParser::cellValue($row, ScanlogCellParser::RAW_PIN);
            $dateRaw = ScanlogCellParser::cellValue($row, ScanlogCellParser::RAW_DATE);
            $timeRaw = ScanlogCellParser::cellValue($row, ScanlogCellParser::RAW_TIME);

            // Fallback: kolom "Tanggal scan" sudah lengkap datetime
            if ($dateRaw === null || $dateRaw === '' || ScanlogCellParser::isEmptyScan($timeRaw)) {
                $parsed = ScanlogCellParser::parseDateTime(
                    ScanlogCellParser::cellValue($row, ScanlogCellParser::RAW_SCAN_DATETIME)
                );

                if ($parsed) {
                    $dateRaw = ($dateRaw === null || $dateRaw === '')
                        ? $parsed->toDateString()
                        : $dateRaw;
                    $timeRaw = $parsed->format('H:i:s');
                }
            }

            return [$pin, $dateRaw, [$timeRaw]];
        }

        $scanValues = [];
        foreach (ScanlogCellParser::PIVOT_SCAN_COLUMNS as $column) {
            $scanValues[] = ScanlogCellParser::cellValue($row, $column, 'H:i:s');
        }

        return [
            ScanlogCellParser::cellValue($row, ScanlogCellParser::PIVOT_PIN),
            ScanlogCellParser::cellValue($row, ScanlogCellParser::PIVOT_DATE),
            $scanValues,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  BANGUN RECORD
    // ═══════════════════════════════════════════════════════════════

    /**
     * @param  array<string, array{employee_id:int, date:string, scans:array<string, Carbon>}>  $scansByKey
     * @return array<int, array<string, mixed>>
     */
    protected function buildRecords(array $scansByKey): array
    {
        $records = [];

        foreach ($scansByKey as $key => $entry) {
            if (isset($this->lockedKeys[$key])) {
                $this->locked++;

                continue;
            }

            $employeeId = $entry['employee_id'];
            $dateStr = $entry['date'];
            $date = Carbon::parse($dateStr);

            $times = array_keys($entry['scans']);
            sort($times);

            $checkIn = Carbon::parse($dateStr.' '.$times[0]);
            $checkOut = count($times) > 1
                ? Carbon::parse($dateStr.' '.$times[count($times) - 1])
                : null;

            $roster = $this->rostersByKey[$key] ?? null;
            $shift = $roster?->shift;

            $isSun = $roster !== null ? (bool) $roster->is_sun : $date->dayOfWeek === 0;
            $isSat = $roster !== null ? (bool) $roster->is_sat : $date->dayOfWeek === 6;
            $isNationalHoliday = isset($this->holidaySet[$dateStr]);
            $isHoliday = ($roster !== null && (bool) $roster->is_holiday) || $isNationalHoliday;

            // Satpam (pola SHIFT) memang kerja Minggu & hari libur — lm-nya nyata.
            // Aturan di bawah ini hanya untuk pola lain, sama seperti
            // AttendanceImportService::processFixedFlex().
            $pattern = $roster?->work_pattern_type
                ?? $this->patternByEmployee[$employeeId]
                ?? 'FIXED';
            $isShift = $pattern === 'SHIFT';

            // Non-SHIFT: Minggu / libur nasional → off, semua jam dikosongkan
            $isOffDay = ! $isShift && ($isSun || $isNationalHoliday);

            if ($isOffDay) {
                $checkIn = null;
                $checkOut = null;
                $actualIn = null;
                $actualOut = null;
                $lembur = 0;
                $lm = 0;
            } else {
                $actualIn = ($shift && $shift->work_hour_start)
                    ? Carbon::parse($dateStr.' '.$shift->work_hour_start)
                    : null;
                $actualOut = ($shift && $shift->work_hour_end)
                    ? Carbon::parse($dateStr.' '.$shift->work_hour_end)
                    : null;

                $overtimeMinutes = $this->calculateOvertimeMinutes($checkIn, $checkOut, $actualOut);

                // Klasifikasi lm memakai libur NASIONAL, bukan flag is_holiday di
                // roster: roster menandai hari libur mingguan per karyawan
                // (shift_code 'L'), dan kerja di hari itu tetap lembur biasa.
                $isLmDay = $isSun || $isNationalHoliday;

                // Non-SHIFT: Sabtu → lembur selalu 0 (jam Sabtu pendek, jadi
                // selisih check_out vs jadwal bukan lembur), dan lm memang selalu 0.
                // Maksimal 3 jam, sama seperti AttendanceImportService.
                $lembur = ($isLmDay || (! $isShift && $isSat))
                    ? 0
                    : min($this->roundOvertimeMinutes($overtimeMinutes), self::MAX_OVERTIME_MINUTES);

                $lm = ($isShift && $isLmDay) ? $overtimeMinutes : 0;

                // Jam pulang mengikuti jumlah lembur: check_out = jadwal + lembur.
                // Dihitung dari lembur + lm supaya hari Minggu/libur satpam
                // (lembur 0, lm = selisih) tetap menghasilkan jam scan aslinya.
                // Kalau tidak ada scan pulang atau jadwalnya tidak diketahui,
                // jam scan dibiarkan apa adanya.
                if ($checkOut !== null && $actualOut !== null) {
                    $checkOut = $actualOut->copy()->addMinutes($lembur + $lm);
                }
            }

            $records[] = [
                'company_id' => 1,
                'branch_id' => null,
                'employee_id' => $employeeId,
                'employee_shift_roster_id' => $roster?->id,
                'date' => $dateStr,
                'check_in' => $checkIn?->format('Y-m-d H:i:s'),
                'check_out' => $checkOut?->format('Y-m-d H:i:s'),
                'actual_in' => $actualIn?->format('Y-m-d H:i:s'),
                'actual_out' => $actualOut?->format('Y-m-d H:i:s'),
                'check_in_log_id' => null,
                'check_out_log_id' => null,
                'import_batch' => $this->importBatch,
                'status' => $isOffDay ? 'off' : 'present',
                'late_duration' => 0,
                'early_leave_duration' => 0,
                'lembur' => $lembur,
                'lembur_calc' => $lembur > 0 ? round($lembur / 60, 2) : 0,
                'lm' => $lm,
                'lm_calc' => $lm > 0 ? round($lm / 60, 2) : 0,
                'deduct_attendance' => 0,
                'is_half_day' => $roster?->is_half_day ?? 0,
                'is_sun' => $isSun ? 1 : 0,
                'is_sat' => $isSat ? 1 : 0,
                'is_holiday' => $isHoliday ? 1 : 0,
                'is_leave' => 0,
                'is_manual_edit' => 0,
                'last_edited_at' => null,
                'last_edited_by' => null,
                'holiday_overtime' => 0,
                'is_locked' => 0,
                'locked_at' => null,
                'locked_by' => null,
                'notes' => 'Imported from scanlog '.$this->fileName,
                'metadata' => json_encode([
                    'source' => 'scan_excel',
                    'file' => $this->fileName,
                    'format' => $this->format,
                    'imported_at' => now()->toDateTimeString(),
                    'scans' => $times,
                ]),
                'scan_count' => ($checkIn ? 1 : 0) + ($checkOut ? 1 : 0),
                'leave_id' => null,
                'deduct_day' => null,
                'izin_duration' => 0,
                'sakit_duration' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                // Baris yang pernah di-soft-delete dihidupkan kembali; tanpa ini
                // upsert hanya menimpa kolom lain dan barisnya tetap "terhapus".
                'deleted_at' => null,
            ];
        }

        return $records;
    }

    /**
     * Lembur = kelebihan jam pulang dibanding jadwal shift.
     * Hanya melihat check_out vs actual_out — bukan total jam kerja.
     */
    protected function calculateOvertimeMinutes(?Carbon $checkIn, ?Carbon $checkOut, ?Carbon $actualOut): int
    {
        if (! $checkIn || ! $checkOut || ! $actualOut) {
            return 0;
        }

        // Shift yang melewati tengah malam: penyesuaian hanya untuk perhitungan,
        // nilai check_out yang disimpan tetap apa adanya.
        $out = $checkOut->copy();
        if ($out->lt($checkIn)) {
            $out->addDay();
        }

        $minutes = (int) floor(($out->getTimestamp() - $actualOut->getTimestamp()) / 60);

        return max(0, $minutes);
    }

    /**
     * Pembulatan lembur hari kerja: kelipatan 30 menit, dengan toleransi 5 menit
     * di tiap batas kelipatan.
     *
     *   < 30 mnt        →  0     (di bawah 30 menit tidak dihitung lembur)
     *   30 – 54 mnt     → 30
     *   55 – 84 mnt     → 60
     *   85 – 114 mnt    → 90
     *   115 – 144 mnt   → 120
     *
     * Toleransinya cuma di batas atas: 55 menit sudah dihitung 60, tapi 29 menit
     * tetap 0 — bukan 30.
     */
    protected function roundOvertimeMinutes(int $minutes): int
    {
        if ($minutes < self::MIN_OVERTIME_MINUTES) {
            return 0;
        }

        return (int) floor(($minutes + self::OVERTIME_ROUNDING_TOLERANCE) / self::OVERTIME_ROUNDING_INTERVAL)
            * self::OVERTIME_ROUNDING_INTERVAL;
    }

    // ═══════════════════════════════════════════════════════════════
    //  SIMPAN
    // ═══════════════════════════════════════════════════════════════

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function persist(array $records): void
    {
        if ($records === []) {
            return;
        }

        foreach ($records as $record) {
            $key = $record['employee_id'].'|'.$record['date'];
            if (isset($this->existingKeys[$key])) {
                $this->updated++;
            } else {
                $this->inserted++;
            }
        }

        // Semua kolom kecuali kunci & created_at ikut ditimpa. Kalau ada yang
        // di-omit, nilai lama ikut terbawa (mis. lm_calc basi) dan menggelembungkan
        // lembur di payroll.
        $updateColumns = array_values(array_diff(
            array_keys($records[0]),
            ['employee_id', 'date', 'created_at']
        ));

        DB::beginTransaction();

        try {
            foreach (array_chunk($records, self::CHUNK_SIZE) as $chunk) {
                DB::table('attendance_autologs')->upsert($chunk, ['employee_id', 'date'], $updateColumns);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Attendance scan import gagal menyimpan', [
                'batch' => $this->importBatch,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{inserted:int, updated:int, skipped:int, locked:int, rows_read:int,
     *               format:string, warnings:array<int,string>, unmatched_pins:array<int,string>}
     */
    protected function result(): array
    {
        if ($this->unmatchedPins !== []) {
            $this->warnings[] = 'PIN tidak ditemukan di data karyawan: '
                .implode(', ', array_slice(array_keys($this->unmatchedPins), 0, 20))
                .(count($this->unmatchedPins) > 20 ? ' …' : '');
        }

        if ($this->locked > 0) {
            $this->warnings[] = $this->locked.' baris dilewati karena terkunci (is_locked).';
        }

        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'locked' => $this->locked,
            'rows_read' => $this->rowsRead,
            'format' => $this->format,
            'warnings' => $this->warnings,
            'unmatched_pins' => array_map('strval', array_keys($this->unmatchedPins)),
        ];
    }
}
