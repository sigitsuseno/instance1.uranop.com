<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PerbaikanCutiSeeder2025 extends Seeder
{
    protected int $periodId = 2;
    protected string $filename = 'data_cuti_2025-2026.xlsx';

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

    /** @var array<string, int> NIP => employee_id */
    protected array $employeeMap = [];

    /** @var array<string, array{id: int, balance_type: string}> code => info */
    protected array $leaveTypeMap = [];

    /** @var array<string, int> entitlement per leave_type_id */
    protected array $entitlementMap = [];

    /** @var array<string, int> total_used per (employee_id:leave_type_id) — running total */
    protected array $usedTracker = [];

    protected int $createdRequests = 0;
    protected int $updatedRequests = 0;
    protected int $createdDecrements = 0;
    protected int $employeesNotFound = 0;
    protected int $skippedCancelled = 0;
    protected int $skippedEmpty = 0;

    public function run(): void
    {
        echo "\n============================================\n";
        echo "  PERBAIKAN CUTI SEEDER — IMPORT & SALDO\n";
        echo "============================================\n\n";

        $period = LeavePeriod::find($this->periodId);
        if (! $period) {
            echo "ERROR: Leave period ID {$this->periodId} tidak ditemukan!\n";
            return;
        }
        echo "Periode: {$period->name} (ID: {$period->id})\n\n";

        $this->mapEmployees();
        $this->mapLeaveTypes();
        $this->mapEntitlements();

        if (empty($this->employeeMap)) {
            echo "ERROR: Tidak ada karyawan ditemukan!\n";
            return;
        }
        if (empty($this->leaveTypeMap)) {
            echo "ERROR: Tidak ada leave type ditemukan!\n";
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
        echo "Total baris data: " . count($rows) . "\n\n";

        // Urut dari tanggal paling lama
        usort($rows, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));

        foreach ($rows as $i => $row) {
            $nip = $row['nip'];
            $tipeCuti = $row['tipe_cuti'];
            $startDate = $row['start_date'];
            $endDate = $row['end_date'];
            $durasi = (int) $row['durasi'];
            $alasan = $row['alasan'];
            $status = $row['status'];

            if (empty($nip) || empty($tipeCuti) || empty($startDate)) {
                $this->skippedEmpty++;
                continue;
            }
            if ($status === 'Dibatalkan') {
                $this->skippedCancelled++;
                continue;
            }
            if (! isset($this->employeeMap[$nip])) {
                $this->employeesNotFound++;
                if ($this->employeesNotFound <= 10) {
                    echo "  WARN: NIP {$nip} tidak ditemukan\n";
                }
                continue;
            }

            $employeeId = $this->employeeMap[$nip];
            $leaveTypeCode = $this->tipeCutiMapping[$tipeCuti] ?? null;
            if (! $leaveTypeCode || ! isset($this->leaveTypeMap[$leaveTypeCode])) {
                echo "  WARN: Tipe cuti '{$tipeCuti}' tidak dikenal (NIP: {$nip})\n";
                continue;
            }

            $leaveTypeId = $this->leaveTypeMap[$leaveTypeCode]['id'];
            $balanceType = $this->leaveTypeMap[$leaveTypeCode]['balance_type'];

            // --- Cari / buat leave_request ---
            $leaveRequest = LeaveRequest::where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('leave_period_id', $this->periodId)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->first();

            $requestData = [
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
                'updated_by'      => 1,
            ];

            if ($leaveRequest) {
                // Update existing
                $leaveRequest->update([
                    'days_requested' => $durasi,
                    'reason'         => $alasan ?: $leaveRequest->reason,
                    'updated_by'     => 1,
                ]);
                $this->updatedRequests++;
            } else {
                // Create baru
                $leaveRequest = LeaveRequest::create($requestData);
                $this->createdRequests++;
            }

            // --- Buat decrement di employee_leave (hanya untuk balance_type = 'decrement') ---
            if ($balanceType === 'decrement') {
                $key = "{$employeeId}:{$leaveTypeId}";
                $cumulative = ($this->usedTracker[$key] ?? 0) + $durasi;
                $this->usedTracker[$key] = $cumulative;

                $entitlement = $this->entitlementMap[$leaveTypeId] ?? 0;
                $sisa = max(0, $entitlement - $cumulative);

                // Buat decrement record (firstOrCreate agar aman dari duplikat)
                $el = EmployeeLeave::firstOrCreate(
                    [
                        'employee_id'      => $employeeId,
                        'leave_type_id'    => $leaveTypeId,
                        'leave_period_id'  => $this->periodId,
                        'reference_id'     => $leaveRequest->id,
                        'transaction_type' => 'decrement',
                    ],
                    [
                        'amount'      => $durasi,
                        'description' => 'Decrement dari ' . $tipeCuti . ' (' . $startDate . ' s/d ' . $endDate . ')',
                        'created_by'  => 1,
                        'updated_by'  => 1,
                    ]
                );
                if ($el->wasRecentlyCreated) {
                    $this->createdDecrements++;
                }

                // Update sisa_cuti di leave_request
                $leaveRequest->updateQuietly(['sisa_cuti' => $sisa]);
            }

            if (($i + 1) % 100 === 0) {
                echo "  Diproses " . ($i + 1) . " baris...\n";
            }
        }

        echo "\n============================================\n";
        echo "  HASIL AKHIR\n";
        echo "============================================\n";
        echo "Leave requests dibuat   : {$this->createdRequests}\n";
        echo "Leave requests diupdate : {$this->updatedRequests}\n";
        echo "Decrement records       : {$this->createdDecrements}\n";
        echo "Dibatalkan diskip       : {$this->skippedCancelled}\n";
        echo "NIP tidak ditemukan     : {$this->employeesNotFound}\n";
        echo "Data kosong diskip      : {$this->skippedEmpty}\n";

        $totalLR = DB::table('leave_requests')->where('leave_period_id', $this->periodId)->count();
        $totalEL = DB::table('employee_leaves')->where('leave_period_id', $this->periodId)->count();
        echo "\nVERIFIKASI:\n";
        echo "  Total leave_requests  : {$totalLR}\n";
        echo "  Total employee_leaves : {$totalEL}\n";
        echo "\n=== SEEDER COMPLETED ===\n";
    }

    // ================================================================
    // HELPERS
    // ================================================================

    protected function readExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getSheet(0);
        $allRows = $worksheet->toArray(null, true, true, false);
        $dataRows = [];

        foreach ($allRows as $i => $row) {
            if ($i < 4) continue;
            $nip = trim((string) ($row[1] ?? ''));
            $tipeCuti = trim((string) ($row[4] ?? ''));
            $startRaw = trim((string) ($row[5] ?? ''));
            $endRaw = trim((string) ($row[6] ?? ''));
            $durasi = trim((string) ($row[7] ?? ''));
            $status = trim((string) ($row[8] ?? ''));
            $alasan = trim((string) ($row[9] ?? ''));
            if (empty($nip) && empty($tipeCuti)) continue;
            $startDate = $this->parseDate($startRaw);
            $endDate = $this->parseDate($endRaw);
            if (! $startDate) continue;
            $dataRows[] = [
                'nip' => $nip, 'tipe_cuti' => $tipeCuti,
                'start_date' => $startDate, 'end_date' => $endDate ?: $startDate,
                'durasi' => (int) $durasi ?: 1, 'status' => $status, 'alasan' => $alasan,
            ];
        }
        return $dataRows;
    }

    protected function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (empty($raw)) return null;
        if (is_numeric($raw)) {
            $d = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);
            return $d ? $d->format('Y-m-d') : null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt && $dt->format($fmt) === $raw) return $dt->format('Y-m-d');
        }
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    protected function mapEmployees(): void
    {
        foreach (Employee::all() as $emp) {
            if ($emp->nip) {
                $this->employeeMap[(string) $emp->nip] = $emp->id;
            }
        }
        echo "Karyawan terdaftar (by NIP): " . count($this->employeeMap) . "\n";
    }

    protected function mapLeaveTypes(): void
    {
        foreach (LeaveType::all() as $type) {
            $this->leaveTypeMap[$type->code] = [
                'id' => $type->id,
                'balance_type' => $type->balance_type,
            ];
        }
        echo "Leave types terdaftar: " . count($this->leaveTypeMap) . "\n";
    }

    protected function mapEntitlements(): void
    {
        $policies = LeavePolicy::where('entitlement_days', '>', 0)->get();
        foreach ($policies as $p) {
            $this->entitlementMap[$p->leave_type_id] = (int) $p->entitlement_days;
        }
        echo "Entitlements (dari policy): " . count($this->entitlementMap) . " tipe\n";
    }

    protected function findFile(): ?string
    {
        foreach ([
            database_path('seeders/' . $this->filename),
            base_path('sample_data/' . $this->filename),
            storage_path('app/' . $this->filename),
        ] as $path) {
            if (file_exists($path)) return $path;
        }
        return null;
    }
}
