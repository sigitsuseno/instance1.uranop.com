<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $autolog_id
 * @property int $employee_id
 * @property int|null $pay_periode_id
 * @property string $date
 * @property float $lembur
 * @property float $lembur_hitung
 * @property string|null $um_code
 * @property float $nominal
 * @property float $insentif
 * @property array|null $komponen
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class EmployeeOvertime extends Model
{
    use SoftDeletes;

    protected $table = 'employee_overtime';

    protected $fillable = [
        'uuid',
        'autolog_id',
        'employee_id',
        'pay_periode_id',
        'date',
        'lembur',
        'lembur_hitung',
        'um_code',
        'nominal',
        'insentif',
        'komponen',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'          => 'date:Y-m-d',
        'lembur'        => 'decimal:2',
        'lembur_hitung' => 'decimal:2',
        'nominal'       => 'decimal:2',
        'insentif'      => 'decimal:2',
        'komponen'      => 'array',
        'pay_periode_id' => 'integer',
        'employee_id'    => 'integer',
        'autolog_id'     => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relasi ───

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class, 'pay_periode_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}