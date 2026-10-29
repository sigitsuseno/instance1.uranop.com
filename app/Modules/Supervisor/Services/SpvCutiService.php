<?php

namespace App\Modules\Supervisor\Services;

use App\Modules\Leave\Models\EmployeeLeave;
use App\Modules\Supervisor\Models\SpvLedgerCuti;
use Illuminate\Support\Facades\DB;

class SpvCutiService
{
    /**
     * Salin ledger cuti (employee_leaves) milik karyawan terpilih pada periode
     * tertentu ke spv_ledger_cuti. Kombinasi unik yang sudah ada dilewati agar
     * operasi idempoten.
     *
     * @param  array<int, int>  $employeeIds
     * @return array{created: int, skipped: int}
     */
    public function copyLedgerFromEmployeeLeaves(array $employeeIds, int $leavePeriodId, ?int $userId = null): array
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($employeeIds, $leavePeriodId, $userId, &$created, &$skipped) {
            EmployeeLeave::query()
                ->whereIn('employee_id', $employeeIds)
                ->where('leave_period_id', $leavePeriodId)
                ->orderBy('id')
                ->each(function (EmployeeLeave $source) use ($userId, &$created, &$skipped) {
                    $record = SpvLedgerCuti::firstOrCreate(
                        [
                            'employee_id'      => $source->employee_id,
                            'leave_type_id'    => $source->leave_type_id,
                            'leave_period_id'  => $source->leave_period_id,
                            'reference_id'     => $source->reference_id,
                            'transaction_type' => $source->transaction_type,
                        ],
                        [
                            'amount'      => $source->amount,
                            'description' => $source->description,
                            'created_by'  => $userId,
                            'updated_by'  => $userId,
                        ]
                    );

                    $record->wasRecentlyCreated ? $created++ : $skipped++;
                });
        });

        return ['created' => $created, 'skipped' => $skipped];
    }
}
