<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class UangMakanHarianExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths
{
    protected $data;
    protected $date;

    public function __construct(array $data, string $date)
    {
        $this->data = $data;
        $this->date = $date;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        Carbon::setLocale('id');
        $formatted = Carbon::parse($this->date)->translatedFormat('l, d F Y');

        return [
            ['LAPORAN UANG MAKAN HARIAN — ' . strtoupper($formatted)],
            [''],
            ['No', 'Nama', 'Bagian / Jabatan', 'L/P', 'Tj. Masa Kerja', 'Tunjangan', 'Upah Lembur Per Jam',
             'Kode', 'H/A', 'Upah/Hari', 'L/M', 'Lembur', 'Nominal'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 30, 'C' => 22, 'D' => 5,
            'E' => 16, 'F' => 16, 'G' => 16,
            'H' => 8,  'I' => 6,  'J' => 14, 'K' => 8,
            'L' => 10, 'M' => 14,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 3;
        $lastCol = 'M';

        // Title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        // Headers
        $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("A3:{$lastCol}3")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8EAED');

        // Borders
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Number formats
        $sheet->getStyle("E4:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("J4:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("M4:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Alignment
        $sheet->getStyle("A4:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D4:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H4:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [];
    }
}
