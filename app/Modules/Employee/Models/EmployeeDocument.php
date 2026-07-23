<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Sync\Traits\SyncTimestampable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeDocument extends Model
{
    use SoftDeletes, SyncTimestampable;

    protected $table = 'employee_documents';

    protected $fillable = [
        'uuid',
        'employee_id',
        'document_type',
        'document_number',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'issue_date',
        'expiry_date',
        'issued_by',
        'verification_status',
        'verification_notes',
        'verified_by',
        'verified_at',
        'created_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
        'verified_at' => 'datetime',
    ];

    protected $appends = ['file_url'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/'.$this->file_path) : null;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        $labels = [
            'ktp'        => 'KTP',
            'kk'         => 'Kartu Keluarga',
            'npwp'       => 'NPWP',
            'bpjs'       => 'BPJS',
            'ijazah'     => 'Ijazah',
            'transkrip'  => 'Transkrip Nilai',
            'sertifikat' => 'Sertifikat',
            'kontrak'    => 'Kontrak Kerja',
            'sk'         => 'Surat Keputusan',
            'other'      => 'Lainnya',
        ];

        return $labels[$this->document_type] ?? $this->document_type;
    }

    // ========== BOOT ==========

    protected static function booted(): void
    {
        static::bootTimestampable();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
