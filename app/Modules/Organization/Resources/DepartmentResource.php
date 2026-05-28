<?php

namespace App\Modules\Organization\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
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
            'parent_id' => $this->parent_id,
            'parent_name' => $this->parent ? $this->parent->name : null,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'manager_id' => $this->manager_id,
            'manager_name' => $this->manager ? $this->manager->name : null,
            'cost_center' => $this->cost_center,
            'is_active' => $this->is_active,
            'level' => $this->level,
            'path' => $this->path,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'children' => DepartmentResource::collection($this->whenLoaded('children')),
        ];
    }
}
