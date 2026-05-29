<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeThr extends Model
{
    use SoftDeletes;

    protected $table = 'employee_thr';

    protected $fillable = [
        'employee_id',
        'thr_year',
        'thr_month',
        'gaji_pokok',
        'premi',
        'tunjangan_masa_kerja',
        'join_date',
        'reference_date',
        'total_bulan',
        'sisa_hari',
        'lama_bekerja',
        'thr_amount',
        'pembulatan',
        'total_thr',
        'total_terima',
        'no_account',
        'status',
        'catatan',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gaji_pokok'           => 'decimal:2',
        'premi'                => 'decimal:2',
        'tunjangan_masa_kerja' => 'decimal:2',
        'thr_amount'           => 'decimal:2',
        'pembulatan'           => 'decimal:2',
        'total_thr'            => 'decimal:2',
        'total_terima'         => 'decimal:2',
        'join_date'            => 'date',
        'reference_date'       => 'date',
        'thr_year'             => 'integer',
        'total_bulan'          => 'integer',
        'sisa_hari'            => 'integer',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'    => 'Draft',
            'approved' => 'Disetujui',
            'paid'     => 'Sudah Dibayar',
            default    => $this->status,
        };
    }
}
