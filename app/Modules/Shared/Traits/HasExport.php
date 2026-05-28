<?php

namespace App\Modules\Shared\Traits;

use Carbon\Carbon;

trait HasExport
{
    /**
     * Define exportable fields
     */
    public function getExportableFields(): array
    {
        return $this->exportable ?? ['id', 'name', 'created_at'];
    }

    /**
     * Define export headings
     */
    public function getExportHeadings(): array
    {
        $headings = [];

        foreach ($this->getExportableFields() as $field) {
            $headings[] = __("fields.{$field}");
        }

        return $headings;
    }

    /**
     * Format for export
     */
    public function toExportArray(): array
    {
        $data = [];

        foreach ($this->getExportableFields() as $field) {
            $value = $this->{$field};

            // Format dates
            if ($value instanceof Carbon) {
                $value = $value->format('Y-m-d H:i:s');
            }

            // Format boolean
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }

            $data[$field] = $value;
        }

        return $data;
    }
}
