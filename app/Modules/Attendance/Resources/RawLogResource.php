<?php

namespace App\Modules\Attendance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RawLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pin' => $this->pin,
            'employee_code' => $this->employee_code,
            'employee_name' => $this->employee_name,
            'scan_datetime' => $this->scan_datetime?->format('Y-m-d H:i:s'),
            'scan_date' => $this->scan_datetime?->format('Y-m-d'),
            'scan_time' => $this->scan_datetime?->format('H:i:s'),
            'scan_type' => $this->scan_type,
            'machine_sn' => $this->machine_sn,
            'machine_name' => $this->machine_name,
            'verify_type' => $this->verify_type,
            'is_processed' => $this->is_processed,
            'processed_at' => $this->processed_at?->toDateTimeString(),
            'import_batch' => $this->import_batch,
            'source_file' => $this->source_file,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
