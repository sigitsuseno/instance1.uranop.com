<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AttendanceMatrixExport implements WithMultipleSheets
{
    protected $sections;
    protected $dates;
    protected $label;
    protected $companyName;

    public function __construct($sections, $dates, $label, $companyName = 'PT. KEMILAU UNGARAN SUKSES')
    {
        $this->sections = $sections;
        $this->dates = $dates;
        $this->label = $label;
        $this->companyName = $companyName;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->sections as $section) {
            if (empty($section['data'])) continue;

            $sheets[] = new AttendanceSectionSheet(
                $section,
                $this->dates,
                $this->label,
                $this->companyName
            );
        }

        return $sheets;
    }
}
