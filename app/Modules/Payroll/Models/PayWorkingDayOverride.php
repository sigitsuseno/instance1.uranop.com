<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * PayWorkingDayOverride — pengaturan khusus hari_kerja per karyawan per periode.
 *
 * Dipakai saat finalisasi (GajiKaryawanController::finalisasi()): karyawan yang
 * terdaftar di sini memakai hari_kerja dari pengaturan, bukan hasil hitung otomatis
 * (hk_segmen − izin tak dibayar − absent).
 */
class PayWorkingDayOverride extends Model
{
    use HasAuditLog, HasUserContext, SoftDeletes;

    protected $table = 'pay_working_day_overrides';

    protected $fillable = [
        'uuid',
        'pay_period_id',
        'employee_id',
        'hari_kerja',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hari_kerja' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? Str::uuid()->toString();
        });
    }

    // ========== RELATIONSHIPS ==========

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // ========== SCOPES ==========

    public function scopeForPeriod($query, $periodId)
    {
        return $query->where('pay_period_id', $periodId);
    }
}
