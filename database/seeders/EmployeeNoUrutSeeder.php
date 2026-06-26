<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeeNoUrutSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('sample_data/urutan.xlsx');

        if (!file_exists($path)) {
            $this->command?->error("File tidak ditemukan: {$path}");
            return;
        }

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        // Skip header baris pertama
        $header = array_shift($rows);

        $updated = 0;
        $notFound = [];

        foreach ($rows as $row) {
            $nip = trim((string) ($row[0] ?? ''));
            $noUrut = trim((string) ($row[1] ?? ''));

            if (empty($nip) || $noUrut === '') {
                continue;
            }

            $employee = Employee::where('nip', $nip)->first();

            if ($employee) {
                $employee->update(['no_urut' => (int) $noUrut]);
                $updated++;
            } else {
                $notFound[] = $nip;
            }
        }

        if ($this->command) {
            $this->command->info("{$updated} karyawan berhasil diupdate no_urut-nya.");

            if (!empty($notFound)) {
                $this->command->warn(count($notFound) . ' NIP tidak ditemukan: ' . implode(', ', $notFound));
            }
        }
    }
}
