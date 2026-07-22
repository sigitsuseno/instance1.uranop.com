<?php

namespace App\Modules\Sync\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DesktopLicense extends Model
{
    use SoftDeletes;

    protected $table = 'desktop_licenses';

    protected $fillable = [
        'uuid',
        'license_key',
        'instance_name',
        'activated_at',
        'licensed_until',
        'status',
        'last_sync_at',
        'last_sync_ip',
        'token',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'licensed_until' => 'date',
        'last_sync_at' => 'datetime',
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

    public function isValid(): bool
    {
        return $this->status === 'active'
            && $this->licensed_until
            && $this->licensed_until >= now()->toDateString();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
