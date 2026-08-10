<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PphReportExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected array $rows;
    protected array $monthNames;
    protected int $year;
    protected int $rowCount = 0;

    public function __construct(array $rows, array $monthNames, int $year)
    {
        $this->rows = $rows;
        $this->monthNames = $monthNames;
        $this->year = $year;
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->rows as $row) {
            $line = [
                $row['employee_name'] ?? '-',
                $row['employee_code'] ?? '-',
                $row['ptkp_status'] ?? '-',
            ];

            foreach (range(1, 12) as $m) {
                $mData = $row['monthly_pph'][$m] ?? null;
                $line[] = $mData && $mData['has_data']
                    ? (float) $mData['report']
                    : 0;
            }

            $line[] = (float) ($row['total_pph_report'] ?? 0);
            $data[] = $line;
            $this->rowCount++;
        }

        // Total row
        $totals = ['TOTAL', '', ''];
        foreach (range(1, 12) as $m) {
            $sum = 0;
            foreach ($this->rows as $row) {
                $mData = $row['monthly_pph'][$m] ?? null;
                if ($mData && $mData['has_data']) {
                    $sum += (float) $mData['report'];
                }
            }
            $totals[] = $sum;
        }
        $totals[] = array_sum(array_slice($totals, 3));
        $data[] = $totals;
        $this->rowCount++;

        return $data;
    }

    public function headings(): array
    {
        $monthCols = array_map(fn($m) => $m, $this->monthNames);

        return [
            ['LAPORAN PPh 21'],
            ["Tahun {$this->year}"],
            [''],
            array_merge(
                ['Nama', 'NIP', 'PTKP'],
                $monthCols,
                ['Total']
            ),
        ];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 25, 'B' => 12, 'C' => 8,
        ];
        // Month columns D-O
        foreach (range(4, 15) as $i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $widths[$col] = 12;
        }
        // Total column P
        $widths['P'] = 14;

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastRow = $this->rowCount + 4; // 4 header rows + data
                $lastCol = 'P'; // A (name) through P (total)

                // Title
                $sheet->mergeCells('A1:P1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells('A2:P2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header row 4
                $sheet->getStyle('A4:P4')->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle('A4:P4')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A4:P4')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE0E0E0');

                // Borders all data
                $dataRange = "A4:{$lastCol}{$lastRow}";
                $sheet->getStyle($dataRange)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Total row bold
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8E8E8');

                // Number format for months + total (D5:P{lastRow})
                $numRange = "D5:{$lastCol}{$lastRow}";
                $sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('#,##0');

                // Freeze pane
                $sheet->freezePane('D5');
            },
        ];
    }
}
