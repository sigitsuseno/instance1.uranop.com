<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UangMakanRekapExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    protected $data;
    protected $label;
    protected $rowNumber = 0;

    // Fixed 16 columns: No, Nama, Group, Jabatan, UM, 2, FULL, HALF, L, UM, Lbr Sabtu, Lbr Minggu, Insentif, PBLT, Revisi, TOTAL
    protected const COL_COUNT = 16;
    protected const LAST_COL = 'P';

    public function __construct($data, $label)
    {
        $this->data  = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->label = $label;
    }

    public function title(): string
    {
        return 'Uang Makan';
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $this->rowNumber++;
        $c = $row['counts'] ?? [];
        $n = $row['nominals'] ?? [];

        return [
            $this->rowNumber,
            $row['name'] ?? '',
            $row['group_name'] ?? '-',
            $row['jabatan'] ?? '-',
            $c['UM'] ?? 0,
            $c['2'] ?? 0,
            $c['FULL'] ?? 0,
            $c['HALF'] ?? 0,
            $c['FULL_D'] ?? 0,
            $n['uang_makan'] ?? 0,
            $n['lembur_sabtu'] ?? 0,
            $n['lembur_minggu'] ?? 0,
            $n['insentif'] ?? 0,
            $n['pblt'] ?? 0,
            $n['revisi'] ?? 0,
            $row['total'] ?? 0,
        ];
    }

    public function headings(): array
    {
        $row1 = ['REKAP UANG MAKAN — ' . strtoupper($this->label)];
        $row2 = [''];

        $row3 = [
            'No', 'Nama', 'Group', 'Jabatan',
            'LEMBUR', '', '', '', '',
            'UANG MAKAN', 'LEMBUR SABTU', 'LEMBUR MINGGU',
            'INSENTIF', 'PBLT', 'REVISI', 'TOTAL',
        ];

        $row4 = [
            '', '', '', '',
            'UM', '2', 'FULL', '1/2 HK', 'FULL D',
            '', '', '', '', '', '', '',
        ];

        return [$row1, $row2, $row3, $row4];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 30,  // Nama
            'C' => 14,  // Group
            'D' => 22,  // Jabatan
            'E' => 8,   // UM
            'F' => 8,   // 2
            'G' => 8,   // FULL
            'H' => 8,   // 1/2 HK
            'I' => 8,   // L
            'J' => 16,  // Uang Makan
            'K' => 16,  // Lembur Sabtu
            'L' => 16,  // Lembur Minggu
            'M' => 14,  // Insentif
            'N' => 14,  // PBLT
            'O' => 14,  // Revisi
            'P' => 16,  // TOTAL
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = self::LAST_COL;
        $dataStartRow = 5;
        $lastRow = count($this->data) + $dataStartRow - 1;
        // +1 for TOTAL row
        $totalRow = $lastRow + 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dataStartRow, $lastRow, $totalRow) {
                $sheet = $event->sheet->getDelegate();

                // Title
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Merge "LEMBUR" header in row 3 (E3:I3)
                $sheet->mergeCells('E3:I3');

                // Style row 3 & 4 headers
                $sheet->getStyle("A3:{$lastCol}4")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A3:{$lastCol}4")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A3:{$lastCol}4")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // "LEMBUR" header bg amber
                $sheet->getStyle('E3:J4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF3C7');

                // Nominal headers bg green/blue/red
                $sheet->getStyle('J3:J4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7');
                $sheet->getStyle('K3:K4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');
                $sheet->getStyle('L3:L4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2');
                $sheet->getStyle('P3:P4')->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E7FF');

                // Borders all
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Number format for nominal columns
                $nominalCols = ['J', 'K', 'L', 'M', 'N', 'O', 'P'];
                foreach ($nominalCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }
                // TOTAL column (P) — 2 decimal places
                $sheet->getStyle("P{$dataStartRow}:P{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // Center alignment
                $centerCols = ['A', 'C', 'E', 'F', 'G', 'H', 'I'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Right align nominals
                foreach ($nominalCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ---- TOTAL ROW ----
                $sheet->setCellValue("A{$totalRow}", '');
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // SUM formulas for each column
                $countCols = ['E', 'F', 'G', 'H', 'I'];
                foreach ($countCols as $col) {
                    $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$dataStartRow}:{$col}{$lastRow})");
                    $sheet->getStyle("{$col}{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("{$col}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                foreach ($nominalCols as $col) {
                    $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$dataStartRow}:{$col}{$lastRow})");
                    $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("{$col}{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("{$col}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                // TOTAL column (P) total row — 2 decimal places
                $sheet->getStyle("P{$totalRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // Total row borders
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0F0F0');

                // Freeze pane
                $sheet->freezePane('E5');
            },
        ];
    }
}
