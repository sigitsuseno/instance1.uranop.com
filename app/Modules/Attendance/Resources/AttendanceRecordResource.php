<?php

namespace App\Modules\Attendance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'employee_id' => $this->employee_id,
            'employee' => $this->whenLoaded('employee', fn() => [
                'id' => $this->employee->id,
                'nip' => $this->employee->nip,
                'full_name' => $this->employee->full_name,
                'department' => $this->employee->currentPosition()?->department?->name,
                'position' => $this->employee->currentPosition()?->position?->name,
            ]),
            'date' => $this->date?->format('Y-m-d'),
            'shift_id' => $this->shift_id,
            'work_pattern_id' => $this->work_pattern_id,
            'schedule_in' => $this->schedule_in,
            'schedule_out' => $this->schedule_out,
            'actual_in' => $this->actual_in?->format('H:i:s'),
            'actual_in_full' => $this->actual_in?->toDateTimeString(),
            'actual_out' => $this->actual_out?->format('H:i:s'),
            'actual_out_full' => $this->actual_out?->toDateTimeString(),
            'late_minutes' => $this->late_minutes,
            'early_minutes' => $this->early_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'is_manual' => $this->is_manual,
            'is_locked' => $this->is_locked,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    protected function getStatusLabel(): string
    {
        return match ($this->status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Tidak Hadir',
            'off' => 'Libur',
            'holiday' => 'Hari Libur',
            'permit' => 'Izin',
            'sick' => 'Sakit',
            'half_day' => 'Setengah Hari',
            default => $this->status ?? '-',
        };
    }
}
