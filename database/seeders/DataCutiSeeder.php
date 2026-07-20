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

class DataCutiSeeder extends Seeder
{
    /**
     * Mapping periode ke file Excel.
     * Key   = label periode (cocokin ke leave_periods.name)
     * Value = [leave_period_id, filename]
     */
    protected array $files = [
        'Periode 2025-2026 (Pasca Lebaran)' => [
            'period_id' => 1,
            'filename'  => 'data_cuti_2025-2026.xlsx',
        ],
        'Periode 2026-2027 (Pasca Lebaran)' => [
            'period_id' => 2,
            'filename'  => 'data_cuti_2026-2027.xlsx',
        ],
    ];

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

    public function run(): void
    {
        echo "\n============================================\n";
        echo "  DATA CUTI SEEDER — 2 Periode\n";
        echo "============================================\n\n";

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

        // Proses per periode
        foreach ($this->files as $periodName => $config) {
            $this->processPeriod($periodName, $config['period_id'], $config['filename']);
        }

        echo "\n============================================\n";
        echo "  HASIL AKHIR\n";
        echo "============================================\n";
        echo "Leave requests dibuat : {$this->createdRequests}\n";
        echo "EmployeeLeave decrement: {$this->createdDecrements}\n";
        echo "NIP tidak ditemukan    : {$this->employeesNotFound}\n";
        echo "\n=== SEEDER COMPLETED ===\n";
    }

    /**
     * Proses satu periode: baca Excel → buat leave_request + employee_leave
     */
    protected function processPeriod(string $periodName, int $periodId, string $filename): void
    {
        $period = LeavePeriod::find($periodId);
        if (! $period) {
            echo "ERROR: Leave period ID {$periodId} tidak ditemukan! Skip.\n";
            return;
        }

        $path = $this->findFile($filename);
        if (! $path) {
            echo "ERROR: File '{$filename}' tidak ditemukan! Skip.\n";
            return;
        }

        echo "\n--- Periode: {$period->name} ---\n";
        echo "File: {$path}\n";

        // Baca Excel
        $rows = $this->readExcel($path);
        $dataRows = $rows['data']; // sudah tanpa header
        echo "Total baris data: " . count($dataRows) . "\n";

        // Reset running balance untuk periode baru
        // (setiap periode punya saldo awal sendiri dari employee_leaves)
        $this->runningBalance = [];

        // Sort by start_date ascending biar sisa_cuti kronologis
        usort($dataRows, function ($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        $periodRequests = 0;
        $periodDecrements = 0;
        $periodNotFound = 0;
        $skipped = 0;

        foreach ($dataRows as $i => $row) {
            $nip       = $row['nip'];
            $tipeCuti  = $row['tipe_cuti'];
            $startDate = $row['start_date'];
            $endDate   = $row['end_date'];
            $durasi    = (int) $row['durasi'];
            $alasan    = $row['alasan'];

            if (empty($nip) || empty($tipeCuti) || empty($startDate)) {
                $skipped++;
                continue;
            }

            // Cari employee
            if (! isset($this->employeeMap[$nip])) {
                $periodNotFound++;
                if ($periodNotFound <= 10) {
                    echo "  WARN: NIP {$nip} tidak ditemukan\n";
                }
                continue;
            }

            $employeeId = $this->employeeMap[$nip];

            // Cari leave type
            $leaveTypeCode = $this->tipeCutiMapping[$tipeCuti] ?? null;
            if (! $leaveTypeCode || ! isset($this->leaveTypeMap[$leaveTypeCode])) {
                if ($skipped < 5) {
                    echo "  WARN: Tipe cuti '{$tipeCuti}' tidak dikenal\n";
                }
                $skipped++;
                continue;
            }

            $leaveTypeId    = $this->leaveTypeMap[$leaveTypeCode]['id'];
            $balanceType    = $this->leaveTypeMap[$leaveTypeCode]['balance_type'];

            // Status: semua "Disetujui" → approved
            $status = 'approved';

            DB::transaction(function () use (
                $employeeId, $leaveTypeId, $periodId, $startDate, $endDate,
                $durasi, $alasan, $status, $balanceType, &$periodRequests, &$periodDecrements
            ) {
                // 1. Create leave_request
                $leaveRequest = LeaveRequest::create([
                    'uuid'            => (string) Str::uuid(),
                    'employee_id'     => $employeeId,
                    'leave_type_id'   => $leaveTypeId,
                    'leave_period_id' => $periodId,
                    'start_date'      => $startDate,
                    'end_date'        => $endDate,
                    'days_requested'  => $durasi,
                    'reason'          => $alasan ?: 'Import data cuti',
                    'status'          => $status,
                    'approved_by'     => 1,
                    'approved_at'     => now(),
                    'created_by'      => 1,
                ]);
                $periodRequests++;

                // 2. Jika balance_type = decrement → UPDATE employee_leave + hitung sisa_cuti
                if ($balanceType === 'decrement') {
                    // Cari record employee_leave yang jadi "saldo berjalan"
                    // Prioritas: cari transaction_type 'initial' atau 'increment' terbaru
                    $balanceRecord = EmployeeLeave::where('employee_id', $employeeId)
                        ->where('leave_type_id', $leaveTypeId)
                        ->where('leave_period_id', $periodId)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($balanceRecord) {
                        // Decrement amount langsung di record yang ada
                        $sisa = (int) $balanceRecord->amount - $durasi;
                        $balanceRecord->updateQuietly(['amount' => $sisa]);
                    } else {
                        // Fallback: bikin record baru dengan default 12, lalu decrement
                        $sisa = 12 - $durasi;
                        EmployeeLeave::create([
                            'uuid'             => (string) Str::uuid(),
                            'employee_id'      => $employeeId,
                            'leave_type_id'    => $leaveTypeId,
                            'leave_period_id'  => $periodId,
                            'amount'           => $sisa,
                            'description'      => 'Auto-created from data cuti import',
                            'created_by'       => 1,
                        ]);
                    }
                    $periodDecrements++;

                    // Set sisa_cuti di leave_request
                    $leaveRequest->updateQuietly(['sisa_cuti' => max(0, $sisa)]);
                }
            });

            if (($i + 1) % 100 === 0) {
                echo "  Diproses " . ($i + 1) . " baris...\n";
            }
        }

        echo "\nHasil periode ini:\n";
        echo "  Leave requests : {$periodRequests}\n";
        echo "  Decrements     : {$periodDecrements}\n";
        echo "  NIP not found  : {$periodNotFound}\n";
        echo "  Skipped        : {$skipped}\n";

        $this->createdRequests   += $periodRequests;
        $this->createdDecrements += $periodDecrements;
        $this->employeesNotFound += $periodNotFound;
    }

    /**
     * Baca file Excel, return array of data rows.
     */
    protected function readExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet   = $spreadsheet->getSheet(0); // Sheet pertama

        $allRows   = $worksheet->toArray(null, true, true, false);
        $dataRows  = [];

        // Kolom index (0-based):
        // 0=No, 1=NIP, 2=Nama, 3=Departemen, 4=Tipe Cuti, 5=Tgl Mulai, 6=Tgl Selesai, 7=Durasi, 8=Status, 9=Alasan
        foreach ($allRows as $i => $row) {
            // Skip 4 baris header (row 1-4 di Excel, index 0-3 di array)
            if ($i < 4) {
                continue;
            }

            $nip      = trim((string) ($row[1] ?? ''));
            $tipeCuti = trim((string) ($row[4] ?? ''));
            $startRaw = trim((string) ($row[5] ?? ''));
            $endRaw   = trim((string) ($row[6] ?? ''));
            $durasi   = trim((string) ($row[7] ?? ''));
            $alasan   = trim((string) ($row[9] ?? ''));

            // Skip baris kosong
            if (empty($nip) && empty($tipeCuti)) {
                continue;
            }

            // Parse tanggal (bisa string date atau Excel serial number)
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
                'alasan'     => $alasan,
            ];
        }

        return ['data' => $dataRows];
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

        // Cek apakah ini Excel serial number (integer/float)
        if (is_numeric($raw)) {
            // Excel serial number → gunakan PhpSpreadsheet helper
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
     * Ambil saldo saat ini dari employee_leaves (increment + initial - decrement).
     */
    protected function getCurrentBalance(int $employeeId, int $leaveTypeId, int $periodId): int
    {
        $balance = EmployeeLeave::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_period_id', $periodId)
            ->sum('amount');
        return (int) ($balance);
    }

    /**
     * Mapping NIP → employee_id
     */
    protected function mapEmployees(): void
    {
        $employees = Employee::where('is_active', true)->get();

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
                'name'         => $type->name,
                'balance_type' => $type->balance_type,
            ];
        }

        echo "Leave types terdaftar: " . count($this->leaveTypeMap) . "\n";
        echo "Mapping tipe cuti: " . implode(', ', array_keys($this->tipeCutiMapping)) . "\n";
    }

    /**
     * Cari file Excel di beberapa path.
     */
    protected function findFile(string $filename): ?string
    {
        $paths = [
            database_path('seeders/' . $filename),
            base_path('sample_data/' . $filename),
            storage_path('app/' . $filename),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
