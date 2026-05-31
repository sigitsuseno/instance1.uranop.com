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
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeContractImport implements ToCollection, WithHeadingRow
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

    public function collection(Collection $rows)
    {
        $this->stats['total'] = $rows->count();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // header di baris 1

            $nipVal = trim((string) ($row['nip'] ?? ''));
            $tglVal = trim((string) ($row['tanggal_masuk'] ?? ''));
            $stsVal = trim((string) ($row['status'] ?? ''));
            $durVal = trim((string) ($row['durasi'] ?? ''));

            // Skip baris jika NIP kosong (biasanya ghost rows di Excel)
            if ($nipVal === '') {
                $this->stats['total']--;
                continue;
            }

            // Validasi minimal kolom
            if (empty($row['nip']) || empty($row['tanggal_masuk']) || empty($row['status']) || empty($row['durasi'])) {
                $this->stats['failed']++;
                $this->failed[] = [
                    'row' => $rowNumber,
                    'nik' => $row['nip'] ?? '-',
                    'errors' => ['NIP / TANGGAL_MASUK / STATUS / DURASI wajib diisi']
                ];
                Log::warning("Baris {$rowNumber} gagal validasi", $row->toArray());

                continue;
            }

            try {
                $nip = trim((string) $row['nip']);
                if (! array_key_exists($nip, $this->employeeCache)) {
                    $emp = Employee::where(function ($q) use ($nip) {
                        $q->where('nip', $nip)
                          ->orWhere('employee_code', $nip);
                    })->first();
                    
                    if (!$emp) {
                        Log::warning("Employee lookup failed for nip/employee_code: '{$nip}'");
                    }
                    
                    $this->employeeCache[$nip] = $emp;
                }
                $employee = $this->employeeCache[$nip];

                if (! $employee) {
                    $this->stats['failed']++;
                    $this->failed[] = [
                        'row' => $rowNumber,
                        'nik' => $row['nip'],
                        'errors' => ['Karyawan dengan NIP tersebut tidak ditemukan']
                    ];

                    continue;
                }

                $startDate = $this->parseDate($row['tanggal_masuk']);

                if (! $startDate) {
                    $this->stats['failed']++;
                    $this->failed[] = "Baris {$rowNumber}: Tanggal masuk tidak valid";

                    continue;
                }

                // Cek apakah kontrak dengan start_date yang sama sudah ada dari hasil upload ini (prefix CTR)
                $exists = EmployeeContract::where('employee_id', $employee->id)
                    ->where('start_date', $startDate->format('Y-m-d'))
                    ->where('contract_number', 'like', 'CTR%')
                    ->exists();

                if ($exists) {
                    // Jika data sudah ada, HAPUS riwayat yang terpotong/setengah jalan akibat timeout sebelumnya,
                    // agar sistem bisa meng-generate ulang sejarah kontraknya secara utuh sampai hari ini.
                    EmployeeContract::where('employee_id', $employee->id)
                        ->where('start_date', '>=', $startDate->format('Y-m-d'))
                        ->where('contract_number', 'like', 'CTR%')
                        ->forceDelete();
                }

                $duration = (int) $row['durasi'];

                if ($duration <= 0) {
                    $this->stats['failed']++;
                    $this->failed[] = [
                        'row' => $rowNumber,
                        'nik' => $row['nip'],
                        'errors' => ['DURASI harus lebih besar dari 0']
                    ];

                    continue;
                }

                $contractType = $this->mapContractType($row['status'] ?? null);

                if (! $contractType) {
                    $this->stats['failed']++;
                    $this->failed[] = [
                        'row' => $rowNumber,
                        'nik' => $row['nip'],
                        'errors' => ['STATUS kontrak tidak dikenali']
                    ];

                    continue;
                }

                $rawEnd = $row['end'] ?? $row['end_date'] ?? null;
                $excelEndDate = $this->parseDate($rawEnd);

                DB::beginTransaction();

                $lastVersion = EmployeeContract::where('employee_id', $employee->id)->max('version') ?? 0;
                $currentVersion = $lastVersion;

                // Set semua kontrak lama karyawan ini menjadi is_latest = false karena kita akan insert yang baru
                EmployeeContract::where('employee_id', $employee->id)->update(['is_latest' => false]);

                // Opsi A: Catatan kontrak berulang sesuai durasi hingga waktu saat ini (atau target end dari excel)
                $segmentStart = $startDate->copy();
                $now = Carbon::now();
                $segments = [];

                while (true) {
                    $segmentEnd = $segmentStart->copy()->addMonths($duration)->subDay();
                    $currentVersion++;

                    if ($excelEndDate) {
                        $isLast = $segmentEnd->greaterThanOrEqualTo($excelEndDate);
                    } else {
                        $isLast = $segmentEnd->greaterThanOrEqualTo($now);
                    }

                    // Batas aman 50 segmen
                    $isLast = $isLast || ($currentVersion - $lastVersion >= 50);

                    if ($isLast) {
                        $finalEnd = $excelEndDate ?: $segmentEnd;

                        $segments[] = [
                            'employee_id' => $employee->id,
                            'contract_number' => $this->generateContractNumber(),
                            'contract_type' => $contractType,
                            'start_date' => $segmentStart->copy()->format('Y-m-d'),
                            'end_date' => $finalEnd->copy()->format('Y-m-d'),
                            'duration_months' => $duration,
                            'status' => $finalEnd->greaterThanOrEqualTo($now) ? 'active' : 'expired',
                            'version' => $currentVersion,
                            'is_latest' => true,
                            'compensation_paid_at' => null,
                            'created_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        break;
                    }

                    $segments[] = [
                        'employee_id' => $employee->id,
                        'contract_number' => $this->generateContractNumber(),
                        'contract_type' => $contractType,
                        'start_date' => $segmentStart->copy()->format('Y-m-d'),
                        'end_date' => $segmentEnd->copy()->format('Y-m-d'),
                        'duration_months' => $duration,
                        'status' => 'expired',
                        'version' => $currentVersion,
                        'is_latest' => false,
                        'compensation_paid_at' => now(),
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $segmentStart = $segmentEnd->copy()->addDay();
                }

                if (! empty($segments)) {
                    EmployeeContract::insert($segments);
                }

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

    protected function mapContractType(?string $raw): ?string
    {
        $val = strtolower(trim((string) $raw));

        return match ($val) {
            'pkwt' => 'pkwt',
            'pkwtt' => 'pkwtt',
            'outsourcing', 'outsource' => 'outsourcing',
            'freelance' => 'freelance',
            default => null,
        };
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
