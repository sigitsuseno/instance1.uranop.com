<?php

namespace App\Modules\Supervisor\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupervisorAttendanceSnapshot extends Model
{
    use SoftDeletes;

    protected $table = 'supervisor_att_snapshot';

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

    // ========== BOOT ==========

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
        return $this->belongsTo(PayPeriod::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== SCOPES ==========

    public function scopeByPayPeriod($query, $payPeriodId)
    {
        return $query->where('pay_period_id', $payPeriodId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
