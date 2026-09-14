<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapGajiExport extends DefaultValueBinder implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents, WithCustomValueBinder, WithTitle
{
    protected array $rows;
    protected string $periodName;
    protected int $year;
    protected int $rowCount = 0;
    protected array $totals;

    // Column layout: A=No, B=NAMA, C=ACCOUNT NO, D=STATUS, E=L/P,
    //                F=GAJI, G=TOTAL GAJI, H=BPJS TK, I=BPJS KESEHATAN, J=UM
    protected string $lastCol = 'J';

    public function __construct(array $rows, string $periodName, string $dateStart)
    {
        $this->rows = $rows;
        $this->periodName = $periodName;
        $this->year = (int) date('Y', strtotime($dateStart));

        $this->totals = [
            'gaji'       => array_sum(array_column($rows, 'gaji')),
            'total_gaji' => array_sum(array_column($rows, 'total_gaji')),
            'bpjs_tk'    => array_sum(array_column($rows, 'bpjs_tk')),
            'bpjs_ks'    => array_sum(array_column($rows, 'bpjs_ks')),
            'uang_makan' => array_sum(array_column($rows, 'uang_makan')),
        ];
    }

    /**
     * Force kolom C (ACCOUNT NO) selalu ditulis sebagai teks,
     * supaya Excel tidak menampilkan notasi ilmiah E+12
     * dan nol di depan / digit panjang tetap utuh.
     */
    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'C') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'Rekap Gaji';
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->rows as $i => $row) {
            $data[] = [
                $i + 1, // No
                $row['name']         ?? '-',
                $row['account_no']   ?? '-',
                $row['status_label'] ?? '-',
                $row['gender']       ?? '-',
                (float) ($row['gaji']       ?? 0),
                (float) ($row['total_gaji'] ?? 0),
                (float) ($row['bpjs_tk']    ?? 0),
                (float) ($row['bpjs_ks']    ?? 0),
                (float) ($row['uang_makan'] ?? 0),
            ];
            $this->rowCount++;
        }

        return $data;
    }

    public function headings(): array
    {
        $periodUpper = strtoupper($this->periodName);

        return [
            ['REKAPAN GAJI PT KEMILAU UNGARAN SUKSES'],
            ['KARANGJATI-PRODUKSI'],
            ["TAHUN {$this->year}"],
            [''], // empty spacer row
            [
                'No', 'NAMA', 'ACCOUNT NO', 'STATUS', 'L/P',
                "GAJI\n{$periodUpper}",
                'TOTAL GAJI',
                "BPJS TK\n(JKK,JKM)",
                'BPJS KESEHATAN',
                'UM',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 25,  // NAMA
            'C' => 16,  // ACCOUNT NO
            'D' => 10,  // STATUS
            'E' => 6,   // L/P
            'F' => 16,  // GAJI
            'G' => 16,  // TOTAL GAJI
            'H' => 20,  // BPJS TK
            'I' => 16,  // BPJS KESEHATAN
            'J' => 14,  // UM
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = $this->lastCol;
        $rowCount = &$this->rowCount;
        $totals = $this->totals;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, &$rowCount, $totals) {
                $sheet = $event->sheet->getDelegate();

                $headerRow  = 5;   // row 5 = column headers
                $dataStart  = 6;   // first data row
                $dataEnd    = $dataStart + $rowCount - 1;

                // ─── Panel Title (rows 1-3): merge + bold + center ───
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ─── Column Headers (row 5): bg color, bold, wrap, center ───
                $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD6EAF8'); // light blue
                $sheet->getRowDimension($headerRow)->setRowHeight(35);

                // ─── All Borders: header through last data row ───
                $borderRange = "A{$headerRow}:{$lastCol}{$dataEnd}";
                $sheet->getStyle($borderRange)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ─── Data alignment ───
                // No column: center
                $sheet->getStyle("A{$dataStart}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // NAMA: left
                $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                // ACCOUNT NO, STATUS, L/P: center
                $sheet->getStyle("C{$dataStart}:E{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // ACCOUNT NO: paksa format teks agar tidak jadi notasi ilmiah
                $sheet->getStyle("C{$dataStart}:C{$dataEnd}")->getNumberFormat()->setFormatCode('@');
                // GAJI..UM: right
                $sheet->getStyle("F{$dataStart}:{$lastCol}{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // ─── Number format: #,##0 ───
                $numRange = "F{$dataStart}:{$lastCol}{$dataEnd}";
                $sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('#,##0');

                // ─── Zebra striping ───
                for ($r = $dataStart; $r <= $dataEnd; $r++) {
                    if (($r - $dataStart) % 2 === 1) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF2F6FC');
                    }
                }

                // ─── TOTAL Row ───
                $totalRow = $dataEnd + 2; // 1 empty row gap

                // Merge No-TOTAL label across A-E
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue("F{$totalRow}", $totals['gaji']);
                $sheet->setCellValue("G{$totalRow}", $totals['total_gaji']);
                $sheet->setCellValue("H{$totalRow}", $totals['bpjs_tk']);
                $sheet->setCellValue("I{$totalRow}", $totals['bpjs_ks']);
                $sheet->setCellValue("J{$totalRow}", $totals['uang_makan']);

                // Style TOTAL row
                $totalRange = "A{$totalRow}:{$lastCol}{$totalRow}";
                $sheet->getStyle($totalRange)->getFont()->setBold(true);
                $sheet->getStyle($totalRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8E8E8');
                $sheet->getStyle($totalRange)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$totalRow}:{$lastCol}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("F{$totalRow}:{$lastCol}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

                // ─── Freeze Pane ───
                $sheet->freezePane("B{$dataStart}");
            },
        ];
    }
}
