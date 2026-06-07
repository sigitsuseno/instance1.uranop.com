<?php

namespace App\Modules\Attendance\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Employee\Models\Employee;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RawLogController extends Controller
{
    /**
     * GET /api/v1/attendance/logs/cek
     * Fetch raw logs grouped by employee for a specific date, highlighting in-range scans.
     */
    public function cekLog(Request $request): JsonResponse
    {
        $dateString = $request->input('date', Carbon::today()->toDateString());
        $date = Carbon::parse($dateString);

        // Fetch all active employees
        $employees = Employee::where('is_active', true)->get();

        $employeeCodes = $employees->pluck('employee_code')->filter()->toArray();
        $employeeIds = $employees->pluck('id')->toArray();

        // Fetch rosters for the selected date
        $rosters = EmployeeShiftRoster::where('date', $dateString)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        // Calculate global min and max fetch time to minimize DB query size
        // Earliest possible: $date 00:00:00 (for SHIFT)
        // Latest possible: $date + 1 day 06:29:59 (for NON-SHIFT)
        $fetchStart = $date->copy()->startOfDay();
        $fetchEnd = $date->copy()->addDay()->setTime(6, 29, 59);

        // Fetch all raw logs in the global range for these employees
        $allLogs = RawLog::whereIn('employee_code', $employeeCodes)
            ->whereBetween('scan_datetime', [$fetchStart->toDateTimeString(), $fetchEnd->toDateTimeString()])
            ->orderBy('scan_datetime', 'asc')
            ->get()
            ->groupBy('employee_code');

        $result = [];

        foreach ($employees as $employee) {
            if (!$employee->employee_code) {
                continue;
            }

            $employeeLogs = $allLogs->get($employee->employee_code, collect());

            $roster = $rosters->get($employee->id);
            $workPatternType = $roster ? $roster->work_pattern_type : 'NON-SHIFT'; 

            $isShift = strtoupper($workPatternType) === 'SHIFT';

            $scans = [];

            if ($isShift) {
                // Range for SHIFT: 05:30 to 23:00 on the selected date
                $rangeStart = $date->copy()->setTime(5, 30, 0);
                $rangeEnd = $date->copy()->setTime(23, 0, 0);

                // For SHIFT, show all logs on that calendar day (00:00 to 23:59)
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $relevantLogs = $employeeLogs->filter(function ($log) use ($dayStart, $dayEnd) {
                    $scanTime = Carbon::parse($log->scan_datetime);
                    return $scanTime->between($dayStart, $dayEnd);
                });

                foreach ($relevantLogs as $log) {
                    $scanTime = Carbon::parse($log->scan_datetime);
                    $inRange = $scanTime->between($rangeStart, $rangeEnd);
                    $scans[] = [
                        'id' => $log->id,
                        'time' => $scanTime->format('H:i:s'),
                        'datetime' => $log->scan_datetime,
                        'in_range' => $inRange,
                    ];
                }
            } else {
                // Range for NON-SHIFT: 06:30 today to 06:29 tomorrow
                $rangeStart = $date->copy()->setTime(6, 30, 0);
                $rangeEnd = $date->copy()->addDay()->setTime(6, 29, 59);

                $relevantLogs = $employeeLogs->filter(function ($log) use ($rangeStart, $rangeEnd) {
                    $scanTime = Carbon::parse($log->scan_datetime);
                    return $scanTime->between($rangeStart, $rangeEnd);
                });

                foreach ($relevantLogs as $log) {
                    $scanTime = Carbon::parse($log->scan_datetime);
                    // Since we filtered exactly by range, all of them are in_range
                    $scans[] = [
                        'id' => $log->id,
                        'time' => $scanTime->format('d/m H:i'),
                        'datetime' => $log->scan_datetime,
                        'in_range' => true,
                    ];
                }
            }

            $result[] = [
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'work_pattern' => $workPatternType,
                'is_shift' => $isShift,
                'scans' => $scans,
            ];
        }

        return response()->json([
            'date' => $dateString,
            'data' => $result,
        ]);
    }
}
