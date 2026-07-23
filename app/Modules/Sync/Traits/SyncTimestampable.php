<?php

namespace App\Modules\Sync\Traits;

use App\Modules\Sync\Services\SyncService;
use Illuminate\Support\Str;

/**
 * Trait for Eloquent models that are syncable to desktop.
 * Auto-updates sync_module_timestamps on created/updated/deleted events.
 *
 * Usage: In any syncable model, add:
 *   use \App\Modules\Sync\Traits\SyncTimestampable;
 *   protected static function bootSyncTimestampable(): void {
 *       static::bootTimestampable();
 *   }
 */
trait SyncTimestampable
{
    /**
     * Boot the trait — register model event listeners.
     */
    public static function bootTimestampable(): void
    {
        // Get the module name from the sync modules list
        $moduleName = static::getSyncModuleName();
        if (!$moduleName) return;

        static::created(function () use ($moduleName) {
            SyncService::updateModuleTimestamp($moduleName);
        });

        static::updated(function () use ($moduleName) {
            SyncService::updateModuleTimestamp($moduleName);
        });

        static::deleted(function () use ($moduleName) {
            SyncService::updateModuleTimestamp($moduleName);
        });
    }

    /**
     * Resolve the sync module name from the model class.
     * Looks up self::class in SyncService::modules().
     */
    protected static function getSyncModuleName(): ?string
    {
        $class = static::class;

        foreach (SyncService::modules() as $key => $mod) {
            if ($mod['model'] === $class) {
                return $key;
            }
        }

        return null;
    }
}
