<?php

namespace App\Modules\Employee\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeSalaryImport implements ToCollection, WithHeadingRow
{
    protected array $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
    ];

    protected array $failed = [];
    protected array $employeeCache = [];

    public function collection(Collection $rows)
    {
        $this->stats['total'] = $rows->count();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header di baris 1

            // Skip baris yang benar-benar kosong
            $isEmptyRow = true;
            foreach ($row->toArray() as $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    $isEmptyRow = false;
                    break;
                }
            }

            if ($isEmptyRow) {
                $this->stats['total']--;
                continue;
            }

            // Validasi NIP
            $nip = trim((string) ($row['nip'] ?? ''));
            if (empty($nip)) {
                $this->stats['failed']++;
                $this->failed[] = [
                    'row' => $rowNumber,
                    'nik' => '-',
                    'errors' => ['NIP wajib diisi']
                ];
                continue;
            }

            try {
                if (!array_key_exists($nip, $this->employeeCache)) {
                    $emp = Employee::where(function ($q) use ($nip) {
                        $q->where('employee_code', $nip)
                          ->orWhere('nik', $nip)
                          ->orWhere('nip', $nip);
                    })->first();
                    
                    if (!$emp) {
                        Log::warning("Employee lookup failed for nip/employee_code: '{$nip}'");
                    }
                    
                    $this->employeeCache[$nip] = $emp;
                }
                
                $employee = $this->employeeCache[$nip];

                if (!$employee) {
                    $this->stats['failed']++;
                    $this->failed[] = [
                        'row' => $rowNumber,
                        'nik' => $nip,
                        'errors' => ["Karyawan dengan NIP/Employee Code {$nip} tidak ditemukan"]
                    ];
                    continue;
                }

                $effectiveDate = $this->parseDate($row['tanggal_efektif'] ?? null);
                $endDate = $this->parseDate($row['tanggal_akhir'] ?? null);

                DB::beginTransaction();

                // Get current basic salary before updating
                $current = EmployeeSalary::where('employee_id', $employee->id)->where('is_active', true)->first();
                $previousBasicSalary = $current?->base_salary ?? 0;

                // Nonaktifkan semua histori sebelumnya (jika ini dianggap sebagai data aktif/terbaru)
                EmployeeSalary::where('employee_id', $employee->id)->update(['is_active' => false]);

                // Nilai-nilai gaji
                $baseSalary = (float) ($row['gaji_pokok'] ?? 0);
                $tunjangan = (float) ($row['tunjangan'] ?? 0);
                $tunjanganMakan = (float) ($row['tunjangan_makan'] ?? 0);
                $premi = (float) ($row['premi'] ?? 0);
                $letterNumber = trim((string) ($row['nomer_surat'] ?? ''));
                $reason = trim((string) ($row['alasan'] ?? ''));
                
                // Change type by default is adjustment or initial
                $changeType = $current ? 'adjustment' : 'initial';
                if ($current && $baseSalary > $current->base_salary) {
                    $changeType = 'increase';
                } elseif ($current && $baseSalary < $current->base_salary) {
                    $changeType = 'decrease';
                }

                EmployeeSalary::create([
                    'employee_id'           => $employee->id,
                    'base_salary'           => $baseSalary,
                    'tunjangan'             => $tunjangan,
                    'allowance_meal'        => $tunjanganMakan,
                    'premi'                 => $premi,
                    'previous_basic_salary' => $previousBasicSalary,
                    'effective_date'        => $effectiveDate ? $effectiveDate->format('Y-m-d') : null,
                    'end_date'              => $endDate ? $endDate->format('Y-m-d') : null,
                    'change_type'           => $changeType,
                    'letter_number'         => $letterNumber,
                    'reason'                => $reason,
                    'is_active'             => true,
                    'created_by'            => Auth::id(),
                ]);

                DB::commit();
                $this->stats['success']++;
            } catch (\Exception $e) {
                DB::rollBack();
                $this->stats['failed']++;
                $this->failed[] = [
                    'row' => $rowNumber,
                    'nik' => $row['nip'] ?? '-',
                    'errors' => [$e->getMessage()]
                ];
                Log::error('EmployeeSalaryImport error: ' . $e->getMessage(), [
                    'row' => $row->toArray(),
                ]);
            }
        }
    }

    protected function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject($value));
            }

            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getResult(): array
    {
        if (count($this->failed) > 0) {
            Log::warning('Salary Import Failed Rows', $this->failed);
        }

        return [
            'stats' => $this->stats,
            'failed' => $this->failed,
        ];
    }
}
