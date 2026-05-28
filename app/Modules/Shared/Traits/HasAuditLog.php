<?php

namespace App\Modules\Shared\Traits;

use App\Modules\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait HasAuditLog
{
    /**
     * Boot the trait - register model event listeners
     */
    protected static function bootHasAuditLog(): void
    {
        // Log create
        static::created(function ($model) {
            static::logAuditEvent($model, 'CREATE', null, $model->toArray());
        });

        // Log update
        static::updated(function ($model) {
            $changes = $model->getChanges();
            $oldValues = array_intersect_key($model->getOriginal(), $changes);

            static::logAuditEvent($model, 'UPDATE', $oldValues, $changes);
        });

        // Log delete
        static::deleted(function ($model) {
            static::logAuditEvent($model, 'DELETE', $model->toArray(), null);
        });

        // Log restore (jika pakai SoftDeletes)
        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                static::logAuditEvent($model, 'RESTORE', null, $model->toArray());
            });
        }
    }

    /**
     * Log audit event ke database
     */
    protected static function logAuditEvent(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $user = Auth::user();

        // Skip jika tidak ada user (cronjob, queue, dll)
        if (! $user && ! config('audit.log_cli', false)) {
            return;
        }

        // Ambil context (main/shadow) dari properti objek jika ada, default 'main'
        $context = $model->audit_context ?? 'main';

        AuditLog::create([
            'user_id' => $user?->id,
            'context' => $context,
            'action' => $action,
            'module' => static::getModuleName($model),
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => static::sanitizeAuditData($oldValues),
            'new_values' => static::sanitizeAuditData($newValues),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'duration_ms' => defined('LARAVEL_START')
                ? (microtime(true) - LARAVEL_START) * 1000
                : null,
        ]);
    }

    /**
     * Tentukan nama module berdasarkan namespace
     */
    protected static function getModuleName(Model $model): string
    {
        $class = get_class($model);
        $parts = explode('\\', $class);

        // Cari 'Modules' di path
        $modulesKey = array_search('Modules', $parts);
        if ($modulesKey !== false && isset($parts[$modulesKey + 1])) {
            return $parts[$modulesKey + 1];
        }

        return 'Core';
    }

    /**
     * Sanitasi data audit (hapus field sensitif)
     */
    protected static function sanitizeAuditData(?array $data): ?array
    {
        if (! $data) {
            return null;
        }

        $sensitiveFields = [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'api_token',
            'oauth_token',
            'oauth_token_secret',
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Get audit logs untuk model ini
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'model');
    }
}
