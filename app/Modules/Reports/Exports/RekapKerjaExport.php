<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapKerjaExport implements WithMultipleSheets
{
    protected array $allIn;
    protected array $bulananPrint;
    protected array $uangMakan;
    protected string $periodName;
    protected string $dateStart;
    protected string $dateEnd;

    public function __construct(
        array $allIn,
        array $bulananPrint,
        array $uangMakan,
        string $periodName,
        string $dateStart,
        string $dateEnd
    ) {
        $this->allIn        = $allIn;
        $this->bulananPrint = $bulananPrint;
        $this->uangMakan    = $uangMakan;
        $this->periodName   = $periodName;
        $this->dateStart    = $dateStart;
        $this->dateEnd      = $dateEnd;
    }

    public function sheets(): array
    {
        return [
            new RekapKerjaAllInSheet($this->allIn, $this->periodName, $this->dateStart, $this->dateEnd),
            new RekapKerjaBulananPrintSheet($this->bulananPrint, $this->periodName, $this->dateStart, $this->dateEnd),
            new RekapKerjaUangMakanSheet($this->uangMakan, $this->periodName, $this->dateStart, $this->dateEnd),
        ];
    }
}
