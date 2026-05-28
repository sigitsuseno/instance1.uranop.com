<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Support\Facades\Cache;

trait HasCache
{
    /**
     * Boot the trait
     */
    protected static function bootHasCache(): void
    {
        static::saved(function ($model) {
            $model->clearCache();
        });

        static::deleted(function ($model) {
            $model->clearCache();
        });
    }

    /**
     * Get cache key for this model
     */
    public function getCacheKey($suffix = ''): string
    {
        $key = class_basename($this).'_'.$this->id;

        if ($suffix) {
            $key .= '_'.$suffix;
        }

        return $key;
    }

    /**
     * Get cached model
     */
    public static function getCached($id, $minutes = 60)
    {
        $key = class_basename(static::class).'_'.$id;

        return Cache::remember($key, $minutes * 60, function () use ($id) {
            return static::find($id);
        });
    }

    /**
     * Clear cache for this model
     */
    public function clearCache()
    {
        $key = $this->getCacheKey();
        Cache::forget($key);

        // Clear relationship caches jika ada
        if (method_exists($this, 'clearRelatedCache')) {
            $this->clearRelatedCache();
        }
    }
}
