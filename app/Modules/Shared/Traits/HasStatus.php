<?php

namespace App\Modules\Shared\Traits;

trait HasStatus
{
    /**
     * Scope active records
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope inactive records
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Activate record
     */
    public function activate(): bool
    {
        if (! isset($this->is_active)) {
            return false;
        }

        $this->is_active = true;

        return $this->save();
    }

    /**
     * Deactivate record
     */
    public function deactivate(): bool
    {
        if (! isset($this->is_active)) {
            return false;
        }

        $this->is_active = false;

        return $this->save();
    }

    /**
     * Toggle active status
     */
    public function toggleActive(): bool
    {
        if (! isset($this->is_active)) {
            return false;
        }

        $this->is_active = ! $this->is_active;

        return $this->save();
    }
}
