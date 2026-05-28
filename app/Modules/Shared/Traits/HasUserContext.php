<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Support\Facades\Auth;

trait HasUserContext
{
    /**
     * Scope untuk filter berdasarkan user yang create
     */
    public function scopeCreatedBy($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if ($userId) {
            return $query->where('created_by', $userId);
        }

        return $query;
    }

    /**
     * Scope untuk filter berdasarkan user yang update
     */
    public function scopeUpdatedBy($query, $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if ($userId) {
            return $query->where('updated_by', $userId);
        }

        return $query;
    }

    /**
     * Auto-set created_by dan updated_by
     */
    protected static function bootHasUserContext(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                if (! $model->created_by) {
                    $model->created_by = Auth::id();
                }
                if (! $model->updated_by) {
                    $model->updated_by = Auth::id();
                }
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
}
