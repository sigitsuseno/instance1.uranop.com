<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class AttConfig extends Model
{
    use HasAuditLog;

    protected $table = 'att_configs';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_editable',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    /**
     * Get config value with proper type casting.
     */
    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json' => json_decode($this->value, true),
            'time' => $this->value, // "HH:MM" format
            default => $this->value,
        };
    }

    /**
     * Get config by key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $config = static::where('key', $key)->first();
        return $config ? $config->typed_value : $default;
    }

    /**
     * Set config by key.
     */
    public static function setValue(string $key, mixed $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type,
            ]
        );
    }
}
