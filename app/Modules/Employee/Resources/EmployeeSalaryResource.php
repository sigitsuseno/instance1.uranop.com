<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeSalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'employee'              => [
                'id'            => $this->employee->id ?? null,
                'name'          => $this->employee->name ?? null,
                'employee_code' => $this->employee->employee_code ?? null,
                'department'    => $this->employee->department->name ?? null,
                'position'      => $this->employee->position->name ?? null,
            ],
            'base_salary'           => (float) $this->base_salary,
            'premi'                 => (float) $this->premi,
            'tunjangan'             => (float) $this->tunjangan,
            'previous_basic_salary' => (float) $this->previous_basic_salary,
            'allowance_transport'   => (float) $this->allowance_transport,
            'allowance_meal'        => (float) $this->allowance_meal,
            'allowance_position'    => (float) $this->allowance_position,
            'total_salary'          => (float) $this->total_salary,
            'effective_date'        => $this->effective_date?->format('Y-m-d'),
            'end_date'              => $this->end_date?->format('Y-m-d'),
            'change_type'           => $this->change_type,
            'change_type_label'     => $this->change_type_label,
            'letter_number'         => $this->letter_number,
            'reason'                => $this->reason,
            'is_active'             => (bool) $this->is_active,
            'created_by'            => [
                'id'   => $this->createdBy->id ?? null,
                'name' => $this->createdBy->name ?? null,
            ],
            'created_at'            => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
