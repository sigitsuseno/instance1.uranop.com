<?php

namespace App\Modules\Organization\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use App\Modules\Shared\Traits\HasUserContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Position extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasUserContext;

    protected $fillable = [
        'uuid',
        'department_id',
        'code',
        'name',
        'description',
        'job_grade',
        'salary_grade_id',
        'reports_to_position_id',
        'is_managerial',
        'is_active',
        'max_incumbents',
        'requirements',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_managerial' => 'boolean',
        'is_active' => 'boolean',
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

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function reportsTo()
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Position::class, 'reports_to_position_id');
    }
}
