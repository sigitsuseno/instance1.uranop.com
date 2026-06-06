<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Employee\Models\Employee;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PayRecord extends Model
{
    use HasAuditLog, HasUserContext, SoftDeletes;

    protected $table = 'pay_records';

    protected $fillable = [
        'uuid',
        'pay_period_id',
        'employee_id',
        'att_record_id',
        'segment',
        // Data masukan
        'gaji_pokok',
        'premi',
        'tj_masa_kerja',
        'tunjangan',
        'hari_kerja',
        'deduct_day',
        'lm',
        'lm_count',
        'lembur_count',
        // Hasil hitungan
        'gaji',
        'upah_lembur',
        'premi_hadir',
        'revisi',
        'gaji_kotor',
        // Potongan
        'bpjs_tk',
        'bpjs_ks',
        'bpjs_pen',
        'pph',
        'cashbon',
        'pot_kehadiran',
        // Pembulatan
        'pblt',
        // Gaji bersih
        'gaji_bersih',
        // Status
        'status',
        'notes',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'gaji_pokok' => 'decimal:2',
        'premi' => 'decimal:2',
        'tj_masa_kerja' => 'decimal:2',
        'tunjangan' => 'decimal:2',
        'hari_kerja' => 'integer',
        'deduct_day' => 'decimal:2',
        'lm' => 'integer',
        'lm_count' => 'integer',
        'lembur_count' => 'integer',
        'gaji' => 'decimal:2',
        'upah_lembur' => 'decimal:2',
        'premi_hadir' => 'decimal:2',
        'revisi' => 'decimal:2',
        'gaji_kotor' => 'decimal:2',
        'bpjs_tk' => 'decimal:2',
        'bpjs_ks' => 'decimal:2',
        'bpjs_pen' => 'decimal:2',
        'pph' => 'decimal:2',
        'cashbon' => 'decimal:2',
        'pot_kehadiran' => 'decimal:2',
        'pblt' => 'decimal:2',
        'gaji_bersih' => 'decimal:2',
        'synced_at' => 'datetime',
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

    public function attRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'att_record_id');
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
}
