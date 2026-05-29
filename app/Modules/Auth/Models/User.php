<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasPushSubscriptions;

    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_number',
        'phone',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'user_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
    }

    public function branches()
    {
        return $this->belongsToMany(\App\Modules\Organization\Models\Branch::class, 'user_branches')
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
