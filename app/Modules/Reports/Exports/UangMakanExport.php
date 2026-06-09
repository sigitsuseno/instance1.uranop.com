<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class UangMakanExport implements WithMultipleSheets
{
    use Exportable;

    protected $data;
    protected $period;

    public function __construct($data, $period)
    {
        $this->data = $data;
        $this->period = $period;
    }

    public function sheets(): array
    {
        return [
            new UangMakanRekapLemburSheet($this->data, $this->period),
            new UangMakanPerhitunganSheet($this->data, $this->period),
            new UangMakanResumeSheet($this->data, $this->period),
        ];
    }
}
