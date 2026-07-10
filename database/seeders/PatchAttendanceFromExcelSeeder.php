<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PatchAttendanceFromExcelSeeder extends Seeder
{
    /**
     * Folder tempat file Excel berada. Default: folder tempat seeder ini berada.
     * Override dengan --folder="D:\path\to\folder" atau set $scanFolder.
     */
    protected string $scanFolder = '';

    /**
     * Override spesifik: proses satu file saja (abaikan scan folder).
     * Berguna untuk debug satu karyawan.
     * Contoh: 'ROHMIYATI.xlsx'
     */
    protected ?string $singleFile = null;

    public function run(): void
    {
        // Tentukan folder scan
        $folder = $this->resolveFolder();

        if (! is_dir($folder)) {
            $this->command->error("Folder tidak ditemukan: {$folder}");

            return;
        }

        $this->command->info("\n=== PATCH ATTENDANCE DARI EXCEL ===");
        $this->command->info("Folder: {$folder}");

        // Cari semua file .xlsx (skip temporary ~$*)
        $files = $this->findExcelFiles($folder);

        if (empty($files)) {
            $this->command->warn('Tidak ada file .xlsx ditemukan di folder ini.');

            return;
        }

        $this->command->info('Ditemukan '.count($files)." file Excel.\n");

        // Statistik global
        $globalStats = [
            'files' => count($files),
            'processed' => 0,
            'skipped' => 0,
            'total_updated' => 0,
            'total_not_found' => 0,
            'total_errors' => 0,
        ];

        foreach ($files as $filePath) {
            $fileName = basename($filePath);
            $this->command->info("━━━ {$fileName} ━━━");

            $result = $this->processFile($filePath);

            if ($result === null) {
                $globalStats['skipped']++;
            } else {
                $globalStats['processed']++;
                $globalStats['total_updated'] += $result['updated'];
                $globalStats['total_not_found'] += $result['not_found'];
                $globalStats['total_errors'] += $result['errors'];
            }

            $this->command->info('');
        }

        // Ringkasan global
        $this->command->info('═══════════════════════════════════');
        $this->command->info('        RINGKASAN GLOBAL');
        $this->command->info('═══════════════════════════════════');
        $this->command->info("File ditemukan     : {$globalStats['files']}");
        $this->command->info("File diproses      : {$globalStats['processed']}");
        $this->command->info("File diskip        : {$globalStats['skipped']}");
        $this->command->info("✅ Total diupdate  : {$globalStats['total_updated']}");
        $this->command->info("⚠️  Total not found : {$globalStats['total_not_found']}");
        $this->command->info("❌ Total error     : {$globalStats['total_errors']}");
        $this->command->info('═══════════════════════════════════');
        $this->command->info('SELESAI');
    }

    /**
     * Proses satu file Excel: cari employee, parse data, update attendance.
     */
    protected function processFile(string $filePath): ?array
    {
        // Parse info employee dari baris judul Excel
        $empInfo = $this->parseEmployeeFromExcel($filePath);

        if (! $empInfo) {
            $this->command->warn('  ⚠ Gagal membaca info karyawan dari file, skip.');

            return null;
        }

        $this->command->info("  Karyawan: {$empInfo['code']} - {$empInfo['name']}");

        // Cari employee di database
        $employee = Employee::where('nip', $empInfo['code'])->first();

        if (! $employee) {
            $employee = Employee::where('name', 'like', "%{$empInfo['name']}%")->first();
        }

        if (! $employee) {
            $this->command->warn('  ⚠ Employee tidak ditemukan di database! Skip.');

            return null;
        }

        // Parse data attendance dari Excel
        $data = $this->parseAttendanceData($filePath);

        if (empty($data)) {
            $this->command->warn('  ⚠ Tidak ada data attendance yang bisa diparse.');

            return null;
        }

        // Update records
        $stats = ['updated' => 0, 'not_found' => 0, 'skipped' => 0, 'errors' => 0];
        $notFoundDates = [];

        foreach ($data as $row) {
            $date = $row['date'];
            $dateStr = $date->format('Y-m-d');

            try {
                $attendance = DB::table('attendance_autologs')
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $date)
                    ->first();

                if (! $attendance) {
                    $stats['not_found']++;
                    $notFoundDates[] = $dateStr;

                    continue;
                }

                $updateData = [];

                if ($row['actual_in'] !== null) {
                    $updateData['check_in'] = Carbon::parse($dateStr.' '.$row['actual_in'])->format('Y-m-d H:i:s');
                }

                if ($row['actual_out'] !== null) {
                    $updateData['check_out'] = Carbon::parse($dateStr.' '.$row['actual_out'])->format('Y-m-d H:i:s');
                }

                $updateData['is_manual_edit'] = 1;
                $updateData['last_edited_at'] = now()->format('Y-m-d H:i:s');
                $updateData['updated_at'] = now()->format('Y-m-d H:i:s');

                if (! empty($updateData)) {
                    DB::table('attendance_autologs')
                        ->where('id', $attendance->id)
                        ->update($updateData);
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->command->error("    ❌ {$dateStr}: ".$e->getMessage());
            }
        }

        // Output per file
        $this->command->info("    ✅ Updated: {$stats['updated']}  ⚠ Not found: {$stats['not_found']}  ❌ Errors: {$stats['errors']}");

        if (! empty($notFoundDates) && count($notFoundDates) <= 5) {
            $this->command->warn('    Tanggal tanpa record: '.implode(', ', $notFoundDates));
        } elseif (! empty($notFoundDates)) {
            $this->command->warn('    '.count($notFoundDates).' tanggal tanpa record (sync dulu mungkin?)');
        }

        return $stats;
    }

    /**
     * Parse info karyawan dari judul Excel: "Detail Absensi Karyawan: 162 - ROHMIYATI"
     */
    protected function parseEmployeeFromExcel(string $filePath): ?array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Baris pertama: "Detail Absensi Karyawan: 162 - ROHMIYATI"
            $firstCell = trim((string) $worksheet->getCell('A1')->getValue());

            // Pattern: "Detail Absensi Karyawan: CODE - NAME"
            if (preg_match('/Detail\s+Absensi\s+Karyawan\s*:\s*(\S+)\s*-\s*(.+)/i', $firstCell, $m)) {
                return [
                    'code' => trim($m[1]),
                    'name' => trim($m[2]),
                ];
            }

            // Fallback: ambil dari nama file (format: "NAMA KARYAWAN.xlsx")
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
            // Coba parse "CODE - NAME" dari nama file
            if (preg_match('/^(\d+)\s*[-–—]\s*(.+)$/', $fileName, $m)) {
                return [
                    'code' => trim($m[1]),
                    'name' => trim($m[2]),
                ];
            }

            $this->command->warn("  ⚠ Tidak bisa parse info karyawan dari: {$firstCell}");

            return null;
        } catch (\Exception $e) {
            $this->command->error('  ❌ Error baca Excel: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Parse data attendance dari sheet Excel.
     */
    protected function parseAttendanceData(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        $data = [];
        $dataStart = false;

        foreach ($rows as $row) {
            $firstCell = trim((string) ($row[0] ?? ''));

            // Cari header: "Hari / Tanggal"
            if (! $dataStart) {
                if (str_contains($firstCell, 'Hari') && str_contains($firstCell, 'Tanggal')) {
                    $dataStart = true;
                }

                continue;
            }

            // Skip TOTAL atau kosong
            if (empty($firstCell) || str_starts_with($firstCell, 'TOTAL')) {
                continue;
            }

            $date = $this->parseDate($firstCell);

            if (! $date) {
                continue;
            }

            $data[] = [
                'date' => $date,
                'jadwal_masuk' => $this->parseTime($row[1] ?? null),
                'jadwal_pulang' => $this->parseTime($row[2] ?? null),
                'actual_in' => $this->parseTime($row[3] ?? null),
                'actual_out' => $this->parseTime($row[4] ?? null),
                'overtime_minutes' => $this->parseOvertime($row[5] ?? null),
                'overtime_text' => trim((string) ($row[5] ?? '')),
            ];
        }

        return $data;
    }

    /**
     * Parse string tanggal seperti "Wed, 25 Mar 2026".
     */
    protected function parseDate(string $text): ?Carbon
    {
        $text = trim($text);

        if (empty($text)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('D, d M Y', $text);
        } catch (\Exception $e) {
            try {
                return Carbon::parse($text);
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    /**
     * Parse string waktu: "07:00", "17:51", atau "--:--".
     */
    protected function parseTime(?string $text): ?string
    {
        $text = trim((string) $text);

        if (empty($text) || str_contains($text, '--:--') || $text === '-') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $text)) {
            return $text;
        }

        return null;
    }

    /**
     * Parse lembur: "3 jam" → 180 (menit), "-" → 0.
     */
    protected function parseOvertime(?string $text): int
    {
        $text = trim((string) $text);

        if (empty($text) || $text === '-') {
            return 0;
        }

        if (preg_match('/(\d+)\s*jam/', $text, $matches)) {
            return (int) $matches[1] * 60;
        }

        return 0;
    }

    /**
     * Resolve folder sumber file Excel.
     */
    protected function resolveFolder(): string
    {
        // 1. Override dari property
        if (! empty($this->scanFolder)) {
            return $this->scanFolder;
        }

        // 2. Default: folder tempat seeder ini berada
        $reflector = new \ReflectionClass($this);

        return dirname($reflector->getFileName());
    }

    /**
     * Cari semua file .xlsx dalam folder (skip temporary ~$*).
     */
    protected function findExcelFiles(string $folder): array
    {
        // Jika singleFile diset, hanya proses file itu
        if ($this->singleFile !== null) {
            $path = $folder.DIRECTORY_SEPARATOR.$this->singleFile;

            return file_exists($path) ? [$path] : [];
        }

        $files = glob($folder.DIRECTORY_SEPARATOR.'*.xlsx');

        if ($files === false) {
            return [];
        }

        // Filter: skip temporary Office files (~$*.xlsx)
        return array_values(array_filter($files, function ($file) {
            return ! str_starts_with(basename($file), '~$');
        }));
    }
}
