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
    protected int $periodId = 2;
    protected string $filename = 'perbaikan_cuti.xlsx';

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

    protected array $employeeMap = [];
    protected array $leaveTypeMap = [];

    protected int $deletedDuplicates = 0;
    protected int $createdRequests = 0;
    protected int $employeesNotFound = 0;
    protected int $skippedDuplicates = 0;
    protected int $skippedCancelled = 0;
    protected int $skippedEmpty = 0;
    protected int $recalculatedBalances = 0;

    public function run(): void
    {
        echo "\n============================================\n";
        echo "  PERBAIKAN CUTI SEEDER — CLEANUP & IMPORT\n";
        echo "============================================\n\n";

        $period = LeavePeriod::find($this->periodId);
        if (! $period) { echo "ERROR: Leave period ID {$this->periodId} tidak ditemukan!\n"; return; }
        echo "Periode: {$period->name} (ID: {$period->id})\n\n";

        $this->mapEmployees();
        $this->mapLeaveTypes();
        if (empty($this->employeeMap)) { echo "ERROR: Tidak ada karyawan ditemukan!\n"; return; }
        if (empty($this->leaveTypeMap)) { echo "ERROR: Tidak ada leave type ditemukan!\n"; return; }

        // === PHASE 1: HAPUS DUPLIKAT LEAVE_REQUESTS ===
        echo "━━━ PHASE 1: CLEANUP DUPLIKAT ━━━\n\n";
        $decrementTypeIds = LeaveType::where('balance_type', 'decrement')->pluck('id');

        // Snapshot ALL days SEBELUM cleanup (termasuk duplikat)
        $snapshotBefore = $this->snapshotDays($decrementTypeIds);
        echo "Snapshot sebelum cleanup: " . count($snapshotBefore) . " employee+type tercatat.\n";

        $this->cleanupDuplicateRequests();

        // Snapshot SESUDAH cleanup (hanya unique)
        $snapshotAfter = $this->snapshotDays($decrementTypeIds);
        echo "Snapshot setelah cleanup: " . count($snapshotAfter) . " employee+type tercatat.\n";

        // === PHASE 2: RECALCULATE EMPLOYEE_LEAVES ===
        echo "\n━━━ PHASE 2: RESET SALDO ━━━\n\n";
        $this->recalculateBalances($snapshotBefore, $snapshotAfter);

        // === PHASE 3: IMPORT EXCEL (dengan DB-level duplicate check) ===
        echo "\n━━━ PHASE 3: IMPORT EXCEL ━━━\n\n";

        $path = $this->findFile();
        if (! $path) { echo "ERROR: File '{$this->filename}' tidak ditemukan!\n"; return; }
        echo "File: {$path}\n";

        $rows = $this->readExcel($path);
        echo "Total baris data: " . count($rows) . "\n\n";
        usort($rows, fn($a, $b) => strcmp($a['start_date'], $b['start_date']));

        foreach ($rows as $i => $row) {
            $nip = $row['nip'];
            $tipeCuti = $row['tipe_cuti'];
            $startDate = $row['start_date'];
            $endDate = $row['end_date'];
            $durasi = (int) $row['durasi'];
            $alasan = $row['alasan'];
            $status = $row['status'];

            if (empty($nip) || empty($tipeCuti) || empty($startDate)) { $this->skippedEmpty++; continue; }
            if ($status === 'Dibatalkan') { $this->skippedCancelled++; continue; }
            if (! isset($this->employeeMap[$nip])) {
                $this->employeesNotFound++;
                if ($this->employeesNotFound <= 10) echo "  WARN: NIP {$nip} tidak ditemukan\n";
                continue;
            }

            $employeeId = $this->employeeMap[$nip];
            $leaveTypeCode = $this->tipeCutiMapping[$tipeCuti] ?? null;
            if (! $leaveTypeCode || ! isset($this->leaveTypeMap[$leaveTypeCode])) {
                echo "  WARN: Tipe cuti '{$tipeCuti}' tidak dikenal (NIP: {$nip})\n";
                continue;
            }
            $leaveTypeId = $this->leaveTypeMap[$leaveTypeCode]['id'];

            // CEK DUPLIKAT via DB query langsung — lebih reliable dari in-memory cache
            $exists = DB::table('leave_requests')
                ->where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('leave_period_id', $this->periodId)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->exists();

            if ($exists) { $this->skippedDuplicates++; continue; }

            DB::transaction(function () use ($employeeId, $leaveTypeId, $startDate, $endDate, $durasi, $alasan) {
                LeaveRequest::create([
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
            });

            if (($i + 1) % 100 === 0) echo "  Diproses " . ($i + 1) . " baris...\n";
        }

        echo "\n============================================\n";
        echo "  HASIL AKHIR\n";
        echo "============================================\n";
        echo "PHASE 1 — Duplikat leave_requests dihapus : {$this->deletedDuplicates}\n";
        echo "PHASE 2 — Saldo employee_leaves diperbaiki: {$this->recalculatedBalances}\n";
        echo "PHASE 3 — Leave requests baru dibuat       : {$this->createdRequests}\n";
        echo "         Duplikat (dari Excel) diskip      : {$this->skippedDuplicates}\n";
        echo "         Dibatalkan diskip                 : {$this->skippedCancelled}\n";
        echo "         NIP tidak ditemukan               : {$this->employeesNotFound}\n";
        echo "         Data kosong diskip                : {$this->skippedEmpty}\n";

        // Verifikasi akhir
        $totalLR = DB::table('leave_requests')->where('leave_period_id', $this->periodId)->count();
        $dupGroups = DB::table('leave_requests')
            ->select(DB::raw('COUNT(*) as cnt'))
            ->where('leave_period_id', $this->periodId)
            ->groupBy('employee_id', 'leave_type_id', 'start_date', 'end_date')
            ->having('cnt', '>', 1)
            ->count();
        echo "\nVERIFIKASI:\n";
        echo "  Total leave_requests period 2 : {$totalLR}\n";
        echo "  Sisa grup duplikat            : {$dupGroups}\n";
        echo "  Status: " . ($dupGroups === 0 ? "✓ BERSIH, tidak ada duplikat" : "✗ Masih ada {$dupGroups} grup duplikat") . "\n";
        echo "\n=== SEEDER COMPLETED ===\n";
    }

    // ================================================================
    // PHASE 1
    // ================================================================

    protected function snapshotDays($typeIds): array
    {
        $result = [];
        foreach ($typeIds as $typeId) {
            $rows = DB::table('leave_requests')
                ->select('employee_id', DB::raw('SUM(days_requested) as total_days'))
                ->where('leave_period_id', $this->periodId)
                ->where('leave_type_id', $typeId)
                ->where('status', 'approved')
                ->groupBy('employee_id')
                ->get();
            foreach ($rows as $r) {
                $result["{$r->employee_id}:{$typeId}"] = (int) $r->total_days;
            }
        }
        return $result;
    }

    protected function cleanupDuplicateRequests(): void
    {
        $groups = DB::table('leave_requests')
            ->select('employee_id', 'leave_type_id', 'start_date', 'end_date',
                DB::raw('COUNT(*) as total'), DB::raw('MIN(id) as keep_id'))
            ->where('leave_period_id', $this->periodId)
            ->groupBy('employee_id', 'leave_type_id', 'start_date', 'end_date')
            ->having('total', '>', 1)
            ->get();

        $groupCount = count($groups);
        if ($groupCount === 0) { echo "Tidak ada duplikat ditemukan.\n"; return; }
        echo "Ditemukan {$groupCount} grup duplikat.\n";

        $totalDeleted = 0;
        foreach ($groups as $g) {
            $deleted = DB::table('leave_requests')
                ->where('employee_id', $g->employee_id)
                ->where('leave_type_id', $g->leave_type_id)
                ->where('leave_period_id', $this->periodId)
                ->where('start_date', $g->start_date)
                ->where('end_date', $g->end_date)
                ->where('id', '!=', $g->keep_id)
                ->delete();
            $totalDeleted += $deleted;
        }
        $this->deletedDuplicates = $totalDeleted;
        echo "Total record duplikat dihapus: {$totalDeleted}\n";
    }

    // ================================================================
    // PHASE 2
    // ================================================================

    /**
     * Formula: over_decrement = sum_before_cleanup - sum_after_cleanup
     *          new_amount = current_amount + over_decrement
     */
    protected function recalculateBalances(array $before, array $after): void
    {
        $recalculated = 0;
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($allKeys as $key) {
            $overDec = ($before[$key] ?? 0) - ($after[$key] ?? 0);
            if ($overDec <= 0) continue;

            [$empId, $typeId] = explode(':', $key);
            $el = EmployeeLeave::where('employee_id', $empId)
                ->where('leave_type_id', $typeId)
                ->where('leave_period_id', $this->periodId)
                ->first();

            if ($el) {
                $el->updateQuietly(['amount' => (int) $el->amount + $overDec]);
                $recalculated++;
            }
        }
        $this->recalculatedBalances = $recalculated;
        echo "Saldo employee_leaves diperbaiki: {$recalculated} record\n";
    }

    // ================================================================
    // PHASE 3: HELPERS
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
            if ($emp->nip) $this->employeeMap[(string) $emp->nip] = $emp->id;
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
