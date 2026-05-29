<?php

namespace App\Modules\Employee\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Settings\Models\SalaryGrade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePositionHistory extends Model
{
    use SoftDeletes;

    protected $table = 'employee_position_histories';

    protected $fillable = [
        'employee_id',
        'old_department_id',
        'old_position_id',
        'old_salary_grade_id',
        'old_salary',
        'new_department_id',
        'new_position_id',
        'new_salary_grade_id',
        'new_salary',
        'effective_date',
        'change_reason',
        'notes',
        'document_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'old_salary'     => 'decimal:2',
        'new_salary'     => 'decimal:2',
    ];

    // ========== RELATIONSHIPS ==========

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function oldDepartment()
    {
        return $this->belongsTo(Department::class, 'old_department_id');
    }

    public function oldPosition()
    {
        return $this->belongsTo(Position::class, 'old_position_id');
    }

    public function oldSalaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class, 'old_salary_grade_id');
    }

    public function newDepartment()
    {
        return $this->belongsTo(Department::class, 'new_department_id');
    }

    public function newPosition()
    {
        return $this->belongsTo(Position::class, 'new_position_id');
    }

    public function newSalaryGrade()
    {
        return $this->belongsTo(SalaryGrade::class, 'new_salary_grade_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ========== SCOPES ==========

    public function scopePromotion($query)
    {
        return $query->where('change_reason', 'promotion');
    }

    public function scopeTransfer($query)
    {
        return $query->where('change_reason', 'transfer');
    }

    // ========== ACCESSORS ==========

    public function getChangeReasonLabelAttribute(): string
    {
        return match ($this->change_reason) {
            'initial'        => 'Awal',
            'promotion'      => 'Promosi',
            'demotion'       => 'Demosi',
            'transfer'       => 'Mutasi',
            'rotation'       => 'Rotasi',
            'upgrade'        => 'Upgrade',
            'restructuring'  => 'Restrukturisasi',
            default          => $this->change_reason ?? '-',
        };
    }

    public function getSalaryDifferenceAttribute(): ?float
    {
        if ($this->old_salary === null || $this->new_salary === null) {
            return null;
        }

        return (float) $this->new_salary - (float) $this->old_salary;
    }
}
