<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class ScanDetectionConfig extends Model
{
    use HasAuditLog;

    protected $table = 'att_scan_configs';

    protected $fillable = [
        'machine_sn',
        'machine_name',
        'scan_type_rules',
        'is_active',
        'priority',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scan_type_rules' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Scope: active configs only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('priority', 'desc');
    }

    /**
     * Detect scan type based on time and configured rules.
     * Returns: 'in', 'out', 'break', or null.
     */
    public function detectScanType(string $timeString): ?string
    {
        if (empty($this->scan_type_rules)) {
            return null;
        }

        foreach ($this->scan_type_rules as $rule) {
            $start = $rule['start'] ?? null;
            $end = $rule['end'] ?? null;

            if (!$start || !$end) continue;

            if ($timeString >= $start && $timeString <= $end) {
                return $rule['type'] ?? null;
            }
        }

        return null;
    }
}
