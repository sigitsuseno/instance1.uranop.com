<?php

namespace App\Modules\Payroll\Exports;

use App\Modules\Payroll\Exports\Sheets\KirimAllSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class KirimAllExport implements WithMultipleSheets
{
    protected array $dataAllIn;
    protected array $dataPrint;
    protected ?string $tanggalPenggajian;

    public function __construct(array $dataAllIn, array $dataPrint, ?string $tanggalPenggajian = null)
    {
        $this->dataAllIn = $dataAllIn;
        $this->dataPrint = $dataPrint;
        $this->tanggalPenggajian = $tanggalPenggajian;
    }

    public function sheets(): array
    {
        $sheets = [];

        if (!empty($this->dataAllIn)) {
            $totalAllIn = array_sum(array_column($this->dataAllIn, 'gaji_bersih'));
            $sheets[] = new KirimAllSheet(
                $this->dataAllIn,
                $totalAllIn,
                'A - KARYAWAN ALLIN',
                $this->tanggalPenggajian
            );
        }

        if (!empty($this->dataPrint)) {
            $totalPrint = array_sum(array_column($this->dataPrint, 'gaji_bersih'));
            $sheets[] = new KirimAllSheet(
                $this->dataPrint,
                $totalPrint,
                'B - KARYAWAN BULANAN PRINT',
                $this->tanggalPenggajian
            );
        }

        return $sheets;
    }
}
