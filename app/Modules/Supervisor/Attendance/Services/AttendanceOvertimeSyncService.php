<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Attendance\Models\AttendancePrepare;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceOvertimeSyncService
{
    public function sync(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): array {
        $query = AttendanceAutolog::with(['employee', 'employeeShiftRoster'])
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('check_in')
            ->whereHas('employee.groups', function ($q) {
                $q->where('reference_code', 'GRP-ALLIN');
            });

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $autologs = $query->get();

        Log::info('Starting overtime sync for ALLIN employees', [
            'branch_id' => $branchId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'autolog_count' => $autologs->count(),
        ]);

        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($autologs as $autolog) {
                $externalCode = $autolog->employeeShiftRoster?->external_code;

                if (! in_array($externalCode, ['P', 'S'])) {
                    $skipped++;

                    continue;
                }

                $prepare = AttendancePrepare::where('employee_id', $autolog->employee_id)
                    ->where('date', $autolog->date->toDateString())
                    ->first();

                if (! $prepare || ! $prepare->overtime_duration || $prepare->overtime_duration <= 0) {
                    $skipped++;

                    continue;
                }

                $otDuration = min((int) $prepare->overtime_duration, 180);

                if ($externalCode === 'P') {
                    $autolog->update([
                        'check_out' => $autolog->check_out
                            ? $autolog->check_out->copy()->addMinutes($otDuration)
                            : null,
                        'overtime_duration' => $otDuration,
                    ]);
                    $updated++;
                } elseif ($externalCode === 'S') {
                    $autolog->update([
                        'check_in' => $autolog->check_in
                            ? $autolog->check_in->copy()->subMinutes($otDuration)
                            : null,
                        'overtime_duration' => $otDuration,
                    ]);
                    $updated++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Overtime sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        Log::info('Overtime sync completed', [
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
