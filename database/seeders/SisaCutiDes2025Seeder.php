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

        $updatedEmployeeCode = 0;
        $updatedLeaveBalance = 0;
        $employeesNotFound = [];
        $rowsSkipped = 0;

        foreach ($rows as $rowIndex => $row) {
            if (count($row) < 4) {
                continue;
            }

            $nip          = trim($row[0]);
            $kodeKaryawan = trim($row[1]);
            $nama         = trim($row[2]);
            $sisaCuti     = (int) trim($row[3]);

            if (empty($nip) || $nip === 'NIP') {
                $rowsSkipped++;
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

            // 1. Update employee_code dari "Kode Karyawan"
            if (! empty($kodeKaryawan) && $employee->employee_code !== $kodeKaryawan) {
                $employee->employee_code = $kodeKaryawan;
                $employee->save();
                $updatedEmployeeCode++;
            }

            // 2. Update/Create employee_leave amount dari "Sisa Cuti"
            $this->updateLeaveBalance($employee->id, $leaveType->id, $sisaCuti);
            $updatedLeaveBalance++;

            if (($rowIndex + 1) % 50 === 0) {
                echo "  Diproses " . ($rowIndex + 1) . " baris... (employee_code: {$updatedEmployeeCode}, leave: {$updatedLeaveBalance})\n";
            }
        }

        echo "\n=== HASIL ===\n";
        echo "Total baris CSV: " . count($rows) . "\n";
        echo "Employee code diupdate: {$updatedEmployeeCode}\n";
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
    protected function updateLeaveBalance(int $employeeId, int $leaveTypeId, int $amount): void
    {
        // Cari record existing untuk employee + leave_type + leave_period_id=1 + transaction_type='initial'
        $existing = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', 1)
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
                'leave_period_id'  => 1,
                'reference_id'     => null,
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
