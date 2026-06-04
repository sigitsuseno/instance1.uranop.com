<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\RawLog;
use App\Modules\Attendance\Services\AttendanceCalculatorService;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\WorkingCalendar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{


    /**
     * Lengkapi attendance — update check_in/check_out manual untuk record yang incomplete.
     *
     * @param int   $prepareId
     * @param array $data  ['check_in' => 'H:i', 'check_out' => 'H:i', 'status' => '...', 'notes' => '...']
     * @return AttendancePrepare
     */
    public function lengkapi(int $prepareId, array $data): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);

        if ($prepare->is_locked) {
            throw new \RuntimeException('Data sudah terkunci.');
        }

        $update = [];

        if (array_key_exists('check_in', $data)) {
            $update['check_in'] = $data['check_in']
                ? Carbon::parse($prepare->date->toDateString() . ' ' . $data['check_in'])
                : null;
        }

        if (array_key_exists('check_out', $data)) {
            $update['check_out'] = $data['check_out']
                ? Carbon::parse($prepare->date->toDateString() . ' ' . $data['check_out'])
                : null;
        }

        if (array_key_exists('status', $data)) {
            $update['status'] = $data['status'];
        }

        if (array_key_exists('notes', $data)) {
            $update['notes'] = $data['notes'];
        }

        if (array_key_exists('overtime', $data)) {
            $update['overtime'] = (int) $data['overtime'];
        }

        $prepare->update($update);

        // Update review_status berdasarkan kelengkapan
        $prepare->refresh();
        $prepare->update([
            'review_status' => $prepare->hasCompleteTimes()
                ? AttendancePrepare::REVIEW_LENGKAP
                : AttendancePrepare::REVIEW_CEK,
        ]);

        return $prepare->fresh();
    }

    /**
     * Bulk lengkapi attendance.
     */
    public function bulkLengkapi(array $records, int $userId): array
    {
        $updated = 0;
        $errors  = [];

        DB::beginTransaction();
        try {
            foreach ($records as $item) {
                $prepare = AttendancePrepare::find($item['id'] ?? 0);
                if (!$prepare || $prepare->is_locked) {
                    continue;
                }

                $updateData = [];

                if (isset($item['check_in'])) {
                    $updateData['check_in'] = $item['check_in']
                        ? Carbon::parse($prepare->date->toDateString() . ' ' . $item['check_in'])
                        : null;
                }

                if (isset($item['check_out'])) {
                    $updateData['check_out'] = $item['check_out']
                        ? Carbon::parse($prepare->date->toDateString() . ' ' . $item['check_out'])
                        : null;
                }

                if (isset($item['status'])) {
                    $updateData['status'] = $item['status'];
                }

                if (!empty($updateData)) {
                    $updateData['review_status'] = AttendancePrepare::REVIEW_LENGKAP;
                }

                $note = $item['notes'] ?? '';
                if ($note) {
                    $updateData['notes'] = ($prepare->notes ? $prepare->notes . "\n" : '')
                        . "Dilengkapi oleh: #{$userId}";
                }

                if (!empty($updateData)) {
                    $prepare->update($updateData);
                    $updated++;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    /**
     * Hitung lembur (overtime multiplier) untuk satu record.
     * Recalculate menggunakan AttendanceCalculatorService.
     */
    public function hitungLembur(AttendancePrepare $prepare, AttendanceCalculatorService $calculator): AttendancePrepare
    {
        $shift = null;

        // Coba dapatkan shift dari roster
        $roster = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $prepare->employee_id)
            ->whereDate('date', $prepare->date)
            ->first();

        if ($roster) {
            $shift = $roster->shift;
        }

        $isHoliday = \App\Modules\Schedule\Models\Holiday::where('date', $prepare->date->toDateString())->exists();
        $isSunday  = $prepare->date->isSunday();

        $calc = $calculator->calculate($prepare, $shift, $isHoliday, $isSunday);

        $prepare->update([
            'late_minutes'  => $calc['late_minutes'],
            'lm'            => $calc['lm'],
            'lm_count'      => $calc['lm_count'],
            'overtime'      => $calc['overtime'],
            'overtime_count' => $calc['overtime_count'],
        ]);

        return $prepare->fresh();
    }

    /**
     * Bulk hitung lembur untuk rentang tanggal.
     */
    public function bulkHitungLembur(string $startDate, string $endDate, AttendanceCalculatorService $calculator): array
    {
        $prepares = AttendancePrepare::whereBetween('date', [$startDate, $endDate])
            ->where('is_locked', false)
            ->where(function ($q) {
                $q->where('overtime', '>', 0)
                  ->orWhere('lm', '>', 0);
            })
            ->get();

        $updated = 0;
        foreach ($prepares as $prepare) {
            $this->hitungLembur($prepare, $calculator);
            $updated++;
        }

        return ['updated' => $updated];
    }

    /**
     * Lock attendance record — hanya superadmin & hrmanager.
     */
    public function lock(int $prepareId, int $userId): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);
        $prepare->lock($userId);

        return $prepare->fresh();
    }

    /**
     * Unlock attendance record — hanya superadmin & hrmanager.
     */
    public function unlock(int $prepareId): AttendancePrepare
    {
        $prepare = AttendancePrepare::findOrFail($prepareId);
        $prepare->unlock();

        return $prepare->fresh();
    }

    /**
     * Bulk lock/unlock attendance records.
     */
    public function bulkLock(array $ids, int $userId, bool $lock = true): array
    {
        $updated = 0;
        foreach ($ids as $id) {
            $prepare = AttendancePrepare::find($id);
            if (!$prepare) continue;

            if ($lock) {
                $prepare->lock($userId);
            } else {
                $prepare->unlock();
            }
            $updated++;
        }

        return ['updated' => $updated];
    }



    /**
     * Dapatkan jadwal kerja karyawan untuk tanggal tertentu.
     */
    protected function getEmployeeSchedule(Employee $employee, Carbon $date): ?array
    {
        // 1. Cek roster shift
        try {
            $roster = \App\Modules\Schedule\Models\EmployeeShiftRoster::where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($roster && $roster->shift) {
                return [
                    'shift_id' => $roster->shift_id,
                    'schedule_in' => $roster->shift->start_time,
                    'schedule_out' => $roster->shift->end_time,
                ];
            }
        } catch (\Throwable) {
            // Roster mungkin belum ada
        }

        // 2. Cek work pattern
        // Gunakan Employee Group untuk mapping ke work pattern
        try {
            $workPatternGroup = $employee->employeeGroups()
                ->whereHas('groupMaster', fn($q) => $q->where('code', 'work_pattern'))
                ->first();

            if ($workPatternGroup) {
                $workPattern = \App\Modules\Schedule\Models\WorkPattern::where('code', $workPatternGroup->reference_code)
                    ->first();

                if ($workPattern) {
                    $dayName = strtolower($date->englishDayOfWeek);
                    $detail = $workPattern->details()
                        ->where('day', $dayName)
                        ->first();

                    if ($detail) {
                        return [
                            'work_pattern_id' => $workPattern->id,
                            'schedule_in' => $detail->start_time,
                            'schedule_out' => $detail->end_time,
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Work pattern mapping mungkin belum ada
        }

        // 3. Default: gunakan config
        return [
            'schedule_in' => '08:00',
            'schedule_out' => '17:00',
        ];
    }

    /**
     * Import data absensi dari RawLog ke AttendanceLog.
     * Memetakan PIN ke employee_id.
     */
    public function matchRawLogsToEmployees(?string $batch = null): array
    {
        $result = ['matched' => 0, 'errors' => []];

        $query = RawLog::whereNull('employee_name')->orWhere('employee_name', '');
        if ($batch) {
            $query->where('import_batch', $batch);
        }

        $logs = $query->get();

        foreach ($logs as $log) {
            try {
                $employee = Employee::where('nip', $log->employee_code)
                    ->orWhere('employee_code', $log->employee_code)
                    ->first();

                if ($employee) {
                    $log->update(['employee_name' => $employee->full_name]);
                    $result['matched']++;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = "Gagal match {$log->employee_code}: " . $e->getMessage();
            }
        }

        return $result;
    }
}
