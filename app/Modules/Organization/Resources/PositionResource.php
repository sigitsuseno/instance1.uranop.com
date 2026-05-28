<?php

namespace App\Modules\Organization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'department_id' => $this->department_id,
            'department_name' => $this->department ? $this->department->name : null,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'job_grade' => $this->job_grade,
            'salary_grade_id' => $this->salary_grade_id,
            'reports_to_position_id' => $this->reports_to_position_id,
            'reports_to_name' => $this->reportsTo ? $this->reportsTo->name : null,
            'is_managerial' => $this->is_managerial,
            'is_active' => $this->is_active,
            'max_incumbents' => $this->max_incumbents,
            'requirements' => $this->requirements,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
