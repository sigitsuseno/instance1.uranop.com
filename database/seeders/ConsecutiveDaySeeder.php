<?php

namespace Database\Seeders;

use App\Modules\Attendance\Models\ConsecutiveDay;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ConsecutiveDaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Membaca data consecutive days dari sample_data/cons_mei.xlsx
     * Format Excel: NIP | NAMA | 1 | 2 | 3 | 4
     * Kolom 1-4 berisi tanggal absent (bisa 0-4 tanggal per karyawan)
     *
     * Type: worked (hadir), total_days: 1 per record.
     */
    public function run(): void
    {
        $filePath = base_path('sample_data/cons_mei.xlsx');

        if (! file_exists($filePath)) {
            $this->command?->warn("File not found: {$filePath}");

            return;
        }

        // ── Baca Excel ──────────────────────────────────────────
        $spreadsheet = IOFactory::load($filePath);
        $worksheet   = $spreadsheet->getActiveSheet();
        $rows        = $worksheet->toArray();

        if (empty($rows)) {
            return;
        }

        // Skip header row
        $header = array_shift($rows);

        // ── Kumpulkan data ──────────────────────────────────────
        $records  = [];
        $skipped  = [];
        $nipCache = []; // cache nip -> employee_id

        foreach ($rows as $rowIndex => $row) {
            $nip  = trim((string) ($row[0] ?? ''));
            $nama = trim((string) ($row[1] ?? ''));

            if ($nip === '') {
                continue;
            }

            // Kumpulkan tanggal dari kolom 3-6 (index 2-5)
            $dates = [];
            for ($i = 2; $i <= 5; $i++) {
                $val = $row[$i] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }

                // Parsing: PhpSpreadsheet bisa kasih string datetime atau timestamp
                if (is_numeric($val)) {
                    // Excel serial date → konversi
                    $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($val);
                    $dates[] = $date->format('Y-m-d');
                } else {
                    // String date, ambil Y-m-d
                    $dateStr = substr(trim((string) $val), 0, 10);
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                        $dates[] = $dateStr;
                    }
                }
            }

            if (empty($dates)) {
                continue;
            }

            // ── Lookup employee_id by nip ───────────────────────
            if (! isset($nipCache[$nip])) {
                $employee = Employee::where('nip', $nip)->first(['id', 'nip', 'name']);
                if ($employee) {
                    $nipCache[$nip] = $employee->id;
                } else {
                    $skipped[] = "NIP={$nip}, NAMA={$nama} (employee not found)";
                    continue;
                }
            }

            $employeeId = $nipCache[$nip];

            foreach ($dates as $date) {
                $records[] = [
                    'uuid'        => (string) Str::uuid(),
                    'employee_id' => $employeeId,
                    'start_date'  => $date,
                    'end_date'    => $date,
                    'total_days'  => 1,
                    'type'        => ConsecutiveDay::TYPE_WORKED,
                    'status'      => ConsecutiveDay::STATUS_CALCULATED,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        if (empty($records)) {
            $this->command?->warn('No valid records found in Excel.');

            return;
        }

        // ── Insert ke database ──────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate dulu biar bersih (optional: bisa diganti delete where type=absent)
        ConsecutiveDay::truncate();

        // Insert batch per 100 record biar aman
        foreach (array_chunk($records, 100) as $chunk) {
            ConsecutiveDay::insert($chunk);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ── Report ──────────────────────────────────────────────
        $totalRecords = count($records);
        $totalEmployees = count(array_unique(array_column($records, 'employee_id')));

        $this->command?->info("ConsecutiveDaySeeder: {$totalRecords} records for {$totalEmployees} employees inserted.");

        if (! empty($skipped)) {
            $this->command?->warn('Skipped ' . count($skipped) . ' rows:');
            foreach ($skipped as $msg) {
                $this->command?->line("  - {$msg}");
            }
        }
    }
}
