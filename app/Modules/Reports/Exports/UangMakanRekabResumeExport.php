<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UangMakanRekabResumeExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $label;
    protected $rowNumber = 0;

    // 9 columns: No, Bagian, Uang Makan, Lembur Sabtu, Lembur Minggu, Insentif, PBLT, Revisi, TOTAL
    protected const COL_COUNT = 9;
    protected const LAST_COL = 'I';

    public function __construct($data, $label)
    {
        $this->data  = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->label = $label;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row['bagian'] ?? '-',
            $row['uang_makan'] ?? 0,
            $row['lembur_sabtu'] ?? 0,
            $row['lembur_minggu'] ?? 0,
            $row['insentif'] ?? 0,
            $row['pblt'] ?? 0,
            $row['revisi'] ?? 0,
            $row['total'] ?? 0,
        ];
    }

    public function headings(): array
    {
        $row1 = ['RESUME UANG MAKAN & LEMBUR — ' . strtoupper($this->label)];
        $row2 = [''];

        $row3 = [
            'No', 'BAGIAN',
            'UANG MAKAN', 'LEMBUR SABTU', 'LEMBUR MINGGU',
            'INSENTIF', 'PBLT', 'REVISI', 'TOTAL',
        ];

        return [$row1, $row2, $row3];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 28,  // Bagian
            'C' => 18,  // Uang Makan
            'D' => 18,  // Lembur Sabtu
            'E' => 18,  // Lembur Minggu
            'F' => 16,  // Insentif
            'G' => 16,  // PBLT
            'H' => 16,  // Revisi
            'I' => 18,  // TOTAL
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = self::LAST_COL;
        $dataStartRow = 4;
        $lastRow = count($this->data) + $dataStartRow - 1;
        $totalRow = $lastRow + 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dataStartRow, $lastRow, $totalRow) {
                $sheet = $event->sheet->getDelegate();

                // Title
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Style row 3 headers
                $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A3:{$lastCol}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A3:{$lastCol}3")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Colored headers matching Excel
                $sheet->getStyle('C3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7'); // green - Uang Makan
                $sheet->getStyle('D3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE'); // blue - Lembur Sabtu
                $sheet->getStyle('E3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2'); // red - Lembur Minggu
                $sheet->getStyle('I3')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E7FF'); // indigo - TOTAL

                // Borders all data
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Number format for nominal columns (C-I)
                $nominalCols = ['C', 'D', 'E', 'F', 'G', 'H', 'I'];
                foreach ($nominalCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$totalRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$totalRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Center alignment for No
                $sheet->getStyle("A{$dataStartRow}:A{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ---- TOTAL ROW ----
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->getStyle("A{$totalRow}:B{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:B{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // SUM formulas
                foreach ($nominalCols as $col) {
                    $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$dataStartRow}:{$col}{$lastRow})");
                    $sheet->getStyle("{$col}{$totalRow}")->getFont()->setBold(true);
                }

                // Total row borders + bg
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F0F0');

                // Freeze pane
                $sheet->freezePane('C4');
            },
        ];
    }
}
