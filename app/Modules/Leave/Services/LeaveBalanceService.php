<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\EmployeeLeave;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveBalanceService
{
    /**
     * Membagikan kuota cuti untuk karyawan yang tepat berusia 1 tahun masa kerja.
     * Dijalankan via scheduler setiap hari.
     */
    public function distributeFirstYearQuota($date = null)
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $targetJoinDate = $date->copy()->subYear()->format('Y-m-d');

        // Cari karyawan yang genap 1 tahun hari ini
        $employees = DB::table('employees')
            ->where('is_active', 1)
            ->whereDate('join_date', $targetJoinDate)
            ->get();

        if ($employees->isEmpty()) {
            return 0; // Tidak ada karyawan
        }

        // Cari periode aktif
        $activePeriod = LeavePeriod::where('status', 'active')->first();
        if (!$activePeriod) {
            return 0; // Tidak ada periode aktif
        }

        // Cari kebijakan yang mensyaratkan 1 tahun masa kerja
        $policies = LeavePolicy::where('requires_one_year_service', true)
                        ->where('entitlement_days', '>', 0)
                        ->get();

        if ($policies->isEmpty()) {
            return 0;
        }

        $count = 0;
        DB::beginTransaction();
        try {
            foreach ($employees as $emp) {
                foreach ($policies as $policy) {
                    // Mencegah double mutasi
                    $exists = EmployeeLeave::where('employee_id', $emp->id)
                        ->where('leave_type_id', $policy->leave_type_id)
                        ->where('leave_period_id', $activePeriod->id)
                        ->where('description', 'like', '%1 Tahun Masa Kerja%')
                        ->exists();

                    if (!$exists) {
                        EmployeeLeave::create([
                            'employee_id' => $emp->id,
                            'leave_type_id' => $policy->leave_type_id,
                            'leave_period_id' => $activePeriod->id,
                            'transaction_type' => 'addition',
                            'amount' => $policy->entitlement_days,
                            'description' => 'Kuota 1 Tahun Masa Kerja',
                        ]);
                    }
                }
                $count++;
            }
            DB::commit();
            return $count;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
