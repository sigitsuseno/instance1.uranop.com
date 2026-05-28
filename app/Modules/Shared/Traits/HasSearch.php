<?php

namespace App\Modules\Shared\Traits;

trait HasSearch
{
    /**
     * Scope untuk pencarian global
     */
    public function scopeSearch($query, $search, $fields = [])
    {
        if (empty($search)) {
            return $query;
        }

        $searchFields = $fields ?: $this->searchFields ?? [];

        if (empty($searchFields)) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'LIKE', "%{$search}%");
            }
        });
    }

    /**
     * Scope untuk filter by date range
     */
    public function scopeDateRange($query, $field, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate($field, '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate($field, '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope untuk filter by month/year
     */
    public function scopeMonthYear($query, $field, $month = null, $year = null)
    {
        if ($month) {
            $query->whereMonth($field, $month);
        }

        if ($year) {
            $query->whereYear($field, $year);
        }

        return $query;
    }
}
