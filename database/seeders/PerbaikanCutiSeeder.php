<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PerbaikanCutiSeeder extends Seeder
{
    /** Periode untuk file ini */
    protected string $periodName = 'Periode 2026-2027 (Pasca Lebaran)';
    protected int $periodId = 2;

    /** Nama file Excel */
    protected string $filename = 'perbaikan_cuti.xlsx';

    /**
     * Mapping "Tipe Cuti" (dari Excel) → leave_type code
     */
    protected array $tipeCutiMapping = [
        'Cuti Tahunan'              => 'CT',
        'Cuti Menikah'              => 'CM',
        'Cuti Keluarga Meninggal'   => 'CKM',
        'Cuti Hajatan'              => 'CH',
        'Cuti Melahirkan'           => 'CTM',
        'Cuti Keguguran'            => 'CTK',
        'Sakit'                     => 'SKT',
        'Cuti Haid'                 => 'CTH',
        'Cuti Ibadah'               => 'CTI',
        'Izin Tidak Masuk'          => 'ITM',
        'Izin Masuk Terlambat'      => 'IMT',
        'Izin Pulang Awal'          => 'IPA',
        'Cuti Bersama'              => 'CB',
        'Izin'                      => 'IZN',
    ];

    /** @var array<string, int> NIP → employee_id */
    protected array $employeeMap = [];

    /** @var array<string, array> code → [id, name, balance_type] */
    protected array $leaveTypeMap = [];

    /** @var array<string, int> employee_id:leave_type_id → running balance */
    protected array $runningBalance = [];

    protected int $createdRequests = 0;
    protected int $createdDecrements = 0;
    protected int $employeesNotFound = 0;
    protected int $skippedDuplicates = 0;
    protected int $skippedCancelled = 0;
    protected int $skippedEmpty = 0;

    /**
     * Cache untuk cek duplikasi — key: employee_id:leave_type_id:period_id:start_date:end_date
     */
    protected array $existingRequests = [];

    /**
     * Cache untuk mencegah decrement ganda pada EmployeeLeave yang sama.
     */
    protected array $decrementedBalances = [];

    public function run(): void
    {
        echo "\n============================================\n";
        echo "  PERBAIKAN CUTI SEEDER\n";
        echo "============================================\n\n";

        $period = LeavePeriod::find($this->periodId);
        if (! $period) {
            echo "ERROR: Leave period ID {$this->periodId} tidak ditemukan!\n";
            return;
        }

        echo "Periode: {$period->name} (ID: {$period->id})\n\n";

        $this->mapEmployees();
        $this->mapLeaveTypes();

        if (empty($this->employeeMap)) {
            echo "ERROR: Tidak ada karyawan ditemukan di database!\n";
            return;
        }

        if (empty($this->leaveTypeMap)) {
            echo "ERROR: Tidak ada leave type ditemukan di database!\n";
            return;
        }

        // Baca Excel
        $path = $this->findFile();
        if (! $path) {
            echo "ERROR: File '{$this->filename}' tidak ditemukan!\n";
            return;
        }

        echo "File: {$path}\n";

        $rows = $this->readExcel($path);
        echo "Total baris data: " . count($rows) . "\n";

        // Pre-load existing leave requests untuk deteksi duplikasi
        $existing = LeaveRequest::where('leave_period_id', $this->periodId)->get();
        foreach ($existing as $lr) {
            $key = $this->uniqueKey($lr->employee_id, $lr->leave_type_id, $this->periodId, $lr->start_date, $lr->end_date);
            $this->existingRequests[$key] = true;
        }
        echo "Pre-loaded " . count($existing) . " existing leave requests untuk cek duplikasi.\n\n";

        // Sort by start_date ascending biar sisa_cuti kronologis
        usort($rows, function ($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        foreach ($rows as $i => $row) {
            $nip       = $row['nip'];
            $tipeCuti  = $row['tipe_cuti'];
            $startDate = $row['start_date'];
            $endDate   = $row['end_date'];
            $durasi    = (int) $row['durasi'];
            $alasan    = $row['alasan'];
            $status    = $row['status'];

            if (empty($nip) || empty($tipeCuti) || empty($startDate)) {
                $this->skippedEmpty++;
                continue;
            }

            // Skip yang statusnya Dibatalkan
            if ($status === 'Dibatalkan') {
                $this->skippedCancelled++;
                continue;
            }

            // Cari employee
            if (! isset($this->employeeMap[$nip])) {
                $this->employeesNotFound++;
                if ($this->employeesNotFound <= 10) {
                    echo "  WARN: NIP {$nip} tidak ditemukan\n";
                }
                continue;
            }

            $employeeId = $this->employeeMap[$nip];

            // Cari leave type
            $leaveTypeCode = $this->tipeCutiMapping[$tipeCuti] ?? null;
            if (! $leaveTypeCode || ! isset($this->leaveTypeMap[$leaveTypeCode])) {
                echo "  WARN: Tipe cuti '{$tipeCuti}' tidak dikenal (NIP: {$nip})\n";
                continue;
            }

            $leaveTypeId    = $this->leaveTypeMap[$leaveTypeCode]['id'];
            $balanceType    = $this->leaveTypeMap[$leaveTypeCode]['balance_type'];

            // CEK DUPLIKASI
            $uniqueKey = $this->uniqueKey($employeeId, $leaveTypeId, $this->periodId, $startDate, $endDate);
            if (isset($this->existingRequests[$uniqueKey])) {
                $this->skippedDuplicates++;
                continue;
            }

            DB::transaction(function () use (
                $employeeId, $leaveTypeId, $startDate, $endDate,
                $durasi, $alasan, $balanceType, $uniqueKey
            ) {
                // 1. Create leave_request
                $leaveRequest = LeaveRequest::create([
                    'uuid'            => (string) Str::uuid(),
                    'employee_id'     => $employeeId,
                    'leave_type_id'   => $leaveTypeId,
                    'leave_period_id' => $this->periodId,
                    'start_date'      => $startDate,
                    'end_date'        => $endDate,
                    'days_requested'  => $durasi,
                    'reason'          => $alasan ?: 'Import perbaikan cuti',
                    'status'          => 'approved',
                    'approved_by'     => 1,
                    'approved_at'     => now(),
                    'created_by'      => 1,
                ]);
                $this->createdRequests++;

                // Tandai di cache supaya baris duplikat berikutnya juga diskip
                $this->existingRequests[$uniqueKey] = true;

                // 2. Jika balance_type = decrement → UPDATE employee_leave + hitung sisa_cuti
                if ($balanceType === 'decrement') {
                    $balanceKey = "{$employeeId}:{$leaveTypeId}:{$this->periodId}";
                    if (! isset($this->decrementedBalances[$balanceKey])) {
                        $this->decrementedBalances[$balanceKey] = true;

                        // Cari record employee_leave yang jadi "saldo berjalan"
                        $balanceRecord = EmployeeLeave::where('employee_id', $employeeId)
                            ->where('leave_type_id', $leaveTypeId)
                            ->where('leave_period_id', $this->periodId)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if ($balanceRecord) {
                            $sisa = (int) $balanceRecord->amount - $durasi;
                            $balanceRecord->updateQuietly(['amount' => $sisa]);
                        } else {
                            // Fallback: bikin record baru dengan default 12, lalu decrement
                            $sisa = 12 - $durasi;
                            EmployeeLeave::create([
                                'uuid'             => (string) Str::uuid(),
                                'employee_id'      => $employeeId,
                                'leave_type_id'    => $leaveTypeId,
                                'leave_period_id'  => $this->periodId,
                                'amount'           => $sisa,
                                'description'      => 'Auto-created from perbaikan cuti import',
                                'created_by'       => 1,
                            ]);
                        }
                        $this->createdDecrements++;

                        // Set sisa_cuti di leave_request
                        $leaveRequest->updateQuietly(['sisa_cuti' => max(0, $sisa)]);
                    }
                }
            });

            if (($i + 1) % 100 === 0) {
                echo "  Diproses " . ($i + 1) . " baris...\n";
            }
        }

        echo "\n============================================\n";
        echo "  HASIL AKHIR\n";
        echo "============================================\n";
        echo "Leave requests dibuat : {$this->createdRequests}\n";
        echo "EmployeeLeave decrement: {$this->createdDecrements}\n";
        echo "NIP tidak ditemukan    : {$this->employeesNotFound}\n";
        echo "Duplikat diskip        : {$this->skippedDuplicates}\n";
        echo "Dibatalkan diskip      : {$this->skippedCancelled}\n";
        echo "Data kosong diskip     : {$this->skippedEmpty}\n";
        echo "\n=== SEEDER COMPLETED ===\n";
    }

    /**
     * Generate unique key untuk deteksi duplikasi.
     */
    protected function uniqueKey(int $employeeId, int $leaveTypeId, int $periodId, string $startDate, string $endDate): string
    {
        return "{$employeeId}:{$leaveTypeId}:{$periodId}:{$startDate}:{$endDate}";
    }

    /**
     * Baca file Excel, return array of data rows.
     */
    protected function readExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet   = $spreadsheet->getSheet(0);

        $allRows  = $worksheet->toArray(null, true, true, false);
        $dataRows = [];

        // Kolom index (0-based):
        // 0=No, 1=NIP, 2=Nama, 3=Departemen, 4=Tipe Cuti, 5=Tgl Mulai, 6=Tgl Selesai, 7=Durasi, 8=Status, 9=Alasan
        foreach ($allRows as $i => $row) {
            // Skip 4 baris header
            if ($i < 4) {
                continue;
            }

            $nip      = trim((string) ($row[1] ?? ''));
            $tipeCuti = trim((string) ($row[4] ?? ''));
            $startRaw = trim((string) ($row[5] ?? ''));
            $endRaw   = trim((string) ($row[6] ?? ''));
            $durasi   = trim((string) ($row[7] ?? ''));
            $status   = trim((string) ($row[8] ?? ''));
            $alasan   = trim((string) ($row[9] ?? ''));

            // Skip baris kosong
            if (empty($nip) && empty($tipeCuti)) {
                continue;
            }

            $startDate = $this->parseDate($startRaw);
            $endDate   = $this->parseDate($endRaw);

            if (! $startDate) {
                continue;
            }

            $dataRows[] = [
                'nip'        => $nip,
                'tipe_cuti'  => $tipeCuti,
                'start_date' => $startDate,
                'end_date'   => $endDate ?: $startDate,
                'durasi'     => (int) $durasi ?: 1,
                'status'     => $status,
                'alasan'     => $alasan,
            ];
        }

        return $dataRows;
    }

    /**
     * Parse tanggal dari Excel: bisa string 'Y-m-d' atau serial number.
     */
    protected function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (empty($raw)) {
            return null;
        }

        // Cek apakah ini Excel serial number
        if (is_numeric($raw)) {
            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);
            return $date ? $date->format('Y-m-d') : null;
        }

        // Coba parse string date
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt && $dt->format($format) === $raw) {
                return $dt->format('Y-m-d');
            }
        }

        // Fallback: strtotime
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Mapping NIP → employee_id
     */
    protected function mapEmployees(): void
    {
        $employees = Employee::orderBy('id', 'asc')->get();

        foreach ($employees as $emp) {
            if ($emp->nip) {
                $this->employeeMap[(string) $emp->nip] = $emp->id;
            }
        }

        echo "Karyawan terdaftar (by NIP): " . count($this->employeeMap) . "\n";
    }

    /**
     * Mapping leave_type code → [id, name, balance_type]
     */
    protected function mapLeaveTypes(): void
    {
        $types = LeaveType::all();

        foreach ($types as $type) {
            $this->leaveTypeMap[$type->code] = [
                'id'           => $type->id,
                'balance_type' => $type->balance_type,
            ];
        }

        echo "Leave types terdaftar: " . count($this->leaveTypeMap) . "\n";
    }

    /**
     * Cari file Excel di beberapa path.
     */
    protected function findFile(): ?string
    {
        $paths = [
            database_path('seeders/' . $this->filename),
            base_path('sample_data/' . $this->filename),
            storage_path('app/' . $this->filename),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
