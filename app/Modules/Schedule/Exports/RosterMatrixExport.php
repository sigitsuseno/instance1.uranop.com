<?php

namespace App\Modules\Schedule\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RosterMatrixExport implements FromArray, WithColumnWidths, WithCustomValueBinder, WithEvents
{
    /**
     * @param  array<int, array<int, mixed>>  $rows  Baris karyawan: NIP, NAMA LENGKAP, WP, lalu kode per tanggal.
     */
    public function __construct(
        protected array $rows,
        protected Carbon $start,
        protected Carbon $end,
        protected string $title,
    ) {
    }

    public function array(): array
    {
        return array_merge(
            [[$this->title]],
            [$this->headings()],
            $this->rows,
        );
    }

    protected function headings(): array
    {
        $headings = ['NIP', 'NAMA LENGKAP', 'WP'];

        $current = $this->start->copy();
        while ($current <= $this->end) {
            $headings[] = $current->day;
            $current->addDay();
        }

        return $headings;
    }

    protected function dayCount(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    protected function lastColumn(): string
    {
        return Coordinate::stringFromColumnIndex(3 + $this->dayCount());
    }

    /**
     * NIP disimpan sebagai teks agar Excel tidak mengubahnya menjadi angka.
     */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'A' && $cell->getRow() >= 3) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5.6,
            'B' => 32.6,
            'C' => 8.4,
        ];

        for ($index = 4; $index <= 3 + $this->dayCount(); $index++) {
            $widths[Coordinate::stringFromColumnIndex($index)] = 3.9;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        $lastColumn = $this->lastColumn();
        $lastRow = count($this->rows) + 2;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastColumn, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                $sheet->freezePane('C3');

                $sheet->getStyle("A2:{$lastColumn}2")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if (count($this->rows) > 0) {
                    $sheet->getStyle("A3:A{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('@');

                    $sheet->setAutoFilter("A2:{$lastColumn}{$lastRow}");
                }
            },
        ];
    }
}
