<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapKerjaBulananPrintSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected array $rows;
    protected string $periodName;
    protected string $dateStart;
    protected string $dateEnd;
    protected int $rowCount = 0;
    protected array $totals;
    protected string $lastCol = 'G';

    public function __construct(array $rows, string $periodName, string $dateStart, string $dateEnd)
    {
        $this->rows       = $rows;
        $this->periodName = $periodName;
        $this->dateStart  = $dateStart;
        $this->dateEnd    = $dateEnd;

        $this->totals = [
            'gaji'   => array_sum(array_column($rows, 'gaji')),
            'lembur' => array_sum(array_column($rows, 'lembur')),
            'total'  => array_sum(array_column($rows, 'total')),
            'bpjs'   => array_sum(array_column($rows, 'bpjs')),
        ];
    }

    public function title(): string
    {
        return 'BULANAN PRINT';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 28,  // BAGIAN
            'C' => 7,   // JML
            'D' => 16,  // GAJI
            'E' => 16,  // LEMBUR
            'F' => 16,  // TOTAL
            'G' => 18,  // BPJS (TK + KS)
        ];
    }

    public function headings(): array
    {
        $periodUpper = strtoupper($this->periodName);

        return [
            ['REKAP KERJA — SECTION B: BULANAN PRINT'],
            ['PT KEMILAU UNGARAN SUKSES'],
            ["Periode: {$this->dateStart} s/d {$this->dateEnd}"],
            [''],
            [
                'No',
                'BAGIAN',
                'JML',
                "GAJI\n{$periodUpper}",
                "LEMBUR\n{$periodUpper}",
                'TOTAL',
                "BPJS\n(TK + KS)",
            ],
        ];
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->rows as $i => $row) {
            $data[] = [
                $i + 1,
                $row['bagian'] ?? '-',
                (int) ($row['jml'] ?? 0),
                (float) ($row['gaji'] ?? 0),
                (float) ($row['lembur'] ?? 0),
                (float) ($row['total'] ?? 0),
                (float) ($row['bpjs'] ?? 0),
            ];
            $this->rowCount++;
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol  = $this->lastCol;
        $rowCount = &$this->rowCount;
        $totals   = $this->totals;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, &$rowCount, $totals) {
                $sheet = $event->sheet->getDelegate();

                $headerRow = 5;
                $dataStart = 6;
                $dataEnd   = $dataStart + $rowCount - 1;
                $totalRow  = $dataEnd + 2;

                // ─── Panel Title (rows 1-3): merge + bold + center ───
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ─── Column Headers (row 5) ───
                $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD5F5E3'); // light green
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ─── All Borders ───
                $borderRange = "A{$headerRow}:{$lastCol}{$dataEnd}";
                $sheet->getStyle($borderRange)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ─── Data alignment ───
                $sheet->getStyle("A{$dataStart}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("C{$dataStart}:C{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$dataStart}:{$lastCol}{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // ─── Number format ───
                $numRange = "D{$dataStart}:{$lastCol}{$dataEnd}";
                $sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('#,##0');

                // ─── Zebra striping ───
                for ($r = $dataStart; $r <= $dataEnd; $r++) {
                    if (($r - $dataStart) % 2 === 1) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF2FCF5');
                    }
                }

                // ─── TOTAL Row ───
                if ($rowCount > 0) {
                    $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL BULANAN');
                    $sheet->setCellValue("D{$totalRow}", $totals['gaji']);
                    $sheet->setCellValue("E{$totalRow}", $totals['lembur']);
                    $sheet->setCellValue("F{$totalRow}", $totals['total']);
                    $sheet->setCellValue("G{$totalRow}", $totals['bpjs']);

                    $totalRange = "A{$totalRow}:{$lastCol}{$totalRow}";
                    $sheet->getStyle($totalRange)->getFont()->setBold(true);
                    $sheet->getStyle($totalRange)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE8E8E8');
                    $sheet->getStyle($totalRange)->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$totalRow}:{$lastCol}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("D{$totalRow}:{$lastCol}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // ─── Freeze Pane ───
                $sheet->freezePane("B{$dataStart}");
            },
        ];
    }
}
