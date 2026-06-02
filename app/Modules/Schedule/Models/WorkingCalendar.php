<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkingCalendar extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $table = 'sch_working_calendars';

    protected $fillable = [
        'uuid',
        'name',
        'year',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function holidays()
    {
        return $this->hasMany(Holiday::class, 'working_calendar_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }
}
