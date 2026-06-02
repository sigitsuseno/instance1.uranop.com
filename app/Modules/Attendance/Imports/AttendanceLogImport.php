<?php

namespace App\Modules\Attendance\Imports;

use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Services\TimeParser;
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
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class AttendanceLogImport implements SkipsEmptyRows, SkipsOnError, ToCollection, WithCalculatedFormulas, WithChunkReading
{
    use Importable;

    protected int $inserted = 0;

    protected array $errors = [];

    protected array $warnings = [];

    protected array $employeeCache = [];

    protected int $duplicateCount = 0;

    protected string $importBatch;

    protected string $fileName;

    // Column mapping (0-index) — sesuai format export mesin fingerprint
    protected int $nipColumn   = 1;  // PIN/NIP (kolom B)
    protected int $nameColumn  = 2;  // Nama (kolom C)
    protected int $dateColumn  = 6;  // Tanggal (kolom G)
    protected array $scanColumns = [7, 8, 9, 10]; // Scan 1-4 (kolom H-K)

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

        Log::info('=== START ATTENDANCE IMPORT ===', [
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

        Log::info('Data start row', [
            'batch'           => $this->importBatch,
            'start_row'       => $startRow,
            'total_data_rows' => count($allRows) - $startRow,
        ]);

        $allRecords     = [];
        $emptyScanCount = 0;

        for ($i = $startRow; $i < count($allRows); $i++) {
            $rowValues = $allRows[$i];
            $rowNumber = $i + 1;

            $employeeCode = $this->getColumnValue($rowValues, $this->nipColumn);
            $tanggal      = $this->getColumnValue($rowValues, $this->dateColumn);

            // Skip truly empty rows
            if (empty($employeeCode) && empty($tanggal)) {
                continue;
            }

            $result = $this->processRow($rowValues, $rowNumber);

            if (! empty($result['records'])) {
                $allRecords = array_merge($allRecords, $result['records']);
            }
            if (! empty($result['errors'])) {
                $this->errors = array_merge($this->errors, $result['errors']);
            }
            if (! empty($result['warnings'])) {
                $this->warnings = array_merge($this->warnings, $result['warnings']);
                if ($result['is_empty_scan']) {
                    $emptyScanCount++;
                }
            }
        }

        Log::info('Processing summary', [
            'batch'           => $this->importBatch,
            'records_created' => count($allRecords),
        ]);

        if (! empty($allRecords)) {
            $this->batchInsert($allRecords);
        }

        Log::info('Import completed', [
            'batch'            => $this->importBatch,
            'inserted'         => $this->inserted,
            'errors'           => count($this->errors),
            'warnings'         => count($this->warnings),
            'empty_scan_rows'  => $emptyScanCount,
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function onError(Throwable $e): void
    {
        $this->errors[] = $e->getMessage();
        Log::error('Import error: ' . $e->getMessage());
    }

    // ─── Header Detection ────────────────────────────────────────

    protected function findDataStartRow(array $allRows): int
    {
        // Strategy 1: Look for header row with 'PIN' or 'NIP'
        foreach ($allRows as $index => $row) {
            if (empty($row)) continue;

            $firstCol  = isset($row[0]) ? trim((string) $row[0]) : '';
            $secondCol = isset($row[1]) ? trim((string) $row[1]) : '';

            if ($firstCol === 'PIN' || $secondCol === 'PIN') return $index + 1;
            if ($firstCol === 'NIP' || $secondCol === 'NIP') return $index + 1;
        }

        // Strategy 2: Find first row with valid data format
        for ($i = 0; $i < count($allRows); $i++) {
            $row          = $allRows[$i];
            $employeeCode = isset($row[$this->nipColumn]) ? trim((string) $row[$this->nipColumn]) : '';
            $tanggal      = isset($row[$this->dateColumn]) ? trim((string) $row[$this->dateColumn]) : '';

            if (! empty($employeeCode) && preg_match('/\d{2}-\d{2}-\d{4}/', $tanggal)) {
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
            'records'       => [],
            'errors'        => [],
            'warnings'      => [],
            'is_empty_scan' => false,
        ];

        $employeeCode = $this->getColumnValue($rowValues, $this->nipColumn);
        $employeeName = $this->getColumnValue($rowValues, $this->nameColumn);
        $tanggal      = $this->getColumnValue($rowValues, $this->dateColumn);

        // Validate required fields
        if (empty($employeeCode)) {
            $result['errors'][] = "Baris {$rowNumber}: NIP/PIN kosong";

            return $result;
        }
        if (empty($tanggal)) {
            $result['errors'][] = "Baris {$rowNumber}: Tanggal kosong";

            return $result;
        }

        // Parse date
        $scanDate = $this->parseDate($tanggal);
        if (! $scanDate) {
            $result['errors'][] = "Baris {$rowNumber}: Format tanggal tidak valid '{$tanggal}'";

            return $result;
        }

        // Try to find employee (nullable — not required for raw import)
        $employeeId = $this->findEmployeeId($employeeCode);

        // Process each scan column
        $hasValidScan = false;
        $validScans   = [];

        foreach ($this->scanColumns as $scanIndex => $scanColumn) {
            $scanTime = $this->getColumnValue($rowValues, $scanColumn);

            if ($this->isEmptyScan($scanTime)) {
                continue;
            }

            $scanDateTime = TimeParser::parse($scanTime, $scanDate);

            if ($scanDateTime === null) {
                $result['warnings'][] = "Baris {$rowNumber} - Scan" . ($scanIndex + 1)
                    . ": Gagal parse waktu '{$scanTime}'";
                continue;
            }

            $validScans[] = [
                'scanDateTime' => $scanDateTime,
                'scanIndex'    => $scanIndex + 1,
                'scanTime'     => $scanTime,
            ];
            $hasValidScan = true;
        }

        if (! $hasValidScan) {
            $result['warnings'][]       = "Baris {$rowNumber}: Tidak ada data scan yang valid";
            $result['is_empty_scan']    = true;

            return $result;
        }

        // Create records
        foreach ($validScans as $scan) {
            $result['records'][] = [
                'pin'            => $employeeCode,
                'employee_code'  => $employeeCode,
                'employee_name'  => $employeeName,
                'scan_datetime'  => $scan['scanDateTime'],
                'scan_type'      => null,
                'machine_sn'     => null,
                'machine_name'   => 'Fingerprint',
                'verify_type'    => 'fingerprint',
                'is_processed'   => false,
                'import_batch'   => $this->importBatch,
                'source_file'    => $this->fileName,
                'raw_data'       => json_encode([
                    'nip'      => $employeeCode,
                    'nama'     => $employeeName,
                    'tanggal'  => $tanggal,
                    'waktu'    => $scan['scanTime'],
                    'scan_ke'  => 'scan' . $scan['scanIndex'],
                    'row'      => $rowNumber,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

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
                    $filteredChunk[]  = $record;
                    $existingMap[$key] = true; // prevent in-chunk duplicates too
                } else {
                    $this->duplicateCount++;
                    if ($this->duplicateCount <= 10) {
                        $this->warnings[] = "Data duplikat diabaikan: NIP {$record['employee_code']} pada {$recordDateStr}";
                    }
                }
            }

            if (empty($filteredChunk)) {
                Log::info('Batch skipped (all duplicates)', [
                    'batch' => $this->importBatch,
                    'chunk' => $chunkIndex + 1,
                ]);
                continue;
            }

            // Batch insert with row-by-row fallback
            try {
                DB::table('att_raw_logs')->insert($filteredChunk);
                $this->inserted += count($filteredChunk);

                Log::info('Batch inserted', [
                    'batch' => $this->importBatch,
                    'chunk' => $chunkIndex + 1,
                    'count' => count($filteredChunk),
                ]);
            } catch (Throwable $e) {
                Log::error('Batch insert error', [
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
            return $value->format('H:i:s');
        }

        // Keep numeric for Excel time parsing
        if (is_numeric($value)) {
            return $value;
        }

        // Clean string
        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', (string) $value);

        return trim($cleaned);
    }

    protected function isEmptyScan(mixed $value): bool
    {
        if ($value === null) return true;
        if (is_numeric($value) && $value == 0) return true;

        $stringValue = trim((string) $value);

        $emptyPatterns = ['', '0', '00:00', '00:00:00', '0:00:00', '0:00', 'null', 'NULL'];
        if (in_array($stringValue, $emptyPatterns)) return true;
        if (preg_match('/^0+$/', $stringValue)) return true;
        if (preg_match('/^(0{1,2}):(0{2}):(0{2})$/', $stringValue)) return true;

        return false;
    }

    protected function parseDate(mixed $dateString): ?Carbon
    {
        $dateString = trim((string) $dateString);

        // Excel numeric date
        if (is_numeric($dateString)) {
            try {
                if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Shared\\Date')) {
                    return Carbon::instance(Date::excelToDateTimeObject((int) $dateString));
                }
            } catch (Throwable) {
                // Continue
            }
        }

        // Common date formats
        $formats = ['d-m-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];
        foreach ($formats as $format) {
            try {
                $result = Carbon::createFromFormat($format, $dateString);
                if ($result && $result->format('Y') !== '1970') {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        // Carbon automatic
        try {
            $result = Carbon::parse($dateString);
            if ($result && $result->format('Y') !== '1970') {
                return $result;
            }
        } catch (Throwable) {
            // Continue
        }

        return null;
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
            Log::error('Error finding employee', [
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
