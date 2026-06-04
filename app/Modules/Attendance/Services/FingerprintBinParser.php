<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\RawLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Fingerprint Bin Parser
 * 
 * Parsing file .bin dari mesin fingerprint (contoh: Fingerspot, Solution, dll).
 * Format .bin adalah fixed-width binary/text dengan struktur tertentu.
 */
class FingerprintBinParser
{
    /**
     * Delimiter untuk format text-based .bin.
     */
    protected string $delimiter = "\t";

    /**
     * Kolom mapping untuk format text-based.
     * Format standar mesin fingerprint Indonesia (Fingerspot/Solution):
     *   PIN\tTanggal\tJam\tMesin\tVerifikasi\t...
     */
    protected array $columnMap = [
        'pin' => 0,
        'date' => 1,
        'time' => 2,
        'machine_sn' => 3,
        'verify_type' => 4,
    ];

    /**
     * Format tanggal di file .bin.
     */
    protected array $dateFormats = [
        'd/m/Y',       // 25/05/2026
        'Y-m-d',       // 2026-05-25
        'd-m-Y',       // 25-05-2026
        'm/d/Y',       // 05/25/2026
        'Y/m/d',       // 2026/05/25
        'd.m.Y',       // 25.05.2026
    ];

    /**
     * Format jam di file .bin.
     */
    protected array $timeFormats = [
        'H:i:s',       // 08:30:00
        'H:i',         // 08:30
        'h:i:s A',     // 08:30:00 AM
        'h:i A',       // 08:30 AM
    ];

    /**
     * Parsing file .bin dan insert ke att_raw_logs.
     *
     * @param string $filePath Full path ke file .bin
     * @param string $importBatch Unique batch identifier
     * @param string $sourceFile Original filename
     * @return array ['inserted' => int, 'errors' => array, 'total_lines' => int]
     */
    public function parse(string $filePath, string $importBatch, string $sourceFile): array
    {
        Log::info('FingerprintBinParser: Memulai parsing', [
            'batch' => $importBatch,
            'file'  => $sourceFile,
            'path'  => $filePath,
        ]);

        if (!file_exists($filePath)) {
            return [
                'inserted' => 0,
                'errors' => ['File tidak ditemukan: ' . $filePath],
                'total_lines' => 0,
            ];
        }

        $inserted = 0;
        $errors = [];
        $records = [];

        // Baca file line by line (handle file besar)
        $handle = fopen($filePath, 'r');
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) continue;

            // Skip header lines (mengandung teks, bukan data numerik)
            if ($this->isHeaderLine($line)) continue;

            $result = $this->parseLine($line, $lineNumber, $importBatch, $sourceFile);

            if ($result['error']) {
                $errors[] = $result['error'];
            } else {
                $records[] = $result['record'];
            }

            // Batch insert setiap 500 record untuk menghindari memory overflow
            if (count($records) >= 500) {
                $inserted += $this->batchInsert($records);
                $records = [];
            }
        }

        fclose($handle);

        // Insert remaining records
        if (!empty($records)) {
            $inserted += $this->batchInsert($records);
        }

        Log::info('FingerprintBinParser: Selesai', [
            'batch' => $importBatch,
            'inserted' => $inserted,
            'errors' => count($errors),
            'total_lines' => $lineNumber,
        ]);

        return [
            'inserted' => $inserted,
            'errors' => $errors,
            'total_lines' => $lineNumber,
        ];
    }

    /**
     * Parse satu baris dari file .bin.
     */
    protected function parseLine(string $line, int $lineNumber, string $importBatch, string $sourceFile): array
    {
        // Deteksi delimiter (tab atau koma)
        $delimiter = $this->detectDelimiter($line);
        $columns = explode($delimiter, $line);

        // Clean whitespace dari setiap kolom
        $columns = array_map('trim', $columns);

        // Extract fields
        $pin = $columns[$this->columnMap['pin']] ?? null;
        $dateStr = $columns[$this->columnMap['date']] ?? null;
        $timeStr = $columns[$this->columnMap['time']] ?? null;
        $machineSn = $columns[$this->columnMap['machine_sn']] ?? null;
        $verifyType = $columns[$this->columnMap['verify_type']] ?? 'fingerprint';

        // Validasi PIN
        if (empty($pin)) {
            return ['error' => "Baris {$lineNumber}: PIN kosong", 'record' => null];
        }

        // Parse tanggal
        $date = $this->parseDate($dateStr);
        if (!$date) {
            return ['error' => "Baris {$lineNumber}: Format tanggal tidak valid '{$dateStr}'", 'record' => null];
        }

        // Parse jam
        $time = $this->parseTime($timeStr);
        if (!$time) {
            return ['error' => "Baris {$lineNumber}: Format jam tidak valid '{$timeStr}'", 'record' => null];
        }

        // Gabung tanggal + jam
        $scanDatetime = $date->setTime($time['hour'], $time['minute'], $time['second'] ?? 0);

        return [
            'error' => null,
            'record' => [
                'pin' => $pin,
                'employee_code' => $pin,
                'employee_name' => null,
                'scan_datetime' => $scanDatetime,
                'scan_type' => null,
                'machine_sn' => $machineSn,
                'machine_name' => null,
                'verify_type' => $verifyType ?: 'fingerprint',
                'is_processed' => false,
                'import_batch' => $importBatch,
                'source_file' => $sourceFile,
                'raw_data' => json_encode([
                    'line' => $lineNumber,
                    'raw' => $line,
                    'pin' => $pin,
                    'date' => $dateStr,
                    'time' => $timeStr,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Deteksi apakah baris adalah header.
     */
    protected function isHeaderLine(string $line): bool
    {
        $headerKeywords = ['PIN', 'NIP', 'NIK', 'TANGGAL', 'DATE', 'JAM', 'TIME', 'MESIN', 'NO.', 'NO'];

        $upperLine = strtoupper($line);

        foreach ($headerKeywords as $keyword) {
            if (str_contains($upperLine, $keyword) && strlen($line) < 200) {
                return true;
            }
        }

        // Cek untuk fixed-width .bin header (binary junk chars)
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $line) && strlen($line) < 100) {
            return true;
        }

        return false;
    }

    /**
     * Deteksi delimiter.
     */
    protected function detectDelimiter(string $line): string
    {
        $tabCount = substr_count($line, "\t");
        $commaCount = substr_count($line, ',');

        return $tabCount >= $commaCount ? "\t" : ',';
    }

    /**
     * Parse string tanggal ke Carbon.
     */
    protected function parseDate(?string $dateString): ?Carbon
    {
        if (empty($dateString)) return null;

        $dateString = trim($dateString);

        foreach ($this->dateFormats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $dateString);
                if ($date && $date->year > 2000 && $date->year < 2100) {
                    return $date;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        // Try Carbon auto-parse
        try {
            $date = Carbon::parse($dateString);
            if ($date && $date->year > 2000 && $date->year < 2100) {
                return $date;
            }
        } catch (\Throwable) {
            // continue
        }

        return null;
    }

    /**
     * Parse string jam ke array [hour, minute, second].
     */
    protected function parseTime(?string $timeString): ?array
    {
        if (empty($timeString)) return null;

        $timeString = trim($timeString);

        // Excel numeric time (fraction of a day: 0.53699 * 86400 = seconds)
        if (is_numeric($timeString) && $timeString >= 0 && $timeString < 1) {
            $totalSeconds = (int) round((float) $timeString * 86400);
            return [
                'hour' => (int) floor($totalSeconds / 3600),
                'minute' => (int) floor(($totalSeconds % 3600) / 60),
                'second' => $totalSeconds % 60,
            ];
        }

        foreach ($this->timeFormats as $format) {
            try {
                $time = Carbon::createFromFormat($format, $timeString);
                return [
                    'hour' => (int) $time->format('H'),
                    'minute' => (int) $time->format('i'),
                    'second' => (int) $time->format('s'),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Batch insert dengan deduplication.
     */
    protected function batchInsert(array $records): int
    {
        if (empty($records)) return 0;

        $inserted = 0;

        foreach (array_chunk($records, 500) as $chunk) {
            // Cek existing data untuk deduplikasi
            $existingKeys = [];
            if (!empty($chunk)) {
                $employeeCodes = array_unique(array_column($chunk, 'employee_code'));
                $scanDatetimes = array_map(fn($r) => 
                    $r['scan_datetime'] instanceof Carbon 
                        ? $r['scan_datetime']->format('Y-m-d H:i:s') 
                        : $r['scan_datetime'],
                    $chunk
                );
                $minDate = min($scanDatetimes);
                $maxDate = max($scanDatetimes);

                $existing = RawLog::whereIn('employee_code', $employeeCodes)
                    ->whereBetween('scan_datetime', [$minDate, $maxDate])
                    ->get(['employee_code', 'scan_datetime']);

                foreach ($existing as $log) {
                    $key = $log->employee_code . '|' . Carbon::parse($log->scan_datetime)->format('Y-m-d H:i:s');
                    $existingKeys[$key] = true;
                }
            }

            // Filter duplicates
            $filtered = [];
            foreach ($chunk as $record) {
                $scanStr = $record['scan_datetime'] instanceof Carbon
                    ? $record['scan_datetime']->format('Y-m-d H:i:s')
                    : $record['scan_datetime'];
                $key = $record['employee_code'] . '|' . $scanStr;

                if (!isset($existingKeys[$key])) {
                    // Konversi Carbon ke string untuk insert
                    if ($record['scan_datetime'] instanceof Carbon) {
                        $record['scan_datetime'] = $record['scan_datetime']->format('Y-m-d H:i:s');
                    }
                    $filtered[] = $record;
                    $existingKeys[$key] = true;
                }
            }

            if (!empty($filtered)) {
                try {
                    RawLog::insert($filtered);
                    $inserted += count($filtered);
                } catch (\Throwable $e) {
                    Log::error('FingerprintBinParser: Batch insert failed', [
                        'error' => $e->getMessage(),
                    ]);
                    // Row-by-row fallback
                    foreach ($filtered as $record) {
                        try {
                            RawLog::create($record);
                            $inserted++;
                        } catch (\Throwable $e2) {
                            Log::error('FingerprintBinParser: Row insert failed', [
                                'error' => $e2->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        return $inserted;
    }
}
