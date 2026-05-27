<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    protected $fillable = [
        'user_id',
        'theme',
        'language',
        'date_format',
        'time_format',
        'timezone',
        'notify_email',
        'notify_in_app',
        'notify_whatsapp',
        'default_dashboard',
        'dashboard_layout',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'notify_email' => 'boolean',
            'notify_in_app' => 'boolean',
            'notify_whatsapp' => 'boolean',
            'dashboard_layout' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
