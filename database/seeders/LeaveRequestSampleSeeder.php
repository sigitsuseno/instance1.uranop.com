<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveRequestSampleSeeder extends Seeder
{
    /**
     * Seed leave_requests, employee_leaves (deduction), dan update sch_employee_shift_rosters
     * dari semua file sample_data/cuti_*.csv (januari, februari, maret, april, dst)
     *
     * Format CSV: NIP;NAMA LENGKAP;tanggal1;tanggal2;... (delimiter ;)
     * Isi sel: kode cuti (CT, SKT, CM, dll) — kosong jika tidak cuti
     */
    public function run(): void
    {
        $sampleDir = base_path('sample_data');
        $files = glob($sampleDir . '/cuti_*.csv');

        if (empty($files)) {
            $this->command?->warn("Tidak ada file cuti_*.csv di {$sampleDir}");
            return;
        }

        $this->command?->info("Ditemukan " . count($files) . " file: " . implode(', ', array_map('basename', $files)));

        // Cache lookups
        $leaveTypes = LeaveType::all()->keyBy('code');

        $totalStats = [
            'leave_requests' => 0,
            'employee_leaves' => 0,
            'roster_updated' => 0,
            'skipped' => 0,
        ];

        // Kumpulkan semua tanggal dari semua file untuk tentukan periode
        $allDates = [];

        DB::beginTransaction();
        try {
            foreach ($files as $csvPath) {
                $fileName = basename($csvPath);
                $this->command?->line("  Memproses {$fileName}...");

                $stats = $this->processFile($csvPath, $leaveTypes, $allDates);

                $this->command?->line(sprintf(
                    "    ✓ requests:%d | deductions:%d | roster:%d | skipped:%d",
                    $stats['leave_requests'],
                    $stats['employee_leaves'],
                    $stats['roster_updated'],
                    $stats['skipped']
                ));

                foreach ($stats as $key => $val) {
                    $totalStats[$key] += $val;
                }
            }

            DB::commit();

            $this->command?->info(sprintf(
                "\n✅ Total seed selesai:\n  - Leave requests: %d\n  - Employee leaves (deduction): %d\n  - Roster updated: %d\n  - Skipped (nip not found): %d",
                $totalStats['leave_requests'],
                $totalStats['employee_leaves'],
                $totalStats['roster_updated'],
                $totalStats['skipped']
            ));

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command?->error('Gagal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Proses satu file CSV.
     */
    private function processFile(string $csvPath, $leaveTypes, array &$allDates): array
    {
        $rows = array_map(fn($line) => str_getcsv($line, ';'), file($csvPath));
        $header = $rows[0];
        array_shift($rows);

        $dateColumns = array_slice($header, 2);

        // Kumpulkan semua NIP dari file ini
        $nips = [];
        foreach ($rows as $row) {
            if (!empty($row[0]) && is_numeric($row[0])) {
                $nips[] = trim($row[0]);
            }
        }

        $employees = Employee::whereIn('nip', array_unique($nips))->pluck('id', 'nip');

        $stats = [
            'leave_requests' => 0,
            'employee_leaves' => 0,
            'roster_updated' => 0,
            'skipped' => 0,
        ];

        // Akumulator decrement: key = "empId:typeId:periodId" => amount
        $decrementAccumulator = [];

        foreach ($rows as $row) {
            if (empty($row[0]) || !is_numeric($row[0])) {
                continue;
            }

            $nip = trim($row[0]);
            $employeeId = $employees[$nip] ?? null;
            if (!$employeeId) {
                $stats['skipped']++;
                continue;
            }

            foreach ($dateColumns as $colIndex => $dateStr) {
                $code = trim($row[$colIndex + 2] ?? '');

                if (!isset($leaveTypes[$code])) {
                    continue;
                }

                $leaveType = $leaveTypes[$code];

                // Parse tanggal: d/m/yy → Y-m-d
                $parts = explode('/', $dateStr);
                if (count($parts) !== 3) continue;
                $date = Carbon::createFromDate(
                    2000 + (int)$parts[2],
                    (int)$parts[1],
                    (int)$parts[0]
                )->format('Y-m-d');

                $allDates[] = $date;

                // Tentukan periode untuk tanggal ini
                $period = $this->findPeriod($date);

                // === 1. leave_request (tetap per hari) ===
                $leaveRequest = LeaveRequest::updateOrCreate(
                    [
                        'employee_id' => $employeeId,
                        'leave_type_id' => $leaveType->id,
                        'start_date' => $date,
                    ],
                    [
                        'leave_period_id' => $period?->id,
                        'end_date' => $date,
                        'days_requested' => 1,
                        'reason' => "Cuti {$leaveType->name} (import sample)",
                        'status' => 'approved',
                        'approved_at' => now(),
                    ]
                );

                if ($leaveRequest->wasRecentlyCreated) {
                    $stats['leave_requests']++;
                }

                // === 2. Akumulasi decrement (bukan insert langsung) ===
                if ($leaveType->balance_type === 'decrement') {
                    $key = "{$employeeId}:{$leaveType->id}:{$period->id}";
                    if (!isset($decrementAccumulator[$key])) {
                        $decrementAccumulator[$key] = [
                            'employee_id' => $employeeId,
                            'leave_type_id' => $leaveType->id,
                            'leave_period_id' => $period->id,
                            'amount' => 0,
                            'leave_type_name' => $leaveType->name,
                        ];
                    }
                    $decrementAccumulator[$key]['amount']++;
                }

                // === 3. Update sch_employee_shift_rosters ===
                $isLeave = in_array($leaveType->category, ['leave', 'sick', 'special']) ? 1 : 0;
                $isPermit = ($leaveType->category === 'permit') ? 1 : 0;

                $updated = DB::table('sch_employee_shift_rosters')
                    ->where('employee_id', $employeeId)
                    ->where('date', $date)
                    ->update([
                        'external_code' => $code,
                        'is_leave' => $isLeave,
                        'is_permit' => $isPermit,
                        'leave_id' => $leaveRequest->id,
                        'updated_at' => now(),
                    ]);

                if ($updated > 0) {
                    $stats['roster_updated']++;
                }
            }
        }

        // === 4. Insert aggregated decrement (1 record per employee+type+period) ===
        foreach ($decrementAccumulator as $key => $acc) {
            $incrementRecord = EmployeeLeave::where('employee_id', $acc['employee_id'])
                ->where('leave_type_id', $acc['leave_type_id'])
                ->where('leave_period_id', $acc['leave_period_id'])
                ->where('transaction_type', 'increment')
                ->first();

            // Update decrement record — jika sudah ada, tambahkan amount
            $decrementRecord = EmployeeLeave::where('employee_id', $acc['employee_id'])
                ->where('leave_type_id', $acc['leave_type_id'])
                ->where('leave_period_id', $acc['leave_period_id'])
                ->where('transaction_type', 'decrement')
                ->first();

            if ($decrementRecord) {
                // Update existing — timpa (overwrite) amount agar tidak double
                $decrementRecord->update([
                    'amount' => $acc['amount'],
                    'description' => 'Akumulasi potongan ' . $acc['leave_type_name'],
                    'reference_id' => null,
                ]);
            } else {
                // Buat baru
                EmployeeLeave::create([
                    'employee_id' => $acc['employee_id'],
                    'leave_type_id' => $acc['leave_type_id'],
                    'leave_period_id' => $acc['leave_period_id'],
                    'transaction_type' => 'decrement',
                    'amount' => $acc['amount'],
                    'description' => 'Akumulasi potongan ' . $acc['leave_type_name'],
                    'reference_id' => null,
                ]);
                $stats['employee_leaves']++;
            }
        }

        return $stats;
    }

    /**
     * Cari periode cuti yang mencakup tanggal tertentu.
     */
    private function findPeriod(string $date): ?LeavePeriod
    {
        static $cache = [];

        if (isset($cache[$date])) {
            return $cache[$date];
        }

        $period = LeavePeriod::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();

        if (!$period) {
            $period = LeavePeriod::where('status', 'active')->first();
        }

        return $cache[$date] = $period;
    }
}
