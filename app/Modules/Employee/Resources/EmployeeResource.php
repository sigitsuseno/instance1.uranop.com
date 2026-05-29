<?php

namespace App\Modules\Employee\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'uuid'                   => $this->uuid,
            'user_id'                => $this->user_id,
            'employee_group_id'      => $this->employee_group_id,
            'employee_code'          => $this->employee_code,
            'nik'                    => $this->nik,
            'name'                   => $this->name,
            'photo'                  => $this->photo,
            'photo_url'              => $this->photo_url,
            'gender'                 => $this->gender,
            'place_of_birth'         => $this->place_of_birth,
            'date_of_birth'          => $this->date_of_birth?->format('Y-m-d'),
            'age'                    => $this->age,
            'religion'               => $this->religion,
            'blood_type'             => $this->blood_type,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'address'                => $this->address,
            'postal_code'            => $this->postal_code,
            'full_address'           => $this->full_address,
            'npwp'                   => $this->npwp,
            'bpjs_ketenagakerjaan'   => $this->bpjs_ketenagakerjaan,
            'bpjs_kesehatan'         => $this->bpjs_kesehatan,
            'has_npwp'               => $this->has_npwp,
            'ptkp'                   => $this->ptkp,
            'employment_status'      => $this->employment_status,
            'employment_status_label'=> $this->employment_status_label,
            'payroll_cycle'          => $this->payroll_cycle,
            'join_date'              => $this->join_date?->format('Y-m-d'),
            'end_date'               => $this->end_date?->format('Y-m-d'),
            'permanent_date'         => $this->permanent_date?->format('Y-m-d'),
            'resign_date'            => $this->resign_date?->format('Y-m-d'),
            'bank_name'              => $this->bank_name,
            'bank_account_number'    => $this->bank_account_number,
            'bank_account_name'      => $this->bank_account_name,
            // Salary (denormalized cache + live component data)
            'base_salary'            => (float) $this->base_salary,
            'premi'                  => (float) $this->premi,
            'tunjangan'              => (float) $this->tunjangan,
            // Effective date accessors — live query
            'gaji_pokok'             => $this->gaji_pokok(),
            'premi_component'        => $this->premi_component(),
            'tunjangan_masa_kerja'   => $this->tunjangan_masa_kerja(),
            'total_gaji'             => $this->totalGaji(),
            //
            'is_active'              => $this->is_active,
            'years_of_service'       => $this->years_of_service,
            'months_of_service'      => $this->months_of_service,
            'synced_at'              => $this->synced_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
            // Relationships (loaded conditionally)
            'department'             => $this->whenLoaded('department', fn () => [
                'id'   => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ]),
            'position'               => $this->whenLoaded('position', fn () => [
                'id'   => $this->position->id,
                'name' => $this->position->name,
            ]),
            'group'                  => $this->whenLoaded('group', fn () => [
                'id'   => $this->group->id,
                'name' => $this->group->name,
                'code' => $this->group->code,
            ]),
            'latest_contract'        => $this->whenLoaded('latestContract', fn () => $this->latestContract ? [
                'id'              => $this->latestContract->id,
                'contract_number' => $this->latestContract->contract_number,
                'contract_type'   => $this->latestContract->contract_type,
                'contract_type_label' => $this->latestContract->contract_type_label,
                'start_date'      => $this->latestContract->start_date?->format('Y-m-d'),
                'end_date'        => $this->latestContract->end_date?->format('Y-m-d'),
                'status'          => $this->latestContract->status,
                'status_label'    => $this->latestContract->status_label,
                'days_left'       => $this->latestContract->days_left,
                'is_expiring_soon'=> $this->latestContract->is_expiring_soon,
            ] : null),
            'contracts'              => $this->whenLoaded('contracts'),
            'position_histories'     => $this->whenLoaded('positionHistories'),
            'families'               => $this->whenLoaded('families'),
            'documents'              => $this->whenLoaded('documents'),
            'bpjs'                   => $this->whenLoaded('bpjs'),
        ];
    }
}
