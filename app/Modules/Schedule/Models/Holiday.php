<?php

namespace App\Modules\Schedule\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Holiday extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $table = 'sch_holidays';

    protected $fillable = [
        'uuid',
        'working_calendar_id',
        'date',
        'name',
        'description',
        'type',
        'is_national_holiday',
        'is_company_holiday',
        'created_by',
        'updated_by',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'is_national_holiday' => 'boolean',
        'is_company_holiday' => 'boolean',
    ];

    public function workingCalendar()
    {
        return $this->belongsTo(WorkingCalendar::class, 'working_calendar_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeNationalHolidays($query)
    {
        return $query->where('is_national_holiday', true);
    }
}
