<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $pay_periode_id
 * @property int $employee_id
 * @property array $komponen  // JSON: [{nama, nilai, keterangan}, ...]
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class EmployeeReserve extends Model
{
    use SoftDeletes;

    protected $table = 'employee_reserves';

    protected $fillable = [
        'uuid',
        'pay_periode_id',
        'employee_id',
        'komponen',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'komponen' => 'array',
        'pay_periode_id' => 'integer',
        'employee_id' => 'integer',
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

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class, 'pay_periode_id');
    }

    public function employee()
    {
        return $this->belongsTo(\App\Modules\Employee\Models\Employee::class, 'employee_id');
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
