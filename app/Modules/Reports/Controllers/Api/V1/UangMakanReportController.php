<?php

namespace App\Modules\Reports\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Attendance\Models\AttendancePrepare;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UangMakanReportController extends Controller
{
    public function index(Request $request)
    {
        $periodId = $request->input('period_id');
        $search = $request->input('search');

        if (!$periodId) {
            return response()->json([
                'success' => false,
                'message' => 'Pay Period ID is required.'
            ], 400);
        }

        $period = PayPeriod::find($periodId);
        if (!$period) {
            return response()->json([
                'success' => false,
                'message' => 'Pay Period not found.'
            ], 404);
        }

        $startDate = $period->start_date;
        $endDate = $period->end_date;

        // Retrieve employees with shift roster in this period, filtered by Uang Makan group
        $employeesQuery = EmployeeShiftRoster::whereBetween('date', [$startDate, $endDate])
            ->whereHas('employee', function ($q) use ($search) {
                if ($search) {
                    $q->where(function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%")
                             ->orWhere('employee_code', 'like', "%{$search}%");
                    });
                }
            })
            ->whereHas('employee.groups.master', function ($q) {
                $q->where('group_label', 'Uang Makan');
            })
            ->with([
                'employee:id,employee_code,name,department_id,position_id',
                'employee.department:id,name',
                'employee.position:id,name',
                'employee.groups' => function ($q) {
                    $q->whereHas('master', function ($sq) {
                        $sq->where('group_label', 'Uang Makan');
                    })->with('master');
                }
            ])
            ->select('employee_id')
            ->distinct()
            ->get()
            ->pluck('employee');

        // Fetch attendance prepares for these employees within the period
        $employeeIds = $employeesQuery->pluck('id')->toArray();
        $attPrepares = AttendancePrepare::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('employee_id');

        // Prepare dates array
        $dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $dates[] = [
                'date' => $current->toDateString(),
                'day_name' => $current->translatedFormat('l'),
                'day_short' => $current->translatedFormat('D'),
                'serial' => $current->format('Ymd'),
            ];
            $current->addDay();
        }

        $data = [];
        $resumeGrouped = [];

        foreach ($employeesQuery as $i => $employee) {
            $logs = $attPrepares->get($employee->id)?->keyBy(fn($l) => Carbon::parse($l->date)->toDateString()) ?? collect();
            
            // Get the group for rate calculation
            $groupMaster = $employee->groups->first()?->master;
            $groupName = strtoupper($groupMaster?->name ?? '');

            $rateWeekday = 15000;

            if (str_contains($groupName, 'KABAG')) {
                $rateSabtuDua = 55000;
                $rateSabtuFull = 110000;
                $rateMingguSetengah = 110000;
                $rateMingguFull = 220000;
            } elseif (str_contains($groupName, 'KEPALA SHIFT') || str_contains($groupName, 'KASHIFT')) {
                $rateSabtuDua = 52522;
                $rateSabtuFull = 105000;
                $rateMingguSetengah = 105000;
                $rateMingguFull = 210000;
            } elseif (str_contains($groupName, 'ALL IN') || str_contains($groupName, 'ALL-IN')) {
                $rateSabtuDua = 50000;
                $rateSabtuFull = 100000;
                $rateMingguSetengah = 100000;
                $rateMingguFull = 200000;
            } else {
                $rateSabtuDua = 0;
                $rateSabtuFull = 0;
                $rateMingguSetengah = 0;
                $rateMingguFull = 0;
            }

            $count_um_weekday = 0;
            $count_sabtu_dua = 0;
            $count_sabtu_full = 0;
            $count_minggu_setengah = 0;
            $count_minggu_full = 0;

            $nominal_um_weekday = 0;
            $nominal_sabtu = 0;
            $nominal_minggu = 0;

            $days = [];

            foreach ($dates as $dateObj) {
                $dateStr = $dateObj['date'];
                $log = $logs->get($dateStr);
                
                // Assuming 'calculated_overtime' equivalent in new schema, might need check if it exists or use 'overtime_minutes' / 60
                // For now, let's assume overtime is in 'overtime' field as minutes
                $overtimeMinutes = $log ? ((int)$log->overtime ?? 0) : 0;
                $lembur = round($overtimeMinutes / 60, 2);

                $statusStr = $log ? $log->status : '-';
                $isHoliday = ($statusStr === 'libur');

                $dayOfWeek = Carbon::parse($dateStr)->dayOfWeek; // 0 = Sunday, 6 = Saturday

                if ($lembur > 0) {
                    if ($dayOfWeek == 0 || $isHoliday) {
                        // Minggu / Holiday
                        if ($lembur >= 8) {
                            $count_minggu_full++;
                            $nominal_minggu += $rateMingguFull;
                        } elseif ($lembur >= 4) {
                            $count_minggu_setengah++;
                            $nominal_minggu += $rateMingguSetengah;
                        }
                    } elseif ($dayOfWeek == 6) {
                        // Sabtu
                        if ($lembur >= 4) {
                            $count_sabtu_full++;
                            $nominal_sabtu += $rateSabtuFull;
                        } elseif ($lembur >= 2) {
                            $count_sabtu_dua++;
                            $nominal_sabtu += $rateSabtuDua;
                        }
                    } else {
                        // Senin - Jumat
                        if ($lembur >= 3) {
                            $count_um_weekday++;
                            $nominal_um_weekday += $rateWeekday;
                        }
                    }
                }

                $mappedStatus = $this->mapStatus($statusStr);

                $days[$dateStr] = [
                    'status' => $mappedStatus,
                    'lembur' => $lembur > 0 ? $lembur : '-',
                ];
            }

            $total = $nominal_um_weekday + $nominal_sabtu + $nominal_minggu;

            $item = [
                'no' => $i + 1,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'title' => $groupName,
                'count_um' => $count_um_weekday,
                'count_sabtu_dua' => $count_sabtu_dua,
                'count_sabtu_full' => $count_sabtu_full,
                'count_minggu_setengah' => $count_minggu_setengah,
                'count_minggu_full' => $count_minggu_full,
                'nominal_um' => $nominal_um_weekday,
                'nominal_sabtu' => $nominal_sabtu,
                'nominal_minggu' => $nominal_minggu,
                'nominal_insentif' => 0,
                'nominal_pblt' => 0,
                'nominal_revisi' => 0,
                'total' => $total,
                'days' => $days,
            ];

            $data[] = $item;

            // Resume grouping logic for ALL IN
            if (str_contains($groupName, 'ALL IN') || str_contains($groupName, 'ALL-IN')) {
                $pos = $employee->position?->name ?? 'Tanpa Posisi';
                if (!isset($resumeGrouped[$pos])) {
                    $resumeGrouped[$pos] = [
                        'bagian' => $pos,
                        'uang_makan' => 0,
                        'lembur_sabtu' => 0,
                        'lembur_minggu' => 0,
                        'insentif' => 0,
                        'pblt' => 0,
                        'revisi' => 0,
                        'total' => 0,
                    ];
                }

                $resumeGrouped[$pos]['uang_makan'] += $nominal_um_weekday;
                $resumeGrouped[$pos]['lembur_sabtu'] += $nominal_sabtu;
                $resumeGrouped[$pos]['lembur_minggu'] += $nominal_minggu;
                $resumeGrouped[$pos]['total'] += $total;
            }
        }

        $resumeData = array_values($resumeGrouped);

        return response()->json([
            'success' => true,
            'data' => $data,
            'resumeData' => $resumeData,
            'dates' => $dates,
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'period_id' => $periodId,
            ]
        ]);
    }

    private function mapStatus($statusStr)
    {
        return match (strtolower($statusStr)) {
            'hadir', 'terlambat' => 'H',
            'cuti' => 'CUTI',
            'izin' => 'I',
            'absent', 'alpa' => 'A',
            'libur' => 'LIBUR',
            'off' => 'OFF',
            'sakit' => 'SAKIT',
            default => '-'
        };
    }
}
