<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'employee_code'          => $this->employee_code,
            'name'                   => $this->name,
            'photo_url'              => $this->photo_url,
            'gender'                 => $this->gender,
            'employment_status'      => $this->employment_status,
            'employment_status_label'=> $this->employment_status_label,
            'payroll_cycle'          => $this->payroll_cycle,
            'join_date'              => $this->join_date?->format('Y-m-d'),
            'origin_join_date'       => $this->origin_join_date?->format('Y-m-d'),
            'is_active'              => $this->is_active,
            'years_of_service'       => $this->years_of_service,
            'department'             => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
            ]),
            'position'               => $this->whenLoaded('position', fn () => [
                'id'   => $this->position->id,
                'name' => $this->position->name,
            ]),
            // Denormalized cache untuk performa list
            'base_salary'            => (float) $this->base_salary,
            'premi'                  => (float) $this->premi,
            'tunjangan'              => (float) $this->tunjangan,
            'latest_contract'        => $this->whenLoaded('latestContract', fn () => $this->latestContract),
        ];
    }
}
