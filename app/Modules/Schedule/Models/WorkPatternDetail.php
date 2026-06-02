<?php

namespace App\Modules\Schedule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkPatternDetail extends Model
{
    use HasFactory;

    protected $table = 'sch_work_pattern_details';

    protected $fillable = [
        'uuid',
        'work_pattern_id',
        'shift_id',
        'name',
        'cycle_day',
        'day_number',
        'day_type',
        'is_workday',
        'is_half_day',
        'date_label',
    ];

    protected $casts = [
        'cycle_day' => 'integer',
        'day_number' => 'integer',
        'day_type' => 'string',
        'is_workday' => 'boolean',
        'is_half_day' => 'boolean',
    ];

    public function workPattern()
    {
        return $this->belongsTo(WorkPattern::class, 'work_pattern_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function scopeForDayType($query, $type)
    {
        return $query->where('day_type', $type);
    }

    public function scopeWorkDays($query)
    {
        return $query->whereIn('day_type', ['work_day', 'half_day']);
    }

    public function scopeDayOff($query)
    {
        return $query->where('day_type', 'day_off');
    }

    public function isWorkDay()
    {
        return in_array($this->day_type, ['work_day', 'half_day']);
    }

    public function isHalfDay()
    {
        return $this->day_type === 'half_day';
    }

    public function isDayOff()
    {
        return $this->day_type === 'day_off';
    }

    public function isSunday()
    {
        return $this->day_type === 'is_sun';
    }
}
