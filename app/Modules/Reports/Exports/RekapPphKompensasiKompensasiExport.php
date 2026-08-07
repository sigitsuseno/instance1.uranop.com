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
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapPphKompensasiKompensasiExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected array $rows;
    protected string $periodName;
    protected string $dateStart;
    protected string $dateEnd;
    protected int $rowCount = 0;
    protected array $totals;

    // A=No, B=NAMA BANK, C=PERHITUNGAN PPH, D=NIK, E=NIK TKU, F=STATUS, G=TANGGAL BAYAR, H=TOTAL KOMPENSASI
    protected string $lastCol = 'H';

    public function __construct(array $rows, string $periodName, string $dateStart, string $dateEnd)
    {
        $this->rows       = $rows;
        $this->periodName = $periodName;
        $this->dateStart  = $dateStart;
        $this->dateEnd    = $dateEnd;

        $this->totals = [
            'total_kompensasi' => array_sum(array_column($rows, 'total_kompensasi')),
        ];
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->rows as $i => $row) {
            $data[] = [
                $i + 1,
                $row['name']             ?? '-',
                $row['name']             ?? '-',
                $row['nik']              ?? '-',
                $row['nik_tku']          ?? '-',
                $row['status_label']     ?? '-',
                $row['paid_at']          ?? '-',
                (float) ($row['total_kompensasi'] ?? 0),
            ];
            $this->rowCount++;
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            ['KOMPENSASI KONTRAK'],
            ['PT KEMILAU UNGARAN SUKSES'],
            ["PERIODE: {$this->dateStart} - {$this->dateEnd}"],
            [''],
            [
                'No', 'NAMA BANK', 'PERHITUNGAN PPH (NAMA KTP)', 'NIK', 'NIK TKU',
                'STATUS', 'TANGGAL BAYAR', 'TOTAL KOMPENSASI',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 25,  // NAMA BANK
            'C' => 28,  // PERHITUNGAN PPH
            'D' => 16,  // NIK
            'E' => 18,  // NIK TKU
            'F' => 10,  // STATUS
            'G' => 14,  // TANGGAL BAYAR
            'H' => 18,  // TOTAL KOMPENSASI
        ];
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
        $rows     = $this->rows;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, &$rowCount, $totals, $rows) {
                $sheet = $event->sheet->getDelegate();

                $headerRow = 5;
                $dataStart = 6;
                $dataEnd   = $dataStart + $rowCount - 1;

                // ─── Title rows ───
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->getFont()->setSize(10);
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ─── Column Headers ───
                $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
                $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle($headerRange)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle($headerRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFFFE0B2'); // light amber
                $sheet->getRowDimension($headerRow)->setRowHeight(35);

                // ─── Borders ───
                $borderRange = "A{$headerRow}:{$lastCol}{$dataEnd}";
                $sheet->getStyle($borderRange)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ─── Alignment ───
                $sheet->getStyle("A{$dataStart}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("C{$dataStart}:C{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("D{$dataStart}:E{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$dataStart}:F{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$dataStart}:G{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$dataStart}:H{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // ─── NIK & NIK TKU sebagai teks (hindari floating-point truncation 16-digit) ───
                $sheet->getStyle("D{$dataStart}:E{$dataEnd}")->getNumberFormat()->setFormatCode('@');
                foreach ($rows as $i => $row) {
                    $r = $dataStart + $i;
                    $sheet->setCellValueExplicit("D{$r}", $row['nik'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("E{$r}", $row['nik_tku'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

                // ─── Zebra ───
                for ($r = $dataStart; $r <= $dataEnd; $r++) {
                    if (($r - $dataStart) % 2 === 1) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFFFF8E1');
                    }
                }

                // ─── TOTAL Row ───
                $totalRow = $dataEnd + 2;

                $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue("H{$totalRow}", $totals['total_kompensasi']);

                $totalRange = "A{$totalRow}:{$lastCol}{$totalRow}";
                $sheet->getStyle($totalRange)->getFont()->setBold(true);
                $sheet->getStyle($totalRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8E8E8');
                $sheet->getStyle($totalRange)->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');

                // ─── Freeze ───
                $sheet->freezePane("B{$dataStart}");
            },
        ];
    }
}