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
use Carbon\Carbon;

class LemburUangMakanResumeExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected $sections;
    protected $dates;
    protected $label;
    protected $companyName;

    /** Fixed columns before date columns */
    protected const FIXED_COLS = 4; // No, Bagian, L, P
    /** Sub-columns per date */
    protected const SUB_COLS = 3; // Hari Kerja, Overtime, Uang Makan

    public function __construct($sections, $dates, $label, $companyName = 'PT. KEMILAU UNGARAN SUKSES')
    {
        $this->sections = $sections;
        $this->dates = $dates;
        $this->label = $label;
        $this->companyName = $companyName;
    }

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,  // No
            'B' => 26, // Bagian
            'C' => 6,  // L
            'D' => 6,  // P
        ];

        $col = 'E';
        foreach ($this->dates as $dateStr) {
            $widths[$col] = 16; $col = self::nextCol($col); // Hari Kerja
            $widths[$col] = 16; $col = self::nextCol($col); // Overtime
            $widths[$col] = 14; $col = self::nextCol($col); // Uang Makan
        }

        $widths[$col] = 18; $col = self::nextCol($col); // Total Hari Kerja
        $widths[$col] = 18; $col = self::nextCol($col); // Total Overtime
        $widths[$col] = 16; $col = self::nextCol($col); // Total Uang Makan
        $widths[$col] = 18;                 // Total Terima

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates)) + 4; // +4 total cols
        $lastCol = self::colLetter($totalCols);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($totalCols, $lastCol) {
                $sheet = $event->sheet->getDelegate();
                Carbon::setLocale('id');

                $currentRow = 1;

                // Company name
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $this->companyName);
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // Location
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'UNGARAN');
                $sheet->getStyle("A{$currentRow}")->getFont()->setSize(10);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // Report title
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'RESUME GAJI & OVERTIME');
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // Period
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'PERIODE: ' . strtoupper($this->label));
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow += 2;

                // --- Table headers ---
                $headerStartRow = $currentRow;

                // Row 1: NO, BAGIAN, JML KARYAWAN(colspan2), dates..., totals
                $sheet->setCellValue("A{$currentRow}", 'No');
                $sheet->mergeCells("A{$currentRow}:A" . ($currentRow + 1));
                $sheet->setCellValue("B{$currentRow}", 'Bagian');
                $sheet->mergeCells("B{$currentRow}:B" . ($currentRow + 1));
                $sheet->setCellValue("C{$currentRow}", 'Jml Karyawan');
                $sheet->mergeCells("C{$currentRow}:D{$currentRow}");

                $dateStartCol = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $start = self::colLetter($dateStartCol);
                    $end = self::colLetter($dateStartCol + self::SUB_COLS - 1);
                    $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
                    $sheet->setCellValue("{$start}{$currentRow}", strtoupper($formatted));
                    $sheet->mergeCells("{$start}{$currentRow}:{$end}{$currentRow}");
                    $dateStartCol += self::SUB_COLS;
                }

                // Totals
                $totCol = $dateStartCol;
                $sheet->setCellValue(self::colLetter($totCol) . "{$currentRow}", 'Total Hari Kerja');
                $sheet->mergeCells(self::colLetter($totCol) . "{$currentRow}:" . self::colLetter($totCol) . ($currentRow + 1));
                $totCol++;
                $sheet->setCellValue(self::colLetter($totCol) . "{$currentRow}", 'Total Overtime');
                $sheet->mergeCells(self::colLetter($totCol) . "{$currentRow}:" . self::colLetter($totCol) . ($currentRow + 1));
                $totCol++;
                $sheet->setCellValue(self::colLetter($totCol) . "{$currentRow}", 'Total Uang Makan');
                $sheet->mergeCells(self::colLetter($totCol) . "{$currentRow}:" . self::colLetter($totCol) . ($currentRow + 1));
                $totCol++;
                $sheet->setCellValue(self::colLetter($totCol) . "{$currentRow}", 'Total Terima');
                $sheet->mergeCells(self::colLetter($totCol) . "{$currentRow}:" . self::colLetter($totCol) . ($currentRow + 1));

                $currentRow++;

                // Row 2: L, P, date sub-headers
                $sheet->setCellValue("C{$currentRow}", 'L');
                $sheet->setCellValue("D{$currentRow}", 'P');

                $dateStartCol = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", 'Hari Kerja'); $dateStartCol++;
                    $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", 'Overtime'); $dateStartCol++;
                    $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", 'U.Makan'); $dateStartCol++;
                }

                // Style headers
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")->getFont()->setBold(true)->setSize(8);
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Blue tint for date headers row
                $firstDateCol = self::colLetter(self::FIXED_COLS + 1);
                $lastDateCol = self::colLetter(self::FIXED_COLS + (self::SUB_COLS * count($this->dates)));
                $sheet->getStyle("{$firstDateCol}{$headerStartRow}:{$lastDateCol}{$headerStartRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');

                $currentRow++;

                // --- Data rows ---
                $dataStartRow = $currentRow;

                foreach ($this->sections as $section) {
                    // Section header
                    $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", $section['label']);
                    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(10);
                    $sheet->getStyle("A{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
                    $currentRow++;

                    $counter = 0;
                    foreach ($section['data'] as $item) {
                        $counter++;
                        $col = 1;

                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $counter); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['bagian']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['l']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['p']); $col++;

                        // Date sub-columns
                        $days = $item['days'] ?? [];
                        foreach ($this->dates as $dateStr) {
                            $d = $days[$dateStr] ?? null;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['hari_kerja'] ?? 0) > 0 ? $d['hari_kerja'] : 0); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['overtime'] ?? 0) > 0 ? $d['overtime'] : 0); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['uang_makan'] ?? 0) > 0 ? $d['uang_makan'] : 0); $col++;
                        }

                        // Totals
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_hari_kerja']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_overtime']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_uang_makan']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_terima']);

                        $currentRow++;
                    }
                    $currentRow++; // Blank row
                }

                $lastDataRow = $currentRow - 2;

                // --- Borders ---
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$lastDataRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // --- Number formats ---
                $numberBaseCols = [];
                $dateStartCol = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $numberBaseCols[] = self::colLetter($dateStartCol);     // Hari Kerja
                    $numberBaseCols[] = self::colLetter($dateStartCol + 1); // Overtime
                    $numberBaseCols[] = self::colLetter($dateStartCol + 2); // Uang Makan
                    $dateStartCol += self::SUB_COLS;
                }
                $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                for ($c = 1; $c <= 4; $c++) {
                    $numberBaseCols[] = self::colLetter($totBase + $c);
                }

                foreach ($numberBaseCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // --- Alignment ---
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$dataStartRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Freeze pane
                $sheet->freezePane('C' . ($headerStartRow + 2));
            },
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────

    protected static function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = (int)($index / 26);
        }
        return $letter;
    }

    protected static function nextCol(string $col): string
    {
        return self::colLetter(self::colIndex($col) + 1);
    }

    protected static function colIndex(string $col): int
    {
        $index = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($col[$i]) - 64);
        }
        return $index;
    }
}
