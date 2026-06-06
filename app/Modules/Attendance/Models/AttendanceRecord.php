<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AttendanceRecord extends Model
{
    use HasAuditLog, HasUserContext, SoftDeletes;

    protected $table = 'att_records';

    protected $fillable = [
        'uuid',
        'employee_id',
        'pay_period_id',
        'segment',
        'hari_kerja',
        'cuti',
        'izin',
        'sakit',
        'absen',
        'deduct_day',
        'late_minutes',
        'lm',
        'lm_count',
        'lembur',
        'lembur_count',
        'status',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'hari_kerja' => 'integer',
        'cuti' => 'decimal:2',
        'izin' => 'decimal:2',
        'sakit' => 'decimal:2',
        'absen' => 'integer',
        'deduct_day' => 'decimal:2',
        'late_minutes' => 'integer',
        'lm' => 'integer',
        'lm_count' => 'integer',
        'lembur' => 'integer',
        'lembur_count' => 'integer',
        'synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    // ========== RELATIONSHIPS ==========

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }

    // ========== SCOPES ==========

    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('pay_period_id', $periodId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }
}
