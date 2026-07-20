<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SisaCutiDes2025Seeder extends Seeder
{
    protected $csvFile = 'SISA CUTI DES 2025.csv';

    protected int $periodId = 1; // Periode 2025-2026

    /**
     * CSV columns: NIP;Kode Karyawan;Nama Lengkap;Sisa Cuti
     */
    public function run(): void
    {
        echo "\n=== SISA CUTI DES 2025 SEEDER ===\n";

        $csvPath = $this->findCsvFile();
        if (! $csvPath) {
            echo "ERROR: File CSV tidak ditemukan: {$this->csvFile}\n";

            return;
        }

        echo "Memproses file: {$csvPath}\n";

        // Cari leave_type "CT" (Cuti Tahunan)
        $leaveType = LeaveType::where('code', 'CT')->first();
        if (! $leaveType) {
            echo "ERROR: LeaveType 'CT' (Cuti Tahunan) tidak ditemukan!\n";

            return;
        }
        echo "Leave Type: {$leaveType->name} (ID: {$leaveType->id})\n";

        // Baca CSV (delimiter semicolon)
        $rows = [];
        if (($handle = fopen($csvPath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        // Skip header
        $header = array_shift($rows);
        echo "Header: " . implode(' | ', $header) . "\n";

        $updatedLeaveBalance = 0;
        $employeesNotFound = [];

        foreach ($rows as $rowIndex => $row) {
            if (count($row) < 4) {
                continue;
            }

            $nip          = trim($row[0]);
            $kodeKaryawan = trim($row[1]);
            $nama         = trim($row[2]);
            $sisaCuti     = (int) trim($row[3]);

            if (empty($nip) || $nip === 'NIP') {
                continue;
            }

            // Cari employee by NIP
            $employee = Employee::where('nip', $nip)->first();

            if (! $employee) {
                if (! in_array($nip, $employeesNotFound)) {
                    $employeesNotFound[] = $nip;
                }
                continue;
            }

            // Update employee_leave amount dari "Sisa Cuti"
            $this->updateLeaveBalance($employee->id, $leaveType->id, $sisaCuti, $this->periodId);
            $updatedLeaveBalance++;

            if (($rowIndex + 1) % 50 === 0) {
                echo "  Diproses " . ($rowIndex + 1) . " baris... (leave balance: {$updatedLeaveBalance})\n";
            }
        }

        echo "\n=== HASIL ===\n";
        echo "Total baris CSV: " . count($rows) . "\n";
        echo "Leave balance diupdate: {$updatedLeaveBalance}\n";

        if (! empty($employeesNotFound)) {
            echo "\nPeringatan: " . count($employeesNotFound) . " NIP tidak ditemukan di database:\n";
            foreach (array_slice($employeesNotFound, 0, 20) as $nip) {
                echo "  - {$nip}\n";
            }
        }

        echo "\n=== SEEDER COMPLETED ===\n";
    }

    /**
     * Update atau create employee_leave balance untuk cuti tahunan.
     * leave_period_id = 1, leave_type = CT (Cuti Tahunan).
     * transaction_type = 'initial' untuk menandai saldo awal.
     */
    protected function updateLeaveBalance(int $employeeId, int $leaveTypeId, int $amount, int $periodId): void
    {
        $existing = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->first();

        if ($existing) {
            // Update amount jika berbeda
            if ((int) $existing->amount !== $amount) {
                $existing->amount = $amount;
                $existing->save();
            }
        } else {
            // Buat record baru
            EmployeeLeave::create([
                'uuid'             => (string) Str::uuid(),
                'employee_id'      => $employeeId,
                'leave_type_id'    => $leaveTypeId,
                'leave_period_id'  => $periodId,
                'reference_id'     => null,
                'transaction_type' => 'initial',
                'amount'           => $amount,
                'description'      => 'Sisa Cuti Desember 2025',
                'created_by'       => 1,
            ]);
        }
    }

    protected function findCsvFile(): ?string
    {
        $paths = [
            database_path('seeders/' . $this->csvFile),
            storage_path('app/' . $this->csvFile),
            base_path($this->csvFile),
            base_path('sample_data/' . $this->csvFile),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
