<?php

namespace App\Modules\Supervisor\Attendance\Imports;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Settings\Models\EmployeeGroup;
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

    protected ?int $companyId = null;

    protected ?int $branchId = null;

    protected ?int $userId;

    protected string $importBatch;

    protected string $filePath;

    protected int $inserted = 0;

    protected int $updated = 0;

    protected array $errors = [];

    protected array $warnings = [];

    protected array $employeeCache = [];

    protected array $employeeTypeCache = [];

    protected array $employeeGroupCache = [];

    protected int $employeeCodeColumn = 0;

    protected int $dateColumn = 1;

    protected int $checkInColumn = 2;

    protected int $checkOutColumn = 3;

    protected int $statusColumn = 4;

    public function __construct(
        ?int $companyId = null,
        ?int $branchId = null,
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

        // Ambil tanggal dari header (mulai kolom index 2, lompat 2)
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

            // ─── Pengecekan Group ───────────────────────────────────
            // GRP-JKT di-skip karena sudah ada service khusus
            $employeeGroup = $this->getEmployeeGroup($employeeId);
            if ($employeeGroup === 'GRP-JKT') {
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

    // ═══════════════════════════════════════════════════════════════
    //  PROSES UTAMA — dispatch ke pattern sesuai employee_type
    // ═══════════════════════════════════════════════════════════════

    protected function processRow(array $rowValues, int $rowNumber, int $employeeId, array $dates): array
    {
        $result = [
            'records' => [],
            'errors' => [],
            'warnings' => [],
        ];

        foreach ($dates as $colIndex => $date) {
            $status = trim($rowValues[$colIndex] ?? '');
            $lemburRaw = trim($rowValues[$colIndex + 1] ?? '');

            try {
                $roster = EmployeeShiftRoster::where('employee_id', $employeeId)
                    ->where('date', $date->toDateString())
                    ->with('workPattern', 'shift')
                    ->first();

                $leave = LeaveRequest::where('employee_id', $employeeId)
                    ->where('start_date', '<=', $date->toDateString())
                    ->where('end_date', '>=', $date->toDateString())
                    ->with('leaveType')
                    ->first();

                // Tentukan employee type
                $employeeType = $this->resolveEmployeeType($employeeId, $roster);

                $record = match ($employeeType) {
                    'FIXED'      => $this->processFixedPattern($employeeId, $date, $status, $lemburRaw, $roster, $leave, $rowNumber),
                    'FLEX-SHIFT' => $this->processFlexShiftPattern($employeeId, $date, $status, $lemburRaw, $roster, $leave, $rowNumber),
                    'SHIFT'      => $this->processShiftPattern($employeeId, $date, $status, $lemburRaw, $roster, $leave, $rowNumber),
                    default      => null,
                };

                if ($record) {
                    $result['records'][] = $record;
                }

            } catch (Throwable $e) {
                $result['errors'][] = "Baris {$rowNumber} Tgl {$date->toDateString()}: Gagal memproses data - ".$e->getMessage();
            }
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN A — FIXED
    // ═══════════════════════════════════════════════════════════════

    protected function processFixedPattern(
        int $employeeId,
        Carbon $date,
        string $status,
        string $lemburRaw,
        ?EmployeeShiftRoster $roster,
        ?LeaveRequest $leave,
        int $rowNumber
    ): ?array {
        $lemburHours = is_numeric($lemburRaw) ? (float) $lemburRaw : 0;
        $lemburMinutes = (int) ($lemburHours * 60);
        $holidayOvertime = 0;

        // Jadwal shift
        $shiftStart = ($roster && $roster->shift && $roster->shift->work_hour_start)
            ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_start)
            : Carbon::parse($date->toDateString().' 08:00:00');

        $shiftEnd = ($roster && $roster->shift && $roster->shift->work_hour_end)
            ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_end)
            : Carbon::parse($date->toDateString().' 17:00:00');

        $actualIn = $shiftStart->copy();
        $actualOut = $shiftEnd->copy();

        // ── a.1 Minggu → off ──
        if ($roster && $roster->is_sun) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'off', 0, 0, 0, 0, 0, 0);
        }

        // ── a.2 Holiday → off ──
        if ($roster && $roster->is_holiday) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'holiday', 0, 0, 0, 0, 0, 0);
        }

        // ── a.5 Cuti / Sakit / Izin ──
        //    check_in & check_out = null (karyawan tidak masuk),
        //    actual_in & actual_out tetap dari jadwal
        $arrayCuti = ['CTM', 'CTH', 'CH', 'CTI', 'CTK', 'CKM', 'CM', 'CUTI', 'CT'];

        if (in_array($status, $arrayCuti)) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'leave', 0, 0, 0, 0, 0, 0);
        }

        if ($status === 'SAKIT') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'sakit', 0, 1, 0, 0, 0, 0);
        }

        if ($status === 'IZIN') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'izin', 0, 0, 1, 0, 0, 0);
        }

        if ($status === 'OFF') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'off', 0, 0, 0, 0, 0, 0);
        }

        if (in_array($status, ['OUT', '-', 'ALFA', ''])) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'absent', 1, 0, 0, 0, 0, 0);
        }

        // ── a.3 Work day (Senin-Jumat) & a.4 Sabtu ──
        //    Ambil lembur, actual_in & actual_out (jadwal),
        //    check_out selalu + lembur setelah actual_out
        //    Randomisasi kecil biar menit nggak kaku (0-15 menit telat, ±10 menit pulang)
        $checkIn = $actualIn->copy()->addMinutes(rand(0, 15));
        $checkOut = $actualOut->copy()->addMinutes(rand(-10, 10));

        if ($lemburMinutes > 0) {
            $checkOut = $checkOut->addMinutes($lemburMinutes);
        }

        return $this->buildRecord($employeeId, $date, $checkIn, $checkOut, $actualIn, $actualOut, $roster, $leave, 'present', 0, 0, 0, $lemburMinutes, $lemburHours, 0);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN B — FLEX-SHIFT
    // ═══════════════════════════════════════════════════════════════

    protected function processFlexShiftPattern(
        int $employeeId,
        Carbon $date,
        string $status,
        string $lemburRaw,
        ?EmployeeShiftRoster $roster,
        ?LeaveRequest $leave,
        int $rowNumber
    ): ?array {
        $lemburHours = is_numeric($lemburRaw) ? (float) $lemburRaw : 0;
        $lemburMinutes = (int) ($lemburHours * 60);
        $holidayOvertime = 0;

        // Jadwal shift
        $shiftStart = ($roster && $roster->shift && $roster->shift->work_hour_start)
            ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_start)
            : Carbon::parse($date->toDateString().' 08:00:00');

        $shiftEnd = ($roster && $roster->shift && $roster->shift->work_hour_end)
            ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_end)
            : Carbon::parse($date->toDateString().' 17:00:00');

        $actualIn = $shiftStart->copy();
        $actualOut = $shiftEnd->copy();

        $shiftCode = $roster ? $roster->external_code : null;

        // ── b.1 Minggu → off ──
        if ($roster && $roster->is_sun) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'off', 0, 0, 0, 0, 0, 0);
        }

        // ── b.2 Holiday → off ──
        if ($roster && $roster->is_holiday) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'holiday', 0, 0, 0, 0, 0, 0);
        }

        // ── b.5 Cuti / Sakit / Izin ──
        //    check_in & check_out = null (karyawan tidak masuk),
        //    actual_in & actual_out tetap dari jadwal
        $arrayCuti = ['CTM', 'CTH', 'CH', 'CTI', 'CTK', 'CKM', 'CM', 'CUTI', 'CT'];

        if (in_array($status, $arrayCuti)) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'leave', 0, 0, 0, 0, 0, 0);
        }

        if ($status === 'SAKIT') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'sakit', 0, 1, 0, 0, 0, 0);
        }

        if ($status === 'IZIN') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'izin', 0, 0, 1, 0, 0, 0);
        }

        if ($status === 'OFF') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'off', 0, 0, 0, 0, 0, 0);
        }

        if (in_array($status, ['OUT', '-', 'ALFA'])) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'absent', 1, 0, 0, 0, 0, 0);
        }

        // ── b.4 Sabtu → lembur setelah actual_out ──
        if ($roster && $roster->is_sat) {
            $checkIn = $actualIn->copy()->addMinutes(rand(0, 15));
            $checkOut = $actualOut->copy()->addMinutes(rand(-10, 10));

            if ($lemburMinutes > 0) {
                $checkOut = $checkOut->addMinutes($lemburMinutes);
            }

            return $this->buildRecord($employeeId, $date, $checkIn, $checkOut, $actualIn, $actualOut, $roster, $leave, 'present', 0, 0, 0, $lemburMinutes, $lemburHours, 0);
        }

        // ── b.3 Work day (Senin-Jumat) ──
        $checkIn = $actualIn->copy()->addMinutes(rand(0, 15));
        $checkOut = $actualOut->copy()->addMinutes(rand(-10, 10));

        if ($lemburMinutes > 0) {
            if ($shiftCode === 'P') {
                // Shift Pagi: check_out + lembur
                $checkOut = $checkOut->addMinutes($lemburMinutes);
            } elseif ($shiftCode === 'S') {
                // Shift Siang (jadwal 15.00-23.00): check_in - lembur
                $checkIn = $checkIn->subMinutes($lemburMinutes);
            } else {
                // Fallback: check_out + lembur
                $checkOut = $checkOut->addMinutes($lemburMinutes);
            }
        }

        return $this->buildRecord($employeeId, $date, $checkIn, $checkOut, $actualIn, $actualOut, $roster, $leave, 'present', 0, 0, 0, $lemburMinutes, $lemburHours, 0);
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATTERN C — SHIFT (Satpam)
    // ═══════════════════════════════════════════════════════════════

    protected function processShiftPattern(
        int $employeeId,
        Carbon $date,
        string $status,
        string $lemburRaw,
        ?EmployeeShiftRoster $roster,
        ?LeaveRequest $leave,
        int $rowNumber
    ): ?array {
        $lemburHours = is_numeric($lemburRaw) ? (float) $lemburRaw : 0;
        $lemburMinutes = (int) ($lemburHours * 60);
        $holidayOvertime = 0;

        // Jadwal shift
        $shiftStart = ($roster && $roster->shift && $roster->shift->work_hour_start)
            ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_start)
            : Carbon::parse($date->toDateString().' 08:00:00');

        $shiftEnd = ($roster && $roster->shift && $roster->shift->work_hour_end)
            ? Carbon::parse($date->toDateString().' '.$roster->shift->work_hour_end)
            : Carbon::parse($date->toDateString().' 17:00:00');

        $actualIn = $shiftStart->copy();
        $actualOut = $shiftEnd->copy();

        // Shift code utk penentuan lembur === 4
        $shiftCode = $roster ? strtoupper(trim($roster->external_code ?? '')) : '';

        // ── c.3 Status OFF → off ──
        if ($status === 'OFF') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'off', 0, 0, 0, 0, 0, 0);
        }

        // ── Sakit / Izin / Cuti ──
        //    check_in & check_out = null (karyawan tidak masuk),
        //    actual_in & actual_out tetap dari jadwal
        $arrayCuti = ['CTM', 'CTH', 'CH', 'CTI', 'CTK', 'CKM', 'CM', 'CUTI', 'CT'];

        if (in_array($status, $arrayCuti)) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'leave', 0, 0, 0, 0, 0, 0);
        }

        if ($status === 'SAKIT') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'sakit', 0, 1, 0, 0, 0, 0);
        }

        if ($status === 'IZIN') {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'izin', 0, 0, 1, 0, 0, 0);
        }

        if (in_array($status, ['OUT', '-', 'ALFA', ''])) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'absent', 1, 0, 0, 0, 0, 0);
        }

        if (empty($status)) {
            return $this->buildRecord($employeeId, $date, null, null, $actualIn, $actualOut, $roster, $leave, 'off', 0, 0, 0, 0, 0, 0);
        }

        // ── c.2 Status L → Lembur hari libur ──
        //    Lembur tidak mempengaruhi check_in/check_out, kecuali jika === 4 jam
        if ($status === 'L') {
            $checkIn = $actualIn->copy()->addMinutes(rand(-15, 5));
            $checkOut = $actualOut->copy()->addMinutes(rand(-10, 10));
            $holidayOvertime = 1;

            if ($lemburHours == 4) {
                if (in_array($shiftCode, ['ML', 'S'])) {
                    $checkIn = $checkIn->subMinutes($lemburMinutes);
                } else {
                    $checkOut = $checkOut->addMinutes($lemburMinutes);
                }
            }

            return $this->buildRecord($employeeId, $date, $checkIn, $checkOut, $actualIn, $actualOut, $roster, $leave, 'present', 0, 0, 0, $lemburMinutes, $lemburHours, $holidayOvertime);
        }

        // ── c.1 Status H → Hadir ──
        //    Lembur tidak mempengaruhi check_in/check_out, kecuali jika === 4 jam
        if ($status === 'H') {
            $checkIn = $actualIn->copy()->addMinutes(rand(0, 15));
            $checkOut = $actualOut->copy()->addMinutes(rand(-10, 10));

            if ($lemburHours == 4) {
                if (in_array($shiftCode, ['ML', 'S'])) {
                    $checkIn = $checkIn->subMinutes($lemburMinutes);
                } else {
                    $checkOut = $checkOut->addMinutes($lemburMinutes);
                }
            }

            return $this->buildRecord($employeeId, $date, $checkIn, $checkOut, $actualIn, $actualOut, $roster, $leave, 'present', 0, 0, 0, $lemburMinutes, $lemburHours, 0);
        }

        // ── Fallback: status lain → present biasa ──
        $checkIn = $actualIn->copy()->addMinutes(rand(0, 15));
        $checkOut = $actualOut->copy()->addMinutes(rand(-10, 10));

        return $this->buildRecord($employeeId, $date, $checkIn, $checkOut, $actualIn, $actualOut, $roster, $leave, 'present', 0, 0, 0, 0, 0, 0);
    }

    // ═══════════════════════════════════════════════════════════════
    //  BUILD RECORD — helper bikin array record attendance
    // ═══════════════════════════════════════════════════════════════

    protected function buildRecord(
        int $employeeId,
        Carbon $date,
        ?Carbon $checkIn,
        ?Carbon $checkOut,
        Carbon $actualIn,
        Carbon $actualOut,
        ?EmployeeShiftRoster $roster,
        ?LeaveRequest $leave,
        string $status,
        int $deduct,
        int $sakit,
        int $izin,
        int $lemburMinutes,
        float $lemburHours,
        int $holidayOvertime
    ): array {
        $normalizedStatus = $this->normalizeStatus($status);

        return [
            'company_id' => 1,
            'branch_id' => null,
            'employee_id' => $employeeId,
            'employee_shift_roster_id' => $roster?->id,
            'date' => $date->toDateString(),
            'check_in' => $checkIn?->format('Y-m-d H:i:s'),
            'check_out' => $checkOut?->format('Y-m-d H:i:s'),
            'actual_in' => $actualIn->format('Y-m-d H:i:s'),
            'actual_out' => $actualOut->format('Y-m-d H:i:s'),
            'check_in_log_id' => null,
            'check_out_log_id' => null,
            'import_batch' => $this->importBatch,
            'status' => $normalizedStatus,
            'late_duration' => 0,
            'early_leave_duration' => 0,
            'lembur' => $lemburMinutes,
            'deduct_attendance' => $deduct,
            'is_half_day' => $roster?->is_half_day ?? 0,
            'is_sun' => $roster?->is_sun ?? 0,
            'is_sat' => $roster?->is_sat ?? 0,
            'is_holiday' => $roster?->is_holiday ?? 0,
            'is_leave' => ($leave !== null) ? 1 : ($roster?->is_leave ?? 0),
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
            'lembur_calc' => $lemburHours,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  RESOLVE EMPLOYEE TYPE
    // ═══════════════════════════════════════════════════════════════

    protected function resolveEmployeeType(int $employeeId, ?EmployeeShiftRoster $roster): string
    {
        if ($roster && $roster->workPattern) {
            $employeeType = $roster->workPattern->employee_type;
            $this->employeeTypeCache[$employeeId] = $employeeType;
        } else {
            if (isset($this->employeeTypeCache[$employeeId])) {
                $employeeType = $this->employeeTypeCache[$employeeId];
            } else {
                $otherRoster = EmployeeShiftRoster::where('employee_id', $employeeId)
                    ->whereNotNull('work_pattern_id')
                    ->with('workPattern')
                    ->orderBy('date', 'desc')
                    ->first();
                $employeeType = $otherRoster && $otherRoster->workPattern ? $otherRoster->workPattern->employee_type : 'SHIFT';
                $this->employeeTypeCache[$employeeId] = $employeeType;
            }
        }

        return $employeeType;
    }

    // ═══════════════════════════════════════════════════════════════
    //  GROUP — cari reference_code grup karyawan
    // ═══════════════════════════════════════════════════════════════

    protected function getEmployeeGroup(int $employeeId): ?string
    {
        if (isset($this->employeeGroupCache[$employeeId])) {
            return $this->employeeGroupCache[$employeeId];
        }

        $group = EmployeeGroup::where('employee_id', $employeeId)->first();
        $this->employeeGroupCache[$employeeId] = $group?->reference_code;

        return $this->employeeGroupCache[$employeeId];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPERS — parsing, konversi, normalisasi
    // ═══════════════════════════════════════════════════════════════

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
            'ct' => 'leave',
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
            $employee = Employee::where('nip', $employeeCode)
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
        ?int $companyId = null,
        ?int $branchId = null,
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
