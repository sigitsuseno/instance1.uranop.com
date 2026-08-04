<?php

namespace App\Modules\Payroll\Exports;

use App\Modules\Payroll\Exports\Sheets\KirimAllSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TransferGajiExport implements WithMultipleSheets
{
    protected array $secAData;
    protected array $secBData;
    protected ?string $tanggalPenggajian;

    public function __construct(array $secAData, array $secBData, ?string $tanggalPenggajian = null)
    {
        $this->secAData = $secAData;
        $this->secBData = $secBData;
        $this->tanggalPenggajian = $tanggalPenggajian;
    }

    public function sheets(): array
    {
        $sheets = [];

        if (!empty($this->secAData)) {
            $sheets[] = new KirimAllSheet(
                $this->secAData,
                array_sum(array_column($this->secAData, 'gaji_bersih')),
                'A - KARYAWAN ALLIN',
                $this->tanggalPenggajian,
                'TRANSFER GAJI'
            );
        }

        if (!empty($this->secBData)) {
            $sheets[] = new KirimAllSheet(
                $this->secBData,
                array_sum(array_column($this->secBData, 'gaji_bersih')),
                'B - KARYAWAN BULANAN PRINT',
                $this->tanggalPenggajian,
                'TRANSFER GAJI'
            );
        }

        return $sheets;
    }
}
