<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UangMakanResumeExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $data;
    protected $dates;
    protected $label;

    public function __construct($data, $dates, $label)
    {
        $this->data = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->dates = $dates;
        $this->label = $label;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            ['RESUME UANG MAKAN — ' . strtoupper($this->label)],
            [''],
            ['No', 'Bagian', 'Uang Makan', 'Lembur Sabtu', 'Lembur Minggu', 'Total'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 35, 'C' => 20,
            'D' => 20, 'E' => 20, 'F' => 20,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 4; // 1=title, 2=empty, 3=header, 4=data, +total row
        $lastCol = 'F';

        // Title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Headers
        $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A3:{$lastCol}3")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8EAED');

        // Borders
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Total row
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF3F4F6');

        // Number formats
        $sheet->getStyle("C4:{$lastCol}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

        // Alignment
        $sheet->getStyle("A4:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C4:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
