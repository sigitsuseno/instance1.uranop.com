<?php

namespace App\Modules\Supervisor\Attendance\Services;

use App\Modules\Supervisor\Attendance\Models\SupervisorAttendance as AttendanceAutolog;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditorLogService
{
    /**
     * Generate auditor logs in attendance_autologs table
     * Based on PROSES.md rules
     */
    public function generateLogs($companyId, $startDate, $endDate, $branchId = null, $workPatternId = null)
    {
        $query = EmployeeShiftRoster::with(['shift', 'prepare'])
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($workPatternId) {
            $query->where('work_pattern_id', $workPatternId);
        }

        $rosters = $query->get();

        Log::info('Generating Auditor Logs', [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'work_pattern_id' => $workPatternId,
            'count' => $rosters->count(),
        ]);

        $processed = 0;
        $deleted = 0;

        DB::beginTransaction();
        try {
            foreach ($rosters as $roster) {
                $this->processRosterEntry($roster);
                $processed++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Auditor Log Generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'success' => true,
            'processed' => $processed,
            'message' => "Berhasil menghasilkan $processed data auditor log.",
        ];
    }

    /**
     * Process single roster entry to generate autolog
     */
    protected function processRosterEntry(EmployeeShiftRoster $roster)
    {
        $date = $roster->date;
        $dateStr = $date->toDateString();
        $workPatternType = $roster->work_pattern_type;

        $checkIn = null;
        $checkOut = null;
        $otDuration = 0;
        $lateDuration = 0;

        // 1. Extract leave information for this roster date
        $leaveData = $this->extractLeave($roster->employee_id, $roster->date);

        if ($leaveData['found']) {
            $this->saveAutolog(
                $roster,
                null,
                null,
                0,
                0,
                $leaveData['status'],
                null,
                null,
                $leaveData
            );

            return;
        }

        // Siapkan data prepare dan penanda jadwal
        $prepare = $roster->prepare;
        $actualIn = $prepare?->check_in;
        $actualOut = $prepare?->check_out;
        $actualInCarb = $actualIn ? ($actualIn instanceof Carbon ? $actualIn : Carbon::parse($actualIn)) : null;
        $actualOutCarb = $actualOut ? ($actualOut instanceof Carbon ? $actualOut : Carbon::parse($actualOut)) : null;
        $hasSchedule = (bool) ($roster->shift?->external_code ?? $roster->external_code ?? $roster->shift_code);

        // 2. LONGSHIFT Handling
        if ($workPatternType === 'SHIFT') {
            if (! $prepare) {
                return;
            }

            // Holiday (termasuk Minggu): jika ada actual check-in => lembur 8 jam, jika tidak => holiday
            if ($roster->is_holiday) {
                if ($actualInCarb) {
                    $checkIn = $actualInCarb;
                    $checkOut = (clone $checkIn)->addHours(8);
                    $otDuration = 480; // 8 jam
                    $this->saveAutolog($roster, $checkIn, $checkOut, $otDuration, $lateDuration, null, $actualInCarb, $actualOutCarb);

                    return;
                }
                $this->saveAutolog($roster, null, null, 0, 0, 'holiday', $actualInCarb, $actualOutCarb);

                return;
            }

            // Minggu (non-holiday)
            if ($roster->is_sun) {
                if (! $actualInCarb && $hasSchedule) {
                    $this->saveAutolog($roster, null, null, 0, 0, 'absent', $actualInCarb, $actualOutCarb);

                    return;
                }
                if ($actualInCarb) {
                    $checkIn = $actualInCarb;
                    $checkOut = $actualOutCarb;
                    $otDuration = 120; // 2 jam
                    $this->saveAutolog($roster, $checkIn, $checkOut, $otDuration, $lateDuration, null, $actualInCarb, $actualOutCarb);

                    return;
                }
                // Tidak ada jadwal => off
                $this->saveAutolog($roster, null, null, 0, 0, 'off', $actualInCarb, $actualOutCarb);

                return;
            }

            // Hari kerja biasa (non-holiday, non-Sunday)
            if (! $actualInCarb) {
                if ($hasSchedule) {
                    $this->saveAutolog($roster, null, null, 0, 0, 'absent', $actualInCarb, $actualOutCarb);
                } else {
                    $this->saveAutolog($roster, null, null, 0, 0, 'off', $actualInCarb, $actualOutCarb);
                }

                return;
            }

            // Ada actual check-in/out => hadir dengan lembur 2 jam
            $checkIn = $actualInCarb;
            $checkOut = $actualOutCarb;
            $otDuration = 120; // 2 jam
            $this->saveAutolog($roster, $checkIn, $checkOut, $otDuration, $lateDuration, null, $actualInCarb, $actualOutCarb);

            return;
        }

        // 3. Check Holiday/Sunday (non-SHIFT & FLEX-SHIFT)
        if ($roster->is_holiday || $roster->is_sun) {
            $this->saveAutolog($roster, null, null, 0, 0);

            return;
        }

        if (! $prepare) {
            return;
        }

        $externalCode = $roster->external_code ?? ($roster->shift?->external_code);

        $randomMinute = rand(0, 9);

        // Weekdays (Mon-Fri)
        if (! $roster->is_sat) {
            if ($externalCode === 'S') {
                $checkOut = Carbon::parse($dateStr." 22:5$randomMinute");
                $checkIn = (clone $checkOut)->subMinutes(488);
                $expectedCheckIn = Carbon::parse($dateStr.' 14:50');
                if ($checkIn->greaterThan($expectedCheckIn)) {
                    $lateDuration = $checkIn->diffInMinutes($expectedCheckIn);
                }
            } elseif ($externalCode === 'P') {
                $checkIn = Carbon::parse($dateStr." 06:4$randomMinute");
                $checkOut = (clone $checkIn)->addMinutes(488);
                $expectedStart = $roster->shift?->work_hour_start ?: '06:50';
                $expectedCheckIn = Carbon::parse($dateStr.' '.$expectedStart);
                if ($checkIn->greaterThan($expectedCheckIn)) {
                    $lateDuration = $checkIn->diffInMinutes($expectedCheckIn);
                }
            }

        }
        // Saturday
        else {
            if ($externalCode === 'S') {
                $checkIn = Carbon::parse($dateStr." 12:5$randomMinute");
                $checkOut = (clone $checkIn)->addHours(6);
            } elseif ($externalCode === 'P') {
                $checkIn = Carbon::parse($dateStr." 06:4$randomMinute");
                $checkOut = (clone $checkIn)->addHours(6);
            }
        }

        $otDuration = ($roster->is_sat || $workPatternType !== 'FLEX-SHIFT') ? 0 : min($prepare?->overtime_duration ?? 0, 180);

        $this->saveAutolog($roster, $checkIn, $checkOut, $otDuration, $lateDuration);
    }

    /**
     * Save/Update AttendanceAutolog record
     */
    /**
     * Extract leave information for a specific employee and date
     * Used for payroll calculation and attendance status determination
     *
     * @param  Carbon\Carbon  $date
     */
    protected function extractLeave(int $employeeId, Carbon $carbon): array
    {
        $dateStr = $carbon->toDateString();

        $leaveRequest = LeaveRequest::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        if (! $leaveRequest) {
            return [
                'found' => false,
                'leave_id' => null,
                'status' => null,
                'leave_type' => null,
                'deduct_day' => null,
                'is_paid' => null,
            ];
        }

        $leaveType = $leaveRequest->leaveType;
        $code = strtolower($leaveType->code ?? '');
        $name = strtolower($leaveType->name ?? '');

        // Determine leave category based on code or name
        // Common patterns: 'sakit' = sick, 'izin' = permit, 'cuti' = leave
        $isSakit = str_contains($code, 'sakit') || str_contains($name, 'sakit');
        $isIzin = str_contains($code, 'izin') || str_contains($name, 'izin') || str_contains($code, 'permit');
        $isCuti = ! $isSakit && ! $isIzin;

        // Determine status based on leave type
        if ($isSakit) {
            $status = 'sakit';
        } elseif ($isIzin) {
            $status = 'izin';
        } else {
            // Default to leave for paid leave, permit for unpaid
            $status = $leaveType->is_paid ? 'leave' : 'permit';
        }

        // Determine deduct_day:
        // - Paid leave (cuti): usually does NOT deduct from attendance count
        // - Unpaid leave (izin): usually DOES deduct from attendance
        // - Sick with medical doc: usually does NOT deduct
        // - Sick without medical doc: usually DOES deduct
        $deductDay = null;
        if ($isSakit) {
            // Sick leave - check if has medical document
            $deductDay = empty($leaveRequest->medical_document_path) ? 1 : 0;
        } elseif ($isIzin) {
            // Permit/Izin - always deduct
            $deductDay = 1;
        } elseif (! $leaveType->is_paid) {
            // Unpaid leave - deduct
            $deductDay = 1;
        } else {
            // Paid leave (cuti) - no deduction
            $deductDay = 0;
        }

        return [
            'found' => true,
            'leave_id' => $leaveRequest->id,
            'status' => $status,
            'leave_type' => $leaveType->name,
            'deduct_day' => $deductDay,
            'is_paid' => $leaveType->is_paid,
            'has_medical_doc' => ! empty($leaveRequest->medical_document_path),
            'duration_days' => $leaveRequest->duration_days,
        ];
    }

    /**
     * Save/Update AttendanceAutolog record
     */
    protected function saveAutolog(EmployeeShiftRoster $roster, ?Carbon $checkIn, ?Carbon $checkOut, int $otDuration, int $lateDuration = 0, ?string $overrideStatus = null, ?Carbon $actualIn = null, ?Carbon $actualOut = null, $leaveData = null)
    {
        $status = $overrideStatus;

        // If leave data is passed, use it to determine status and leave fields
        if ($leaveData && is_array($leaveData) && ($leaveData['found'] ?? false)) {
            $status = $leaveData['status'];
        } elseif (! $status) {
            if ($checkIn || $checkOut) {
                $status = 'present';
            } elseif ($roster->is_holiday) {
                $status = 'holiday';
            } elseif ($roster->is_sun) {
                $status = 'off';
            } else {
                $status = 'absent';
            }
        }

        AttendanceAutolog::updateOrCreate(
            [
                'employee_id' => $roster->employee_id,
                'date' => $roster->date->toDateString(),
            ],
            [
                'company_id' => $roster->company_id,
                'branch_id' => $roster->branch_id,
                'employee_shift_roster_id' => $roster->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'actual_in' => $actualIn,
                'actual_out' => $actualOut,
                'status' => $status,
                'late_duration' => $lateDuration,
                'early_leave_duration' => 0,
                'overtime_duration' => $otDuration,
                'is_half_day' => $roster->is_half_day ?? false,
                'is_sat' => $roster->is_sat ?? false,
                'is_sun' => $roster->is_sun ?? false,
                'is_holiday' => $roster->is_holiday ?? false,
                'is_leave' => $leaveData && is_array($leaveData) ? ($leaveData['found'] ?? false) : ($roster->is_leave ?? false),
                'deduct_day' => $leaveData && is_array($leaveData) ? ($leaveData['deduct_day'] ?? null) : null,
                'leave_id' => $leaveData && is_array($leaveData) ? ($leaveData['leave_id'] ?? null) : null,
                'notes' => 'Auto-generated for auditor',
                'metadata' => ['generated_at' => now()->toDateTimeString()],
            ]
        );
    }
}
