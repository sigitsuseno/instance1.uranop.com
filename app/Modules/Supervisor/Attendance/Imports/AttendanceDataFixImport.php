<?php

namespace App\Modules\Supervisor\Attendance\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class AttendanceDataFixImport implements SkipsEmptyRows, SkipsOnError, ToCollection, WithCalculatedFormulas, WithChunkReading
{
    use Importable;

    protected int $companyId;

    protected int $branchId;

    protected ?int $userId;

    protected string $importBatch;

    protected string $filePath;

    protected int $inserted = 0;

    protected int $updated = 0;

    protected array $errors = [];

    protected array $warnings = [];

    protected array $employeeCache = [];

    protected int $employeeCodeColumn = 0;

    protected int $dateColumn = 1;

    protected int $checkInColumn = 2;

    protected int $checkOutColumn = 3;

    protected int $statusColumn = 4;

    public function __construct(
        int $companyId,
        int $branchId,
        ?int $userId = null,
        string $filePath = 'sampe_data.xlsx'
    ) {
        $this->companyId = $companyId;
        $this->branchId = $branchId;
        $this->userId = $userId;
        $this->filePath = $filePath;
        $this->importBatch = 'FIX_'.date('YmdHis').'_'.uniqid();
    }

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw new \Exception('File Excel tidak memiliki data');
        }

        Log::info('=== START ATTENDANCE ROSTER IMPORT ===', [
            'batch' => $this->importBatch,
            'file' => $this->filePath,
            'total_rows' => count($rows),
        ]);

        $allRows = [];
        foreach ($rows as $index => $row) {
            $allRows[] = $row->toArray();
        }

        $headerRow = $allRows[0] ?? [];
        $dates = [];

        // Ambil tanggal dari header (lompat 2 kolom)
        for ($i = 2; $i < count($headerRow); $i += 2) {
            $dateValue = $headerRow[$i];
            if (is_numeric($dateValue) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
                $parsedDate = $this->excelDateToCarbon($dateValue) ?? $this->parseDate($dateValue);
                if ($parsedDate) {
                    $dates[$i] = $parsedDate;
                }
            }
        }

        $allRecords = [];

        // Proses data mulai baris ke-3 (index 2)
        for ($rowIndex = 2; $rowIndex < count($allRows); $rowIndex++) {
            $row = $allRows[$rowIndex];
            $rowNumber = $rowIndex + 1;

            $pin = trim($row[1] ?? '');

            if (empty($pin)) {
                continue;
            }

            $employeeId = $this->findEmployeeId($pin);

            if (! $employeeId) {
                $this->warnings[] = "Baris {$rowNumber}: Karyawan PIN {$pin} tidak ditemukan";

                continue;
            }

            $result = $this->processRow($row, $rowNumber, $employeeId, $dates);

            $allRecords = array_merge($allRecords, $result['records']);
            $this->errors = array_merge($this->errors, $result['errors']);
            $this->warnings = array_merge($this->warnings, $result['warnings']);
        }

        if (! empty($allRecords)) {
            $this->batchUpsert($allRecords);
        }
    }

    protected function findDataStartRow(array $allRows): int
    {
        foreach ($allRows as $index => $row) {
            if (empty($row)) {
                continue;
            }

            $firstCol = isset($row[0]) ? trim((string) $row[0]) : '';

            if (in_array($firstCol, ['NIP', 'PIN', 'KODE', 'KODE_KARYAWAN', 'EMPLOYEE_CODE'])) {
                return $index + 1;
            }
        }

        for ($i = 0; $i < count($allRows); $i++) {
            $row = $allRows[$i];
            $employeeCode = isset($row[$this->employeeCodeColumn]) ? trim((string) $row[$this->employeeCodeColumn]) : '';
            $date = isset($row[$this->dateColumn]) ? trim((string) $row[$this->dateColumn]) : '';

            if (! empty($employeeCode) && preg_match('/\d{2}-\d{2}-\d{4}/', $date)) {
                return $i;
            }
        }

        return 2;
    }

    /**
     * Memproses satu baris data karyawan (Horizontal/Matriks)
     */
    protected function processRow(array $rowValues, int $rowNumber, int $employeeId, array $dates): array
    {
        $result = [
            'records' => [],
            'errors' => [],
            'warnings' => [],
        ];

        foreach ($dates as $colIndex => $date) {
            $status = trim($rowValues[$colIndex] ?? '');
            $lemburRaw = trim($rowValues[$colIndex + 1] ?? ''); // Ambil lembur dari kolom sebelahnya

            try {
                $roster = EmployeeShiftRoster::where('employee_id', $employeeId)
                    ->where('company_id', $this->companyId)
                    ->where('branch_id', $this->branchId)
                    ->where('date', $date->toDateString())
                    ->with('workPattern', 'shift')
                    ->first();

                $leave = LeaveRequest::where('employee_id', $employeeId)
                    ->where('start_date', '<=', $date->toDateString())
                    ->where('end_date', '>=', $date->toDateString())
                    ->with('leaveType')
                    ->first();

                $checkIn = null;
                $checkOut = null;
                $sakit = 0;
                $izin = 0;
                $cuti = 0;

                // Konversi jam kerja ke objek Carbon

                $shiftStart = ($roster && $roster->shift) ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_start) : null;
                $shiftEnd = ($roster && $roster->shift) ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_end) : null;
                $ranran = rand(-10, 10);
                $ranend = rand(-2, 10);
                $shiftMulai = $shiftStart->copy()->addMinutes($ranran);
                $shiftSelesai = $shiftEnd->copy()->addMinutes($ranend);
                $deduct = 0;

                // Konversi lembur (hours) ke menit. Misal 1.5 jam -> 90 menit
                $lemburHours = is_numeric($lemburRaw) ? (float) $lemburRaw : 0;
                $lemburMinutes = (int) ($lemburHours * 60);
                $holidayOvertime = 0;
                $randomMinutes = rand(10, 20);
                // dd($randomMinutes);
                $arrayCuti = ['CTM', 'CTH', 'CH', 'CTI', 'CTK', 'CKM', 'CM', 'CUTI', 'CT'];

                // Logika Penentuan Status & Waktu
                if (in_array($roster->workPattern->employee_type, ['FIXED', 'FLEX-SHIFT'])) {
                    if (empty($status)) {

                        if ($roster->external_code === 'M') {
                            $checkIn = null;
                            $checkOut = null;
                            $status = 'off';
                        } elseif ($roster->is_holiday === 1) {
                            $checkIn = null;
                            $checkOut = null;
                            $status = 'holiday';
                        } else {
                            $status = 'off';
                        }

                    } else {
                        if ($status === 'OUT') {
                            $deduct = 1;
                            $status = 'absent';
                        } elseif ($status === '-') {
                            $deduct = 1;
                            $status = 'absent';
                        } elseif ($status === 'OFF') {
                            $status = 'off';
                        } elseif ($status === 'SAKIT') {
                            $sakit = 1;
                            $status = 'sakit';
                        } elseif ($status === 'IZIN') {
                            $izin = 1;
                            $status = 'izin';
                        } elseif (in_array($status, $arrayCuti)) {
                            $cuti = 1;
                        } elseif ($status === 'ALFA') {
                            $deduct = 1;
                            $status = 'absent';
                        } elseif ($status === 'T') {
                            if ($roster->external_code === 'P') {
                                $checkIn = $shiftMulai->format('Y-m-d H:i:s');
                                $checkOut = $shiftSelesai->copy()->addMinutes($lemburMinutes)->format('Y-m-d H:i:s');
                            } else {
                                $checkIn = $shiftMulai->copy()->subMinutes($lemburMinutes)->format('Y-m-d H:i:s');
                                $checkOut = $shiftSelesai->copy()->format('Y-m-d H:i:s');
                            }
                            $status = 'telat';
                        } elseif ($status === 'H') {
                            if ($roster->external_code === 'P') {
                                $checkIn = $shiftMulai->format('Y-m-d H:i:s');
                                $checkOut = $shiftSelesai->copy()->addMinutes($lemburMinutes)->format('Y-m-d H:i:s');
                            }
                            if ($roster->external_code === 'S') {
                                $checkIn = $shiftMulai->copy()->subMinutes($lemburMinutes)->format('Y-m-d H:i:s');
                                $checkOut = $shiftSelesai->format('Y-m-d H:i:s');
                            }
                            $status = 'present';
                        }
                    }

                } else {
                    if (empty($status)) {
                        $checkIn = null;
                        $checkOut = null;
                        $status = 'off';
                    } else {
                        if ($status === 'L') {
                            $checkIn = $shiftMulai->copy()->format('Y-m-d H:i:s');
                            $checkOut = $shiftSelesai->copy()->format('Y-m-d H:i:s');
                            $holidayOvertime = 1;
                            $status = 'present';
                        } elseif ($status === 'H') {
                            $checkIn = $shiftMulai->copy()->format('Y-m-d H:i:s');
                            $checkOut = $shiftSelesai->copy()->format('Y-m-d H:i:s');
                            $status = 'present';
                        } elseif ($status === 'OFF') {
                            $status = 'off';
                        } else {
                            $checkIn = $shiftMulai->copy()->format('Y-m-d H:i:s');
                            $checkOut = $shiftSelesai->copy()->format('Y-m-d H:i:s');
                            $status = 'present';
                        }
                    }
                }

                $normalizedStatus = $this->normalizeStatus($status);

                $result['records'][] = [
                    'company_id' => $this->companyId,
                    'branch_id' => $this->branchId,
                    'employee_id' => $employeeId,
                    'employee_shift_roster_id' => $roster->id ?? null,
                    'date' => $date->toDateString(),
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'check_in_log_id' => null,
                    'check_out_log_id' => null,
                    'import_batch' => $this->importBatch, // Tambahkan batch ID di sini
                    'status' => $normalizedStatus,
                    'late_duration' => ($status === 'telat') ? $randomMinutes : 0,
                    'early_leave_duration' => 0,
                    'overtime_duration' => $lemburMinutes > 0 ? $lemburMinutes : 0,
                    'deduct_attendance' => 0,
                    'is_half_day' => $roster->is_half_day ?? 0,
                    'is_sun' => $roster->is_sun ?? 0,
                    'is_sat' => $roster->is_sat ?? 0,
                    'is_holiday' => $roster->is_holiday ?? 0,
                    'is_leave' => ($leave !== null) ? 1 : ($roster->is_leave ?? 0),
                    'is_manual_edit' => 0,
                    'last_edited_at' => null,
                    'holiday_overtime' => $holidayOvertime,
                    'last_edited_by' => null,
                    'is_locked' => 0,
                    'locked_at' => null,
                    'locked_by' => null,
                    'notes' => null,
                    'metadata' => null,
                    'scan_count' => ($checkIn ? 1 : 0) + ($checkOut ? 1 : 0),
                    'leave_id' => $leave?->id,
                    'deduct_day' => ($leave && $leave->leaveType && $leave->leaveType->is_paid === 1) ? 1 : 0,
                    'izin_duration' => $izin,
                    'sakit_duration' => $sakit,
                    'overtime_converted_hours' => $lemburHours,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } catch (Throwable $e) {
                $result['errors'][] = "Baris {$rowNumber} Tgl {$date->toDateString()}: Gagal memproses data - ".$e->getMessage();
            }
        }

        return $result;
    }

    protected function getColumnValue($rowValues, int $index)
    {
        if (! isset($rowValues[$index])) {
            return null;
        }

        $value = $rowValues[$index];

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return $value;
        }

        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', (string) $value);

        return trim($cleaned);
    }

    protected function parseDate($dateString): ?Carbon
    {
        $dateString = trim((string) $dateString);

        if (is_numeric($dateString)) {
            try {
                if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                    return Carbon::instance(Date::excelToDateTimeObject((int) $dateString));
                }
            } catch (Throwable $e) {
                // Continue
            }
        }

        $formats = ['d-m-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];

        foreach ($formats as $format) {
            try {
                $result = Carbon::createFromFormat($format, $dateString);
                if ($result && $result->format('Y') !== '1970') {
                    return $result;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        try {
            $result = Carbon::parse($dateString);
            if ($result && $result->format('Y') !== '1970') {
                return $result;
            }
        } catch (Throwable $e) {
            // Continue
        }

        return null;
    }

    protected function parseDateTime($timeString, Carbon $date): ?Carbon
    {
        if (empty($timeString)) {
            return null;
        }

        if (is_numeric($timeString)) {
            try {
                $excelTime = (float) $timeString;
                $hours = floor($excelTime * 24);
                $minutes = floor(($excelTime * 24 * 60) % 60);
                $seconds = floor(($excelTime * 24 * 60 * 60) % 60);

                return $date->copy()->setTime($hours, $minutes, $seconds);
            } catch (Throwable $e) {
                return null;
            }
        }

        $timeString = trim((string) $timeString);

        $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i a', 'H:i:s A', 'H:i:s a'];

        foreach ($formats as $format) {
            try {
                $time = Carbon::createFromFormat($format, $timeString);
                if ($time) {
                    return $date->copy()->setTime(
                        (int) $time->format('H'),
                        (int) $time->format('i'),
                        (int) $time->format('s')
                    );
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return null;
    }

    protected function excelDateToCarbon($excelDate): ?Carbon
    {
        if (empty($excelDate) || ! is_numeric($excelDate)) {
            return null;
        }

        try {
            if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                return Carbon::instance(Date::excelToDateTimeObject((int) $excelDate));
            }
        } catch (Throwable $e) {
            Log::error('Error converting Excel date', [
                'excel_date' => $excelDate,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function normalizeStatus(?string $status): string
    {
        if (empty($status) || $status === '-') {
            return 'off';
        }

        $statusMap = [
            '-' => 'out',
            'h' => 'present',
            'hadir' => 'present',
            'present' => 'present',
            'cuti' => 'leave',
            'leave' => 'leave',
            'terlambat' => 'late',
            'telat' => 'late',
            'late' => 'late',
            'absen' => 'absent',
            'absent' => 'absent',
            'a' => 'absent',
            'alfa' => 'absent',
            'izin' => 'izin',
            'permit' => 'izin',
            'sakit' => 'sakit',
            'out' => 'out',
            'libur' => 'holiday',
            'holiday' => 'holiday',
            'off' => 'off',
        ];

        $normalized = mb_strtolower(trim($status));

        return $statusMap[$normalized] ?? $status;
    }

    protected function findEmployeeId($employeeCode)
    {
        if (isset($this->employeeCache[$employeeCode])) {
            return $this->employeeCache[$employeeCode];
        }

        try {
            $employee = Employee::where('employee_code', $employeeCode)
                ->where('company_id', $this->companyId)
                ->first();

            $this->employeeCache[$employeeCode] = $employee?->id;
        } catch (Throwable $e) {
            Log::error('Error finding employee', [
                'code' => $employeeCode,
                'error' => $e->getMessage(),
            ]);
            $this->employeeCache[$employeeCode] = null;
        }

        return $this->employeeCache[$employeeCode];
    }

    protected function batchUpsert(array $records)
    {
        $chunks = array_chunk($records, 500);

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $record) {
                try {
                    $exists = DB::table('attendance_autologs')
                        ->where('employee_id', $record['employee_id'])
                        ->where('date', $record['date'])
                        ->exists();

                    if ($exists) {
                        DB::table('attendance_autologs')
                            ->where('employee_id', $record['employee_id'])
                            ->where('date', $record['date'])
                            ->update(array_merge($record, ['updated_at' => now()]));
                        $this->updated++;
                    } else {
                        DB::table('attendance_autologs')->insert($record);
                        $this->inserted++;
                    }
                } catch (Throwable $e) {
                    $this->errors[] = 'Gagal upsert: '.$e->getMessage();
                    Log::error('Upsert failed', [
                        'employee_id' => $record['employee_id'] ?? null,
                        'date' => $record['date'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Batch upserted', [
                'batch' => $this->importBatch,
                'chunk' => $chunkIndex + 1,
                'count' => count($chunk),
            ]);
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function onError(Throwable $e)
    {
        $this->errors[] = $e->getMessage();
        Log::error('Import error: '.$e->getMessage());
    }

    public function getInserted(): int
    {
        return $this->inserted;
    }

    public function getUpdated(): int
    {
        return $this->updated;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getImportBatch(): string
    {
        return $this->importBatch;
    }

    public static function runImport(
        int $companyId,
        int $branchId,
        ?int $userId = null,
        string $filePath = 'sampe_data.xlsx'
    ): array {
        $import = new self($companyId, $branchId, $userId, $filePath);

        // Check if filePath is already an absolute path or file exists at given path
        if (file_exists($filePath)) {
            $fullPath = $filePath;
        } else {
            $fullPath = database_path('seeders/'.$filePath);
        }

        if (! file_exists($fullPath)) {
            throw new \Exception('File tidak ditemukan: '.$fullPath);
        }

        $import->import($fullPath);

        return [
            'inserted' => $import->getInserted(),
            'updated' => $import->getUpdated(),
            'errors' => $import->getErrors(),
            'warnings' => $import->getWarnings(),
            'import_batch' => $import->getImportBatch(),
        ];
    }
}
