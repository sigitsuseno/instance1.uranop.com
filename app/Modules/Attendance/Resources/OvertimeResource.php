<?php

namespace App\Modules\Attendance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OvertimeResource extends JsonResource
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
            ]),
            'date' => $this->date?->format('Y-m-d'),
            'overtime_rule_id' => $this->overtime_rule_id,
            'overtime_rule' => $this->whenLoaded('overtimeRule', fn() => [
                'id' => $this->overtimeRule->id,
                'code' => $this->overtimeRule->code,
                'name' => $this->overtimeRule->name,
            ]),
            'start_time' => $this->start_time?->toDateTimeString(),
            'end_time' => $this->end_time?->toDateTimeString(),
            'total_hours' => $this->total_hours,
            'multiplier' => $this->multiplier,
            'calculated_hours' => $this->calculated_hours,
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'pending' => 'Menunggu',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => $this->status,
            },
            'approved_by' => $this->approved_by,
            'approver' => $this->whenLoaded('approver', fn() => [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ]),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'reject_reason' => $this->reject_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
