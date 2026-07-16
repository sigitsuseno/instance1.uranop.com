<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CutiAprilSeeder extends Seeder
{
    protected $csvFile = 'cuti_april.csv';

    protected $leaveTypeMapping = [];

    protected $employeeMapping = [];

    protected $periodStartDate = '2026-01-01';

    protected $periodEndDate = '2026-12-31';

    public function run(): void
    {
        echo "\n=== MEMPROSES SEEDER REKAP CUTI APRIL 2026 ===\n";

        $this->mapLeaveTypes();
        $this->mapEmployees();

        if (empty($this->employeeMapping)) {
            echo "ERROR: Tidak ada karyawan ditemukan.\n";

            return;
        }

        $csvPath = $this->findCsvFile();
        if (! $csvPath) {
            echo 'ERROR: File CSV tidak ditemukan: '.$this->csvFile."\n";

            return;
        }

        echo 'Memproses file: '.$csvPath."\n";

        // Baca file dengan delimiter semicolon (;)
        $rows = [];
        if (($handle = fopen($csvPath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        $header = array_shift($rows);

        // DEBUG: Tampilkan informasi header
        echo "\n=== DEBUG HEADER ===\n";
        echo 'Total kolom header: '.count($header)."\n";
        echo "5 kolom pertama header:\n";
        for ($i = 0; $i < min(5, count($header)); $i++) {
            echo "  Kolom {$i}: '{$header[$i]}'\n";
        }

        $dates = $this->parseDatesFromHeader($header);

        echo "\nJumlah tanggal yang diparse: ".count($dates['date_list'])."\n";
        if (count($dates['date_list']) > 0) {
            echo "Contoh 5 tanggal pertama:\n";
            for ($i = 0; $i < min(5, count($dates['date_list'])); $i++) {
                echo "  {$dates['date_list'][$i]}\n";
            }
        }

        // DEBUG: Tampilkan sample row pertama
        if (count($rows) > 0) {
            echo "\n=== DEBUG ROW PERTAMA ===\n";
            $firstRow = $rows[0];
            echo 'Total kolom row pertama: '.count($firstRow)."\n";
            echo "Employee Code (kolom 0): '{$firstRow[0]}'\n";

            if (isset($firstRow[1])) {
                echo "Nama (kolom 1): '{$firstRow[1]}'\n";
            } else {
                echo "Nama (kolom 1): TIDAK ADA\n";
            }

            echo "5 kolom data cuti pertama:\n";
            for ($i = 2; $i < min(7, count($firstRow)); $i++) {
                $dateLabel = isset($dates['date_list'][$i - 2]) ? $dates['date_list'][$i - 2] : '?';
                echo "  Kolom {$i} (tgl {$dateLabel}): '{$firstRow[$i]}'\n";
            }
        }

        $totalLeaveRequests = 0;
        $leaveCountByType = [];
        $employeesNotFound = [];
        $rowsProcessed = 0;

        foreach ($rows as $rowIndex => $row) {
            // Pastikan row memiliki cukup kolom
            if (count($row) < 2) {
                echo 'Peringatan: Row '.($rowIndex + 1).' memiliki kolom sedikit: '.count($row)."\n";

                continue;
            }

            $employeeCode = trim($row[0]);
            $nama = trim($row[1] ?? '');

            if (empty($employeeCode) || $employeeCode === 'NIP') {
                continue;
            }

            // Cek apakah employee ada
            if (! isset($this->employeeMapping[$employeeCode])) {
                if (! in_array($employeeCode, $employeesNotFound)) {
                    $employeesNotFound[] = $employeeCode;
                }

                continue;
            }

            $employeeId = $this->employeeMapping[$employeeCode];
            $rowsProcessed++;

            // Proses kolom cuti (mulai dari index 2)
            for ($i = 2; $i < count($row) && ($i - 2) < count($dates['date_list']); $i++) {
                $leaveCode = trim($row[$i]);

                if (empty($leaveCode)) {
                    continue;
                }

                $date = $dates['date_list'][$i - 2];

                // Cek apakah leave code valid
                if (! isset($this->leaveTypeMapping[$leaveCode])) {
                    static $unknownShown = 0;
                    if ($unknownShown < 10) {
                        echo "Peringatan: Kode tidak dikenal '{$leaveCode}' untuk {$nama} pada {$date}\n";
                        $unknownShown++;
                    }

                    continue;
                }

                $leaveTypeId = $this->leaveTypeMapping[$leaveCode];

                $this->createOrUpdateLeaveRequest($employeeId, $leaveTypeId, $date, $leaveCode);
                $totalLeaveRequests++;

                if (! isset($leaveCountByType[$leaveCode])) {
                    $leaveCountByType[$leaveCode] = 0;
                }
                $leaveCountByType[$leaveCode]++;
            }

            if (($rowIndex + 1) % 50 === 0) {
                echo '  Diproses '.($rowIndex + 1)." karyawan... (leave requests: {$totalLeaveRequests})\n";
            }
        }

        echo "\n=== HASIL PARSING ===\n";
        echo 'Total baris CSV: '.count($rows)."\n";
        echo "Baris diproses (karyawan ditemukan): {$rowsProcessed}\n";
        echo "Total leave requests dibuat: {$totalLeaveRequests}\n";

        if (! empty($employeesNotFound)) {
            echo "\nPeringatan: ".count($employeesNotFound)." employee_code tidak ditemukan:\n";
            foreach (array_slice($employeesNotFound, 0, 20) as $code) {
                echo "  - {$code}\n";
            }
        }

        if ($totalLeaveRequests > 0) {
            echo "\nRincian per jenis cuti:\n";
            foreach ($leaveCountByType as $code => $count) {
                $typeName = $this->getLeaveTypeName($code);
                echo "  - {$code} ({$typeName}): {$count} hari\n";
            }
        } else {
            echo "\nTIDAK ADA LEAVE REQUEST YANG DIBUAT!\n";
            echo "Kemungkinan penyebab:\n";
            echo "1. File CSV tidak berisi data cuti (semua kolom kosong)\n";
            echo "2. Format kode cuti di CSV berbeda dengan di database\n";
            echo "3. employee_code di CSV tidak match dengan database\n";
            echo "4. Header tanggal tidak sesuai format\n";
        }

        echo "\n=== SEEDER COMPLETED ===\n";
    }

    protected function findCsvFile()
    {
        $paths = [
            database_path('seeders/'.$this->csvFile),
            storage_path('app/'.$this->csvFile),
            base_path($this->csvFile),
            database_path('seeders/REKAP CUTI BULAN APRIL 2026.csv'),
            base_path('REKAP CUTI BULAN APRIL 2026.csv'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function mapLeaveTypes(): void
    {
        $leaveTypes = LeaveType::get();

        foreach ($leaveTypes as $type) {
            $this->leaveTypeMapping[$type->code] = $type->id;
        }

        echo 'Ditemukan '.count($this->leaveTypeMapping)." jenis cuti\n";
        echo 'Kode cuti yang tersedia: '.implode(', ', array_keys($this->leaveTypeMapping))."\n";
    }

    protected function mapEmployees(): void
    {
        $employees = Employee::where('is_active', true)->get();

        foreach ($employees as $employee) {
            $employeeCode = $employee->nip ?? $employee->employee_code ?? $employee->code;

            if ($employeeCode) {
                $this->employeeMapping[(string) $employeeCode] = $employee->id;
            }
        }

        echo 'Ditemukan '.count($this->employeeMapping)." karyawan di database (berdasarkan employee_code)\n";

        // Tampilkan 10 contoh employee_code
        $sampleCodes = array_slice(array_keys($this->employeeMapping), 0, 10);
        echo 'Contoh employee_code: '.implode(', ', $sampleCodes)."\n";
    }

    protected function parseDatesFromHeader($header): array
    {
        $dateList = [];
        $startDate = null;
        $endDate = null;

        // Skip kolom NIP dan NAMA (index 0 dan 1)
        for ($i = 2; $i < count($header); $i++) {
            $dateStr = trim($header[$i]);
            if (empty($dateStr)) {
                continue;
            }

            // Coba berbagai format tanggal
            $date = null;
            $formats = ['d/m/y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y'];

            foreach ($formats as $format) {
                try {
                    $date = Carbon::createFromFormat($format, $dateStr);
                    break;
                } catch (\Exception $e) {
                    continue;
                }
            }

            if ($date) {
                $dateList[] = $date->format('Y-m-d');

                if ($startDate === null || $date->lt($startDate)) {
                    $startDate = $date;
                }
                if ($endDate === null || $date->gt($endDate)) {
                    $endDate = $date;
                }
            } else {
                // Hanya tampilkan beberapa peringatan
                static $warningShown = 0;
                if ($warningShown < 5) {
                    echo "Peringatan: Gagal parsing tanggal: '{$dateStr}'\n";
                    $warningShown++;
                }
            }
        }

        return [
            'date_list' => $dateList,
            'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
            'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
        ];
    }

    protected function createOrUpdateLeaveRequest($employeeId, $leaveTypeId, $date, $leaveCode): void
    {
        $status = 'approved';

        // Ambil periode cuti aktif
        $period = LeavePeriod::where('status', 'active')->orderBy('start_date', 'desc')->first();

        $leaveRequest = LeaveRequest::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => $date,
                'end_date' => $date,
            ],
            [
                'leave_period_id' => $period?->id,
                'days_requested' => 1,
                'reason' => 'Auto-generated from April 2026 recap',
                'status' => $status,
                'created_by' => 1,
            ]
        );

        // Muat relasi leaveType
        $leaveRequest->loadMissing('leaveType');
        $leaveType = $leaveRequest->leaveType;
        $isNew = $leaveRequest->wasRecentlyCreated;

        // Jika leave request BARU dan tipe cuti mengurangi jatah, buat record EmployeeLeave (decrement)
        if ($isNew && $leaveType && $leaveType->balance_type === 'decrement') {
            EmployeeLeave::create([
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'leave_period_id' => $period?->id,
                'reference_id' => $leaveRequest->id,
                'transaction_type' => 'decrement',
                'amount' => 1,
                'description' => 'Auto-generated from April 2026 recap',
                'created_by' => 1,
            ]);
        }

        // Update roster (pakai pola dari LeaveRequestService::approveRequest)
        if ($leaveType) {
            $isLeave = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
            $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

            DB::table('sch_employee_shift_rosters')
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$date, $date])
                ->update([
                    'external_code' => $leaveType->code,
                    'is_leave' => $isLeave,
                    'is_permit' => $isPermit,
                    'leave_id' => $leaveRequest->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function getLeaveTypeName($code): string
    {
        $names = [
            'CT' => 'Cuti Tahunan',
            'CM' => 'Cuti Menikah',
            'CKM' => 'Cuti Keluarga Meninggal',
            'CH' => 'Cuti Hajatan',
            'CTM' => 'Cuti Melahirkan',
            'CTK' => 'Cuti Keguguran',
            'SKT' => 'Sakit',
            'CTH' => 'Cuti Haid',
            'CTI' => 'Cuti Ibadah',
            'ITM' => 'Izin Tidak Masuk',
            'IMT' => 'Izin Masuk Terlambat',
            'IPA' => 'Izin Pulang Awal',
        ];

        return $names[$code] ?? $code;
    }
}
