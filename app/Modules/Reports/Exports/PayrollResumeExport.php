<?php

namespace App\Modules\Reports\Exports;

use App\Modules\Reports\Exports\Sheets\PayrollResumeSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollResumeExport implements WithMultipleSheets
{
    protected array $dataAllIn;
    protected array $dataPrint;
    protected string $periodName;

    public function __construct(array $dataAllIn, array $dataPrint, string $periodName)
    {
        $this->dataAllIn = $dataAllIn;
        $this->dataPrint = $dataPrint;
        $this->periodName = $periodName;
    }

    public function sheets(): array
    {
        $sheets = [];

        if (!empty($this->dataAllIn)) {
            $sheets[] = new PayrollResumeSheet(
                $this->dataAllIn,
                'A. KARYAWAN ALL IN',
                $this->periodName
            );
        }

        if (!empty($this->dataPrint)) {
            $sheets[] = new PayrollResumeSheet(
                $this->dataPrint,
                'B. KARYAWAN BULANAN PRINT',
                $this->periodName
            );
        }

        return $sheets;
    }
}
