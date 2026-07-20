<?php

namespace App\Modules\Attendance\Imports;

use App\Modules\Attendance\Models\RawLog;
use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Throwable;

/**
 * Import log absensi dari file Excel format RAW mesin fingerprint.
 *
 * Format kolom (0-indexed):
 *   0 = Tanggal scan  (datetime: DD-MM-YYYY HH:MM:SS)
 *   1 = Tanggal        (date: DD-MM-YYYY)
 *   2 = Jam            (time: HH:MM:SS)
 *   3 = PIN
 *   4 = NIP
 *   5 = Nama
 *   6 = Jabatan
 *   7 = Departemen
 *   8 = Kantor
 *   9 = Verifikasi     (0/1 — 1 = fingerprint terverifikasi)
 *  10 = I/O            (0 = check-in, 1 = check-out, 4 = overtime-in, 5 = overtime-out)
 *  11 = Workcode
 *  12 = SN             (serial number mesin)
 *  13 = Mesin          (nama mesin)
 *
 * Detection header: adanya kolom "Tanggal scan" DAN "I/O" DAN "SN" di baris pertama.
 */
class AttendanceRawLogImport implements SkipsEmptyRows, SkipsOnError, ToCollection, WithCalculatedFormulas, WithChunkReading
{
    use Importable;

    protected int $inserted = 0;

    protected array $errors = [];

    protected array $warnings = [];

    protected array $employeeCache = [];

    protected int $duplicateCount = 0;

    protected string $importBatch;

    protected string $fileName;

    // Column mapping (0-indexed) — format RAW mesin fingerprint
    protected int $nipColumn     = 4;  // NIP
    protected int $nameColumn    = 5;  // Nama
    protected int $scanDtColumn  = 0;  // Tanggal scan (datetime)
    protected int $dateColumn    = 1;  // Tanggal
    protected int $timeColumn    = 2;  // Jam
    protected int $verifyColumn  = 9;  // Verifikasi
    protected int $ioColumn      = 10; // I/O
    protected int $snColumn      = 12; // SN
    protected int $machineColumn = 13; // Mesin

    // IO code mapping
    protected const IO_IN  = '0';
    protected const IO_OUT = '1';

    public function __construct(string $importBatch, string $fileName)
    {
        $this->importBatch = $importBatch;
        $this->fileName    = $fileName;
    }

    // ─── Maatwebsite/Excel Interface ─────────────────────────────

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            throw new \Exception('File Excel tidak memiliki data');
        }

        Log::info('=== START RAW ATTENDANCE IMPORT ===', [
            'batch'      => $this->importBatch,
            'total_rows' => count($rows),
        ]);

        // Convert to indexed arrays
        $allRows = [];
        foreach ($rows as $row) {
            $allRows[] = array_values($row->toArray());
        }

        // Find data start row (skip headers)
        $startRow = $this->findDataStartRow($allRows);

        Log::info('Raw import: data start row', [
            'batch'           => $this->importBatch,
            'start_row'       => $startRow,
            'total_data_rows' => count($allRows) - $startRow,
        ]);

        $allRecords = [];

        for ($i = $startRow; $i < count($allRows); $i++) {
            $rowValues = $allRows[$i];
            $rowNumber = $i + 1;

            $nip  = $this->getColumnValue($rowValues, $this->nipColumn);
            $nama = $this->getColumnValue($rowValues, $this->nameColumn);

            // Skip truly empty rows
            if (empty($nip) && empty($nama)) {
                continue;
            }

            $result = $this->processRow($rowValues, $rowNumber);

            if (! empty($result['record'])) {
                $allRecords[] = $result['record'];
            }
            if (! empty($result['error'])) {
                $this->errors[] = $result['error'];
            }
            if (! empty($result['warning'])) {
                $this->warnings[] = $result['warning'];
            }
        }

        Log::info('Raw import processing summary', [
            'batch'           => $this->importBatch,
            'records_created' => count($allRecords),
        ]);

        if (! empty($allRecords)) {
            $this->batchInsert($allRecords);
        }

        Log::info('Raw import completed', [
            'batch'    => $this->importBatch,
            'inserted' => $this->inserted,
            'errors'   => count($this->errors),
            'warnings' => count($this->warnings),
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function onError(Throwable $e): void
    {
        $this->errors[] = $e->getMessage();
        Log::error('Raw import error: ' . $e->getMessage());
    }

    // ─── Header Detection ────────────────────────────────────────

    /**
     * Detect apakah file ini format RAW fingerprint machine.
     * Ciri: header mengandung "Tanggal scan" DAN ("I/O" atau "IO") DAN "SN".
     */
    public static function isRawFormat(array $firstRow): bool
    {
        $rowValues = array_values($firstRow);
        $headerStr = implode('|', array_map(fn ($v) => trim((string) $v), $rowValues));

        $hasTanggalScan = stripos($headerStr, 'tanggal scan') !== false;
        $hasIO           = stripos($headerStr, 'i/o') !== false;
        $hasSN           = stripos($headerStr, 'sn') !== false;

        // Need at least 2 of 3 markers
        $markers = 0;
        if ($hasTanggalScan) $markers++;
        if ($hasIO) $markers++;
        if ($hasSN) $markers++;

        return $markers >= 2;
    }

    protected function findDataStartRow(array $allRows): int
    {
        // Look for header row with "Tanggal scan" or "I/O" or "SN"
        foreach ($allRows as $index => $row) {
            if (empty($row)) continue;

            $firstVal  = isset($row[0]) ? trim((string) $row[0]) : '';
            $tenthVal  = isset($row[10]) ? trim((string) $row[10]) : '';

            // "Tanggal scan" di kolom pertama ATAU "I/O" di kolom ke-11
            if (stripos($firstVal, 'tanggal scan') !== false) return $index + 1;
            if (stripos($tenthVal, 'i/o') !== false || $tenthVal === 'I/O') return $index + 1;
        }

        // Strategy 2: Find first row with valid datetime at col 0
        for ($i = 0; $i < count($allRows); $i++) {
            $row       = $allRows[$i];
            $tanggalScan = isset($row[$this->scanDtColumn]) ? trim((string) $row[$this->scanDtColumn]) : '';

            if (preg_match('/\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2}/', $tanggalScan)) {
                return $i;
            }
        }

        // Default: assume data starts at row 3
        return 2;
    }

    // ─── Row Processing ──────────────────────────────────────────

    protected function processRow(array $rowValues, int $rowNumber): array
    {
        $result = [
            'record'  => null,
            'error'   => null,
            'warning' => null,
        ];

        $nip          = $this->getColumnValue($rowValues, $this->nipColumn);
        $nama         = $this->getColumnValue($rowValues, $this->nameColumn);
        $tanggalScan  = $this->getColumnValue($rowValues, $this->scanDtColumn);
        $tanggal      = $this->getColumnValue($rowValues, $this->dateColumn);
        $jam          = $this->getColumnValue($rowValues, $this->timeColumn);
        $verifikasi   = $this->getColumnValue($rowValues, $this->verifyColumn);
        $io           = $this->getColumnValue($rowValues, $this->ioColumn);
        $sn           = $this->getColumnValue($rowValues, $this->snColumn);
        $mesin        = $this->getColumnValue($rowValues, $this->machineColumn);

        // Validate NIP
        if (empty($nip)) {
            $result['error'] = "Baris {$rowNumber}: NIP kosong";

            return $result;
        }

        // Parse datetime — prefer Tanggal scan (col 0) karena sudah lengkap datetime
        $scanDateTime = $this->parseDatetime($tanggalScan);

        // Fallback: gabung Tanggal + Jam
        if (! $scanDateTime && ! empty($tanggal)) {
            $scanDateTime = $this->parseDatetimeFromDateAndTime($tanggal, $jam);
        }

        if (! $scanDateTime) {
            $result['error'] = "Baris {$rowNumber}: Gagal parse tanggal/waktu — scan='{$tanggalScan}', tgl='{$tanggal}', jam='{$jam}'";

            return $result;
        }

        // Determine scan_type from I/O code
        $scanType = $this->resolveScanType($io);

        // Determine verify type
        $verifyType = ($verifikasi !== null && (string) $verifikasi === '1') ? 'fingerprint' : 'password';

        // Machine info
        $machineSN   = ! empty($sn) ? (string) $sn : null;
        $machineName = ! empty($mesin) ? (string) $mesin : 'Fingerprint';

        $result['record'] = [
            'pin'           => $nip,
            'employee_code' => $nip,
            'employee_name' => $nama,
            'scan_datetime' => $scanDateTime,
            'scan_type'     => $scanType,
            'machine_sn'    => $machineSN,
            'machine_name'  => $machineName,
            'verify_type'   => $verifyType,
            'is_processed'  => false,
            'import_batch'  => $this->importBatch,
            'source_file'   => $this->fileName,
            'raw_data'      => json_encode([
                'nip'         => $nip,
                'nama'        => $nama,
                'tanggal'     => $tanggal,
                'jam'         => $jam,
                'io'          => $io,
                'verifikasi'  => $verifikasi,
                'sn'          => $sn,
                'mesin'       => $mesin,
                'row'         => $rowNumber,
            ], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        return $result;
    }

    // ─── Batch Insert with Deduplication ─────────────────────────

    protected function batchInsert(array $records): void
    {
        $chunks = array_chunk($records, 500);

        foreach ($chunks as $chunkIndex => $chunk) {
            $employeeCodes = array_unique(array_column($chunk, 'employee_code'));

            $scanDatetimes = array_map(function ($r) {
                return $r['scan_datetime'] instanceof Carbon
                    ? $r['scan_datetime']->format('Y-m-d H:i:s')
                    : Carbon::parse($r['scan_datetime'])->format('Y-m-d H:i:s');
            }, $chunk);

            $minDate = min($scanDatetimes);
            $maxDate = max($scanDatetimes);

            // Check existing records to prevent duplicates
            $existingLogs = DB::table('att_raw_logs')
                ->whereIn('employee_code', $employeeCodes)
                ->whereBetween('scan_datetime', [$minDate, $maxDate])
                ->get(['employee_code', 'scan_datetime']);

            $existingMap = [];
            foreach ($existingLogs as $log) {
                $dateStr = Carbon::parse($log->scan_datetime)->format('Y-m-d H:i:s');
                $existingMap[$log->employee_code . '|' . $dateStr] = true;
            }

            $filteredChunk = [];
            foreach ($chunk as $record) {
                $recordDateStr = $record['scan_datetime'] instanceof Carbon
                    ? $record['scan_datetime']->format('Y-m-d H:i:s')
                    : Carbon::parse($record['scan_datetime'])->format('Y-m-d H:i:s');

                $key = $record['employee_code'] . '|' . $recordDateStr;

                if (! isset($existingMap[$key])) {
                    $filteredChunk[]   = $record;
                    $existingMap[$key] = true; // prevent in-chunk duplicates
                } else {
                    $this->duplicateCount++;
                    if ($this->duplicateCount <= 10) {
                        $this->warnings[] = "Data duplikat diabaikan: NIP {$record['employee_code']} pada {$recordDateStr}";
                    }
                }
            }

            if (empty($filteredChunk)) {
                Log::info('Raw batch skipped (all duplicates)', [
                    'batch' => $this->importBatch,
                    'chunk' => $chunkIndex + 1,
                ]);
                continue;
            }

            // Batch insert with row-by-row fallback
            try {
                DB::table('att_raw_logs')->insert($filteredChunk);
                $this->inserted += count($filteredChunk);

                Log::info('Raw batch inserted', [
                    'batch' => $this->importBatch,
                    'chunk' => $chunkIndex + 1,
                    'count' => count($filteredChunk),
                ]);
            } catch (Throwable $e) {
                Log::error('Raw batch insert error', [
                    'batch' => $this->importBatch,
                    'error' => $e->getMessage(),
                ]);

                // Fallback: insert one by one
                foreach ($filteredChunk as $record) {
                    try {
                        DB::table('att_raw_logs')->insert($record);
                        $this->inserted++;
                    } catch (Throwable $e2) {
                        $this->errors[] = 'Gagal insert: ' . $e2->getMessage();
                    }
                }
            }
        }

        if ($this->duplicateCount > 10) {
            $this->warnings[] = '...dan ' . ($this->duplicateCount - 10) . ' data duplikat lainnya diabaikan.';
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────

    protected function getColumnValue(array $rowValues, int $index): mixed
    {
        if (! isset($rowValues[$index])) {
            return null;
        }

        $value = $rowValues[$index];

        // Handle DateTime objects (Excel dates)
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        // Keep numeric for Excel time parsing
        if (is_numeric($value)) {
            return $value;
        }

        // Clean string
        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', (string) $value);

        return trim($cleaned);
    }

    /**
     * Parse datetime dari kolom "Tanggal scan" (format: DD-MM-YYYY HH:MM:SS).
     */
    protected function parseDatetime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = trim((string) $value);

        // Excel numeric datetime (PhpSpreadsheet)
        if (is_numeric($str) && $str > 1) {
            try {
                if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Shared\\Date')) {
                    return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $str));
                }
            } catch (Throwable) {
                // fall through
            }
        }

        // Format: DD-MM-YYYY HH:MM:SS
        $formats = [
            'd-m-Y H:i:s',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'd-m-Y H:i',
            'd/m/Y H:i',
            'Y-m-d H:i',
        ];

        foreach ($formats as $format) {
            try {
                $result = Carbon::createFromFormat($format, $str);
                if ($result && $result->year > 2000) {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        // Carbon auto-parse
        try {
            $result = Carbon::parse($str);
            if ($result && $result->year > 2000) {
                return $result;
            }
        } catch (Throwable) {
            // fall through
        }

        return null;
    }

    /**
     * Fallback: gabung Tanggal (col 1) + Jam (col 2) jadi datetime.
     */
    protected function parseDatetimeFromDateAndTime(mixed $dateVal, mixed $timeVal): ?Carbon
    {
        $dateStr = trim((string) $dateVal);
        $timeStr = trim((string) $timeVal);

        if (empty($dateStr)) {
            return null;
        }

        // Parse date
        $date = $this->parseDateOnly($dateStr);
        if (! $date) {
            return null;
        }

        // Parse time if available
        if (! empty($timeStr)) {
            $time = $this->parseTimeOnly($timeStr);
            if ($time) {
                $date->setTime((int) $time->format('H'), (int) $time->format('i'), (int) $time->format('s'));
            }
        }

        return $date;
    }

    protected function parseDateOnly(mixed $value): ?Carbon
    {
        $str = trim((string) $value);

        if (is_numeric($str) && $str > 1) {
            try {
                if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Shared\\Date')) {
                    return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $str));
                }
            } catch (Throwable) {}
        }

        $formats = ['d-m-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];
        foreach ($formats as $format) {
            try {
                $result = Carbon::createFromFormat($format, $str);
                if ($result && $result->year > 2000) return $result;
            } catch (Throwable) { continue; }
        }

        try {
            $result = Carbon::parse($str);
            if ($result && $result->year > 2000) return $result;
        } catch (Throwable) {}

        return null;
    }

    protected function parseTimeOnly(mixed $value): ?Carbon
    {
        $str = trim((string) $value);

        // Excel numeric time (fraction of day)
        if (is_numeric($str) && $str < 1 && $str > 0) {
            $hours   = floor($str * 24);
            $minutes = floor(($str * 24 - $hours) * 60);
            $seconds = floor((($str * 24 - $hours) * 60 - $minutes) * 60);

            return Carbon::createFromTime($hours, $minutes, $seconds);
        }

        // DateTime object
        if ($value instanceof \DateTime) {
            return Carbon::instance($value);
        }

        $formats = ['H:i:s', 'H:i', 'h:i:s A', 'h:i A', 'G:i:s', 'G:i'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $str);
            } catch (Throwable) { continue; }
        }

        try {
            return Carbon::parse($str);
        } catch (Throwable) {}

        return null;
    }

    /**
     * Map I/O code to scan_type.
     *  0 = check-in
     *  1 = check-out
     *  4 = overtime-in
     *  5 = overtime-out
     */
    protected function resolveScanType(mixed $io): ?string
    {
        if ($io === null || $io === '') {
            return null;
        }

        $code = trim((string) $io);

        return match ($code) {
            '0'     => 'in',
            '1'     => 'out',
            '4'     => 'overtime_in',
            '5'     => 'overtime_out',
            default => null,
        };
    }

    protected function findEmployeeId(string $employeeCode): ?int
    {
        if (isset($this->employeeCache[$employeeCode])) {
            return $this->employeeCache[$employeeCode];
        }

        try {
            $employee = Employee::where('employee_code', $employeeCode)
                ->orWhere('nip', $employeeCode)
                ->first();

            $this->employeeCache[$employeeCode] = $employee?->id;
        } catch (Throwable $e) {
            Log::error('Error finding employee (raw import)', [
                'code'  => $employeeCode,
                'error' => $e->getMessage(),
            ]);
            $this->employeeCache[$employeeCode] = null;
        }

        return $this->employeeCache[$employeeCode];
    }

    // ─── Getters ──────────────────────────────────────────────────

    public function getInserted(): int
    {
        return $this->inserted;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getImportBatch(): string
    {
        return $this->importBatch;
    }
}
