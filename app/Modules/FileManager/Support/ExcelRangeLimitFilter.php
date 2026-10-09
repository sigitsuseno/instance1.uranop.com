<?php

namespace App\Modules\FileManager\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Membatasi sel yang dibaca PhpSpreadsheet.
 *
 * Filter ini dipasang sebelum workbook dimuat, jadi sel di luar batas tidak
 * pernah dibuat di memori. Ini yang mencegah sheet berisi ratusan ribu baris
 * menghabiskan memori PHP — jauh lebih aman daripada memuat semuanya lalu
 * memotong hasilnya.
 *
 * Tanda tangan method sengaja tanpa tipe parameter, harus persis sama dengan
 * IReadFilter agar tidak melanggar aturan variance PHP.
 */
class ExcelRangeLimitFilter implements IReadFilter
{
    public function __construct(
        private readonly int $maxRow,
        private readonly int $maxColumn,
    ) {}

    /**
     * @param  string  $columnAddress
     * @param  int  $row
     * @param  string  $worksheetName
     */
    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        if ($row > $this->maxRow) {
            return false;
        }

        return Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumn;
    }
}
