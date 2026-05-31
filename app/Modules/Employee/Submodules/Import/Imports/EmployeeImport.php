<?php

namespace App\Modules\Employee\Submodules\Import\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeFamily;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Settings\Models\SalaryGrade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeImport implements ToCollection, WithHeadingRow
{
    protected $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
    ];

    protected $failed = [];

    // Mapping Department berdasarkan keyword
    protected $departmentMapping = [
        'management' => ['FACTORY MANAGER', 'MANAGER QC', 'MANAGER PRD', 'MANAGER PRODUCTION', 'MD'],
        'finance' => ['ACCOUNTING'],
        'hrd' => ['ADM HRD', 'HRD'],
        'administration' => ['ADM SO', 'ADM', 'FOLLOW UP'],
        'production' => ['OPERATOR CETAK', 'MANDOR SHIFT', 'MANDOR CETAK', 'TINTA', 'SCREEN', 'MANDOR SCREEN', 'SAMPLE', 'MANDOR AUTOPRINT', 'AUTOPRINT', 'OPERATOR BORDIR', 'SUBLIM', 'FINISHING', 'PACKING', 'OPERATOR TEMPEL', 'PRESS'],
        'quality' => ['QC PRINT', 'QC LINE', 'LAB'],
        'warehouse' => ['WAREHOUSE', 'GUDANG', 'GUDANG PRINT', 'IN-OUT'],
        'technical' => ['MEKANIK', 'TEKHNICAL'],
        'security' => ['SECURITY'],
        'design' => ['DESIGN'],
        'driver' => ['DRIVER'],
        'general' => ['UMUM'],
    ];

    public function collection(Collection $rows)
    {
        $this->stats['total'] = $rows->count();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            // Skip baris yang benar-benar kosong (biasanya ada di bagian bawah file excel)
            $isEmptyRow = true;
            foreach ($row->toArray() as $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    $isEmptyRow = false;
                    break;
                }
            }

            if ($isEmptyRow) {
                $this->stats['total']--; // Jangan hitung baris kosong ke dalam total
                continue;
            }

            // Validasi data minimal (gunakan NIP sebagai referensi utama)
            if (empty($row['nama_lengkap']) || empty($row['nip'])) {
                $this->stats['failed']++;
                $this->failed[] = "Baris {$rowNumber}: Nama atau NIP Kosong";
                continue;
            }

            DB::beginTransaction();
            try {
                // ===== 1. TENTUKAN DEPARTMENT =====
                $positionName = strtoupper(trim($row['pekerjaanposisi'] ?? 'OPERATOR'));
                $department = $this->determineDepartment($positionName);

                // ===== 2. BUAT/AMBIL POSITION =====
                $position = $this->getOrCreatePosition($positionName, $department->id);

                // ===== 3. TENTUKAN SALARY GRADE =====
                $salary = (float) ($row['gaji_pokok'] ?? 0);
                $this->getOrCreateSalaryGrade($salary);

                // ===== 4. TENTUKAN STATUS KARYAWAN =====
                $statusRaw = strtolower($row['status_permanentcontract'] ?? '');
                $employmentStatus = $this->determineEmploymentStatus($statusRaw);

                // ===== 5. PARSE TANGGAL =====
                $joinDate = $this->parseDate($row['tanggal_masuk_yyyy_mm_dd'] ?? null) ?? now();
                $birthDate = $this->parseDate($row['tanggal_lahir_yyyy_mm_dd'] ?? null);
                
                // Pada sistem lama, 'JKT' -> 1, selain itu 2. Kita buat dinamis berdasarkan nama shift.
                $groupName = ($row['shift'] ?? '') === 'JKT' ? 'JKT' : ($row['shift'] ?? 'Non-JKT');
                $groupCode = $this->ensureEmployeeGroupExists($groupName);

                // ===== 6. UPDATE ATAU CREATE EMPLOYEE =====
                $nip = $row['nip'];
                $employeeCode = (string) (50000 + (int) $nip);
                
                $employee = Employee::updateOrCreate(
                    ['nip' => (string) $nip],
                    [
                        'employee_code' => $employeeCode,
                        'nik' => isset($row['nik_ktp']) ? (string) $row['nik_ktp'] : null,
                        'name' => strtoupper($row['nama_lengkap']),
                        'gender' => strtoupper($row['lp'] ?? '') === 'L' ? 'L' : 'P',
                        'place_of_birth' => $row['tempat_lahir'] ?? '-',
                        'date_of_birth' => $birthDate,
                        'religion' => $this->mapReligion($row['agama'] ?? null),
                        'blood_type' => $row['gol_darah'] ?? null,
                        'address' => $row['alamat_lengkap'] ?? '',
                        'department_id' => $department->id,
                        'position_id' => $position->id,
                        'join_date' => $joinDate,
                        'base_salary' => $row['gaji_pokok'] ?? 0,
                        'bpjs_ketenagakerjaan' => isset($row['bpjs_tk']) ? (string) $row['bpjs_tk'] : null,
                        'bpjs_kesehatan' => isset($row['bpjs_ks']) ? (string) $row['bpjs_ks'] : null,
                        'employment_status' => $employmentStatus,
                        'bank_name' => $row['nama_bank'] ?? null,
                        'bank_account_number' => $row['nomor_rekening'] ?? null,
                        'bank_account_name' => $row['nama_di_rekening'] ?? null,
                        'is_active' => true,
                        'ptkp' => (string) ($row['ptkp'] ?? 'TK/0'),

                    ]
                );

                // Handle Group
                $employee->groups()->where('reference_code', 'LIKE', 'GRP-%')->delete(); // or we can just delete all if this is the only group source
                $employee->groups()->updateOrCreate(['reference_code' => $groupCode]);

                // ===== 7. HANDLE POSITION HISTORY =====
                if ($employee->wasRecentlyCreated) {
                    $employee->positionHistories()->create([
                        'effective_date' => $joinDate,
                        'change_reason'  => 'initial',
                        'new_department_id' => $department->id,
                        'new_position_id' => $position->id,
                    ]);
                }

                // ===== 8. HANDLE GAJI =====
                $this->handleSalary($employee, $row, $joinDate);

                // ===== 9. HANDLE KONTAK DARURAT =====
                $this->handleEmergencyContact($employee, $row);

                DB::commit();
                $this->stats['success']++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->stats['failed']++;
                $this->failed[] = "Baris {$rowNumber} ({$row['nama_lengkap']}): ".$e->getMessage();
                Log::error('Error Import: '.$e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        }
        
        // Log results
        Log::info("Employee Import Completed: Total: {$this->stats['total']}, Success: {$this->stats['success']}, Failed: {$this->stats['failed']}");
        if (!empty($this->failed)) {
            Log::warning("Employee Import Failures:\n" . implode("\n", $this->failed));
        }
    }

    protected function ensureEmployeeGroupExists($name)
    {
        $group = \App\Modules\Settings\Models\EmployeeGroupMaster::firstOrCreate(
            ['name' => $name],
            [
                'group_label' => 'Imported Shift/Group',
                'code' => 'GRP-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name)),
                'description' => "Auto generated from import"
            ]
        );
        
        return $group->code;
    }

    /**
     * Tentukan Department berdasarkan posisi
     */
    protected function determineDepartment($positionName)
    {
        foreach ($this->departmentMapping as $deptCode => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($positionName, $keyword)) {
                    return $this->getOrCreateDepartment($deptCode);
                }
            }
        }

        // Default ke Production
        return $this->getOrCreateDepartment('production_printing');
    }

    /**
     * Get or Create Department
     */
    protected function getOrCreateDepartment($deptCode)
    {
        $departmentNames = [
            'management' => 'Management',
            'finance' => 'Finance & Accounting',
            'hrd' => 'Human Resources',
            'administration' => 'Administration',
            'production_printing' => 'Printing Production',
            'production_embroidery' => 'Embroidery Production',
            'production_finishing' => 'Finishing',
            'quality' => 'Quality Control',
            'warehouse' => 'Warehouse',
            'technical' => 'Technical & Maintenance',
            'security' => 'Security',
            'design' => 'Design',
            'driver' => 'Driver',
            'general' => 'General Affairs',
        ];

        $name = $departmentNames[$deptCode] ?? 'Production';
        $code = strtoupper(substr(str_replace(' ', '_', $name), 0, 10));

        return Department::firstOrCreate(
            ['name' => $name],
            [
                'code' => $code,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or Create Position
     */
    protected function getOrCreatePosition($positionName, $departmentId)
    {
        // Buat kode unik dari nama posisi
        $positionCode = 'POS-'.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $positionName), 0, 10));

        return Position::firstOrCreate(
            ['code' => $positionCode],
            [
                'name' => $positionName,
                'department_id' => $departmentId,
                'is_active' => true,
            ]
        );
    }

    /**
     * Tentukan Salary Grade berdasarkan GAPOK
     */
    protected function getOrCreateSalaryGrade($salary)
    {
        if ($salary <= 2500000) {
            $level = 1;
            $name = 'Grade 1 (Operator)';
            $min = 0;
            $max = 2500000;
        } elseif ($salary <= 3000000) {
            $level = 2;
            $name = 'Grade 2 (Junior Staff)';
            $min = 2500001;
            $max = 3000000;
        } elseif ($salary <= 3500000) {
            $level = 3;
            $name = 'Grade 3 (Staff)';
            $min = 3000001;
            $max = 3500000;
        } elseif ($salary <= 4000000) {
            $level = 4;
            $name = 'Grade 4 (Senior Staff)';
            $min = 3500001;
            $max = 4000000;
        } else {
            $level = 5;
            $name = 'Grade 5 (Managerial)';
            $min = 4000001;
            $max = 20000000;
        }

        $grade = SalaryGrade::firstOrCreate(
            ['name' => $name],
            [
                'code' => 'GRD-'.$level,
                'min_salary' => $min,
                'max_salary' => $max,
                'middle_salary' => ($min + $max) / 2,
                'level' => $level,
            ]
        );

        return $grade->id;
    }

    /**
     * Tentukan Employment Status
     */
    protected function determineEmploymentStatus($statusRaw)
    {
        if ($statusRaw === 'pkwtt' || $statusRaw === 'permanent') {
            return 'permanent';
        }
        return 'contract';
    }

    /**
     * Handle Emergency Contact
     */
    protected function handleEmergencyContact($employee, $row)
    {
        if (! empty($row['nama_kontak_darurat'])) {
            EmployeeFamily::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'name' => $row['nama_kontak_darurat'],
                ],
                [
                    'relation' => $this->mapRelation($row['hubungan_keluarga'] ?? ''),
                    'gender' => strtoupper($row['lp_kontak'] ?? 'L'),
                    'emergency_phone' => $row['no_telp_darurat'] ?? null,
                    'is_emergency_contact' => true,
                ]
            );
        }
    }

    /**
     * Handle Salary
     */
    protected function handleSalary($employee, $row, $joinDate)
    {
        $salary = (float) ($row['gaji_pokok'] ?? 0);

        if ($salary > 0) {
            // Set existing salaries as inactive
            $employee->salaryComponents()
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'end_date' => $joinDate,
                ]);

            // Create new salary record
            $employee->salaryComponents()->create([
                'gaji_pokok' => $salary,
                'premi' => 0, // Set defaults or read from file if needed
                'tunjangan_masa_kerja' => 0,
                'tunjangan' => 0,
                'tunjangan_lain' => 0,
                'effective_date' => $joinDate,
                'end_date' => null,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Map Religion
     */
    protected function mapReligion($agama)
    {
        $agama = strtoupper($agama ?? '');
        if (str_contains($agama, 'ISLAM')) return 'islam';
        if (str_contains($agama, 'KRISTEN')) return 'kristen';
        if (str_contains($agama, 'KATOLIK')) return 'katolik';
        if (str_contains($agama, 'HINDU')) return 'hindu';
        if (str_contains($agama, 'BUDHA')) return 'budha';

        return null;
    }

    /**
     * Map Relation
     */
    protected function mapRelation($hubungan)
    {
        $hubungan = strtoupper($hubungan ?? '');
        if (str_contains($hubungan, 'ISTRI')) return 'spouse';
        if (str_contains($hubungan, 'SUAMI')) return 'spouse';
        if (str_contains($hubungan, 'ANAK')) return 'child';
        if (str_contains($hubungan, 'AYAH') || str_contains($hubungan, 'BAPAK')) return 'parent';
        if (str_contains($hubungan, 'IBU')) return 'parent';
        if (str_contains($hubungan, 'KAKAK') || str_contains($hubungan, 'ADIK') || str_contains($hubungan, 'SAUDARA')) return 'sibling';

        return 'other';
    }

    /**
     * Helper to parse Excel dates or standard dates.
     */
    private function parseDate($value)
    {
        if (empty($value)) return null;

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value))->format('Y-m-d');
            }
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Date parse failed for value: " . $value);
            return null;
        }
    }

    /**
     * Generate unique employee code: EMP{YY}{0001}.
     */
    private function generateEmployeeCode(): string
    {
        $prefix = 'EMP';
        $year   = date('y');

        $last = Employee::whereYear('created_at', date('Y'))
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $last && preg_match('/EMP\d{2}(\d{4})/', $last->employee_code, $matches)
            ? str_pad((int) $matches[1] + 1, 4, '0', STR_PAD_LEFT)
            : '0001';

        $code = $prefix . $year . $nextNumber;

        while (Employee::where('employee_code', $code)->exists()) {
            $nextNumber = str_pad((int) $nextNumber + 1, 4, '0', STR_PAD_LEFT);
            $code       = $prefix . $year . $nextNumber;
        }

        return $code;
    }
}
