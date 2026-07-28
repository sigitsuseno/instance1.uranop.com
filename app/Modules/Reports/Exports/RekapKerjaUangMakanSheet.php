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

class RekapKerjaUangMakanSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected array $rows;
    protected string $periodName;
    protected string $dateStart;
    protected string $dateEnd;
    protected int $rowCount = 0;
    protected array $totals;
    protected string $lastCol = 'H';

    public function __construct(array $rows, string $periodName, string $dateStart, string $dateEnd)
    {
        $this->rows       = $rows;
        $this->periodName = $periodName;
        $this->dateStart  = $dateStart;
        $this->dateEnd    = $dateEnd;

        $this->totals = [
            'uang_makan'    => array_sum(array_column($rows, 'uang_makan')),
            'lembur_sabtu'  => array_sum(array_column($rows, 'lembur_sabtu')),
            'lembur_minggu' => array_sum(array_column($rows, 'lembur_minggu')),
            'insentif'      => array_sum(array_column($rows, 'insentif')),
            'total'         => array_sum(array_column($rows, 'total')),
        ];
    }

    public function title(): string
    {
        return 'UANG MAKAN';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 28,  // BAGIAN
            'C' => 7,   // JML
            'D' => 16,  // UANG MAKAN
            'E' => 16,  // LEMBUR SABTU
            'F' => 16,  // LEMBUR MINGGU
            'G' => 14,  // INSENTIF
            'H' => 16,  // TOTAL
        ];
    }

    public function headings(): array
    {
        return [
            ['REKAP KERJA — SECTION C: UANG MAKAN'],
            ['PT KEMILAU UNGARAN SUKSES'],
            ["Periode: {$this->dateStart} s/d {$this->dateEnd}"],
            [''],
            [
                'No',
                'BAGIAN',
                'JML',
                'UANG MAKAN',
                'LEMBUR SABTU',
                'LEMBUR MINGGU',
                'INSENTIF',
                'TOTAL',
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
                (float) ($row['uang_makan'] ?? 0),
                (float) ($row['lembur_sabtu'] ?? 0),
                (float) ($row['lembur_minggu'] ?? 0),
                (float) ($row['insentif'] ?? 0),
                (float) ($row['total'] ?? 0),
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

                // ─── Column Headers (row 5): amber/gold bg ───
                $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFDEBD0'); // light amber
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
                            ->getStartColor()->setARGB('FFFFFBF0');
                    }
                }

                // ─── TOTAL Row ───
                if ($rowCount > 0) {
                    $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                    $sheet->setCellValue("A{$totalRow}", 'TOTAL BULANAN');
                    $sheet->setCellValue("D{$totalRow}", $totals['uang_makan']);
                    $sheet->setCellValue("E{$totalRow}", $totals['lembur_sabtu']);
                    $sheet->setCellValue("F{$totalRow}", $totals['lembur_minggu']);
                    $sheet->setCellValue("G{$totalRow}", $totals['insentif']);
                    $sheet->setCellValue("H{$totalRow}", $totals['total']);

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
