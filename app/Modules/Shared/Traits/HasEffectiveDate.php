<?php

namespace App\Modules\Shared\Traits;

use Carbon\Carbon;

trait HasEffectiveDate
{
    /**
     * Scope records effective at given date
     */
    public function scopeEffectiveAt($query, $date = null)
    {
        $date = $date ?? Carbon::today();

        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expired_date')
                    ->orWhere('expired_date', '>=', $date);
            });
    }

    /**
     * Scope records effective now
     */
    public function scopeEffectiveNow($query)
    {
        return $this->scopeEffectiveAt($query, Carbon::now());
    }

    /**
     * Scope expired records
     */
    public function scopeExpired($query)
    {
        return $query->where('expired_date', '<', Carbon::today());
    }

    /**
     * Check if record is effective at given date
     */
    public function isEffectiveAt($date = null): bool
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();

        return $this->effective_date <= $date
            && (is_null($this->expired_date) || $this->expired_date >= $date);
    }

    /**
     * Check if record is currently effective
     */
    public function isEffective(): bool
    {
        return $this->isEffectiveAt(Carbon::now());
    }
}
