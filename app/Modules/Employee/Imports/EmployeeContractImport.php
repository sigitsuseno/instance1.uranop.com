<?php

namespace App\Modules\Employee\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeContractImport implements ToCollection, WithMultipleSheets
{
    protected array $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
    ];

    protected array $failed = [];

    protected array $employeeCache = [];

    protected int $lastContractNumber = 0;

    protected string $contractPrefixMonth = '';

    public function __construct()
    {
    }

    public function sheets(): array
    {
        return [
            0 => $this, // Hanya baca sheet pertama
        ];
    }

    public function collection(Collection $rows)
    {
        // Skip header row
        $this->stats['total'] = max(0, $rows->count() - 1);

        foreach ($rows as $index => $row) {
            // Skip the first row (header)
            if ($index === 0) {
                continue;
            }

            $rowNumber = $index + 1; // 1-based index in Excel
            $nipVal = trim((string) ($row[0] ?? ''));

            // Skip row if NIP is empty (usually ghost rows in Excel)
            if ($nipVal === '') {
                $this->stats['total']--;
                continue;
            }
            
            $colCount = count($row);
            if ($colCount < 4) {
                // Not enough columns for a contract, skip silently
                $this->stats['total']--;
                continue;
            }

            try {
                if (! array_key_exists($nipVal, $this->employeeCache)) {
                    $emp = Employee::where(function ($q) use ($nipVal) {
                        $q->where('nip', $nipVal)
                          ->orWhere('employee_code', $nipVal);
                    })->first();
                    
                    if (!$emp) {
                        Log::warning("Employee lookup failed for nip/employee_code: '{$nipVal}'");
                    }
                    
                    $this->employeeCache[$nipVal] = $emp;
                }
                $employee = $this->employeeCache[$nipVal];

                if (! $employee) {
                    $this->stats['failed']++;
                    $this->failed[] = [
                        'row' => $rowNumber,
                        'nik' => $nipVal,
                        'errors' => ['Karyawan dengan NIP tersebut tidak ditemukan']
                    ];
                    continue;
                }

                DB::beginTransaction();

                // Delete all existing contracts for this employee (Replace All mode)
                EmployeeContract::where('employee_id', $employee->id)->forceDelete();

                $now = Carbon::now();
                $segmentsInserted = 0;
                $currentVersion = 0;
                
                $segments = [];

                for ($i = 3; $i < $colCount; $i += 2) {
                    $awalRaw = trim((string) ($row[$i] ?? ''));
                    $akhirRaw = trim((string) ($row[$i+1] ?? ''));

                    // Stop if we hit empty or HABIS
                    if ($awalRaw === '' || strtoupper($awalRaw) === 'HABIS') {
                        break;
                    }

                    $startDate = $this->parseDate($awalRaw);
                    $endDate = $this->parseDate($akhirRaw);

                    if (!$startDate || !$endDate) {
                        Log::warning("Invalid date format on row {$rowNumber}", ['awal' => $awalRaw, 'akhir' => $akhirRaw]);
                        continue;
                    }

                    $currentVersion++;
                    $segmentsInserted++;
                    $durationMonths = max(1, $startDate->diffInMonths($endDate)); // Nilai terkecil 1 bulan

                    $segments[] = [
                        'employee_id' => $employee->id,
                        'contract_number' => $this->generateContractNumber(),
                        'contract_type' => 'pkwt', // Default to pkwt
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'duration_months' => $durationMonths,
                        'status' => $endDate->greaterThanOrEqualTo($now) ? 'active' : 'expired',
                        'version' => $currentVersion,
                        'is_latest' => false, // Will be updated after loop
                        'compensation_paid_at' => null,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($segments)) {
                    // Set is_latest = true for the last segment
                    $segments[count($segments) - 1]['is_latest'] = true;
                    EmployeeContract::insert($segments);
                    $this->stats['success']++;
                } else {
                    // No valid segments found
                    $this->stats['failed']++;
                    $this->failed[] = [
                        'row' => $rowNumber,
                        'nik' => $nipVal,
                        'errors' => ['Tidak ada data periode kontrak yang valid ditemukan']
                    ];
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                $this->stats['failed']++;
                $this->failed[] = [
                    'row' => $rowNumber,
                    'nik' => $nipVal,
                    'errors' => [$e->getMessage()]
                ];
                Log::error('EmployeeContractImport error: '.$e->getMessage(), [
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

    protected function generateContractNumber(): string
    {
        $prefix = 'CTR';
        $year = date('Y');
        $month = date('m');
        $currentMonthPrefix = $prefix.$year.$month;

        if ($this->contractPrefixMonth !== $currentMonthPrefix) {
            $this->contractPrefixMonth = $currentMonthPrefix;

            $lastContract = EmployeeContract::where('contract_number', 'like', $currentMonthPrefix.'%')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastContract) {
                $this->lastContractNumber = (int) substr($lastContract->contract_number, -4);
            } else {
                $this->lastContractNumber = 0;
            }
        }

        $this->lastContractNumber++;
        $newNumber = str_pad($this->lastContractNumber, 4, '0', STR_PAD_LEFT);

        return $currentMonthPrefix.$newNumber;
    }

    public function getResult(): array
    {
        if (count($this->failed) > 0) {
            Log::warning('Contract Import Failed Rows', $this->failed);
        }

        return [
            'stats' => $this->stats,
            'failed' => $this->failed,
        ];
    }
}
