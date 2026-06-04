<?php

namespace App\Modules\Attendance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn() => [
                'id' => $this->employee->id,
                'nip' => $this->employee->nip,
                'full_name' => $this->employee->full_name,
            ]),
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'total_days' => $this->total_days,
            'present_days' => $this->present_days,
            'late_days' => $this->late_days,
            'absent_days' => $this->absent_days,
            'off_days' => $this->off_days,
            'leave_days' => $this->leave_days,
            'holiday_days' => $this->holiday_days,
            'overtime_hours' => $this->overtime_hours,
            'total_late_minutes' => $this->total_late_minutes,
            'is_locked' => $this->is_locked,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
