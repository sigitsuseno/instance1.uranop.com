<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeFamily extends Model
{
    use SoftDeletes;

    protected $table = 'employee_families';

    protected $fillable = [
        'employee_id',
        'relation',
        'name',
        'gender',
        'nik',
        'date_of_birth',
        'education',
        'occupation',
        'is_dependent',
        'is_emergency_contact',
        'emergency_phone',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth'       => 'date',
        'is_dependent'        => 'boolean',
        'is_emergency_contact'=> 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRelationLabelAttribute(): string
    {
        return match ($this->relation) {
            'spouse'  => 'Pasangan',
            'child'   => 'Anak',
            'parent'  => 'Orang Tua',
            'sibling' => 'Saudara',
            default   => 'Lainnya',
        };
    }

    public function scopeDependents($query)
    {
        return $query->where('is_dependent', true);
    }
}
