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

class LemburUangMakanDetailExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected $sections;
    protected $dates;
    protected $label;
    protected $companyName;

    /** Fixed columns before date columns */
    protected const FIXED_COLS = 9; // No, ID, Nama, Jabatan, L/P, Tj.MK, Tunjangan, Upah/Hari, Upah Lbr/Jam
    /** Sub-columns per date */
    protected const SUB_COLS = 7; // Kode, H/A, Upah/Hari, L/M, Lembur, Nominal Overtime, Uang Makan

    public function __construct($sections, $dates, $label, $companyName = 'PT. KEMILAU UNGARAN SUKSES')
    {
        $this->sections = $sections;
        $this->dates = $dates;
        $this->label = $label;
        $this->companyName = $companyName;
    }

    public function array(): array
    {
        // Not used; we build rows in registerEvents
        return [];
    }

    public function headings(): array
    {
        // We build manually in registerEvents
        return [];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,  // No
            'B' => 10, // ID
            'C' => 28, // Nama
            'D' => 22, // Jabatan
            'E' => 5,  // L/P
            'F' => 15, // Tj.MK
            'G' => 14, // Tunjangan
            'H' => 14, // Upah/Hari
            'I' => 14, // Upah Lbr/Jam
        ];

        $col = 'J';
        foreach ($this->dates as $dateStr) {
            $widths[$col] = 7;  $col = self::nextCol($col); // Kode
            $widths[$col] = 6;  $col = self::nextCol($col); // H/A
            $widths[$col] = 14; $col = self::nextCol($col); // Upah/Hari
            $widths[$col] = 8;  $col = self::nextCol($col); // L/M
            $widths[$col] = 8;  $col = self::nextCol($col); // Lembur
            $widths[$col] = 16; $col = self::nextCol($col); // Nominal OT
            $widths[$col] = 14; $col = self::nextCol($col); // Uang Makan
        }

        // End totals columns
        $widths[$col] = 16; $col = self::nextCol($col); // Total Hari Kerja
        $widths[$col] = 16;                 // Total Overtime
        $col = self::nextCol($col);
        $widths[$col] = 16;                 // Total Uang Makan
        $col = self::nextCol($col);
        $widths[$col] = 16;                 // Total Terima

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates)) + 4; // +4 for total columns
        $lastCol = self::colLetter($totalCols);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($totalCols, $lastCol) {
                $sheet = $event->sheet->getDelegate();

                Carbon::setLocale('id');

                $currentRow = 1;

                // --- Company name ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $this->companyName);
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- UNGARAN ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'UNGARAN');
                $sheet->getStyle("A{$currentRow}")->getFont()->setSize(10);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- Report title ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'RINCIAN GAJI & OVERTIME');
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- Period ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'PERIODE: ' . strtoupper($this->label));
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow += 2; // Skip one row

                // --- Build table header row 1 ---
                $headerStartRow = $currentRow;

                // Fixed headers (rowspan 2)
                $fixedHeaders = ['No', 'ID No', 'Nama', 'Bagian / Jabatan', 'L/P', 'Tj. MK', 'Tunjangan', 'Upah/Hari', 'Upah Lbr/Jam'];
                foreach ($fixedHeaders as $i => $h) {
                    $col = self::colLetter($i + 1);
                    $sheet->setCellValue("{$col}{$currentRow}", $h);
                    $sheet->mergeCells("{$col}{$currentRow}:{$col}" . ($currentRow + 1));
                }

                // Date group headers
                $dateStartCol = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $start = self::colLetter($dateStartCol);
                    $end = self::colLetter($dateStartCol + self::SUB_COLS - 1);
                    $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
                    $sheet->setCellValue("{$start}{$currentRow}", strtoupper($formatted));
                    $sheet->mergeCells("{$start}{$currentRow}:{$end}{$currentRow}");
                    $dateStartCol += self::SUB_COLS;
                }

                // Total headers
                $totStartCol = $dateStartCol;
                $sheet->setCellValue(self::colLetter($totStartCol) . "{$currentRow}", 'Total Hari Kerja');
                $sheet->mergeCells(self::colLetter($totStartCol) . "{$currentRow}:" . self::colLetter($totStartCol) . ($currentRow + 1));
                $totStartCol++;
                $sheet->setCellValue(self::colLetter($totStartCol) . "{$currentRow}", 'Total Overtime');
                $sheet->mergeCells(self::colLetter($totStartCol) . "{$currentRow}:" . self::colLetter($totStartCol) . ($currentRow + 1));
                $totStartCol++;
                $sheet->setCellValue(self::colLetter($totStartCol) . "{$currentRow}", 'Total Uang Makan');
                $sheet->mergeCells(self::colLetter($totStartCol) . "{$currentRow}:" . self::colLetter($totStartCol) . ($currentRow + 1));
                $totStartCol++;
                $sheet->setCellValue(self::colLetter($totStartCol) . "{$currentRow}", 'Total Terima');
                $sheet->mergeCells(self::colLetter($totStartCol) . "{$currentRow}:" . self::colLetter($totStartCol) . ($currentRow + 1));

                $currentRow++;

                // --- Build table header row 2 (sub-headers) ---
                $dateStartCol = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $subHeaders = ['Kode', 'H/A', 'Upah/Hari', 'L/M', 'Lembur', 'Nom.OT', 'U.Makan'];
                    foreach ($subHeaders as $sh) {
                        $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", $sh);
                        $dateStartCol++;
                    }
                }

                // Style headers
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")->getFont()->setBold(true)->setSize(8);
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Blue tint for date headers
                $firstDateCol = self::colLetter(self::FIXED_COLS + 1);
                $lastDateCol = self::colLetter(self::FIXED_COLS + (self::SUB_COLS * count($this->dates)));
                $sheet->getStyle("{$firstDateCol}{$headerStartRow}:{$lastDateCol}{$headerStartRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');

                $currentRow++;

                // --- Data rows ---
                $dataStartRow = $currentRow;
                $grandTotalRow = null;

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

                        // Fixed columns
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $counter); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['id']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['name']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['jabatan']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['gender']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['tj_mk'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['tunjangan'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['upah_per_hari'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['upah_lembur_per_jam'] ?? 0); $col++;

                        // Date sub-columns
                        $days = $item['days'] ?? [];
                        foreach ($this->dates as $dateStr) {
                            $d = $days[$dateStr] ?? null;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $d['kode'] ?? ''); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $d['ha'] ?? ''); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['upah_per_hari'] ?? 0) > 0 ? $d['upah_per_hari'] : 0); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['lm'] ?? 0) > 0 ? $d['lm'] : ''); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['lembur'] ?? 0) > 0 ? $d['lembur'] : ''); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['overtime_nominal'] ?? 0) > 0 ? $d['overtime_nominal'] : 0); $col++;
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ($d['uang_makan'] ?? 0) > 0 ? $d['uang_makan'] : 0); $col++;
                        }

                        // Totals
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_hari_kerja'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_overtime'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_uang_makan'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_terima'] ?? 0);

                        $currentRow++;
                    }

                    // Section totals
                    if (!empty($section['totals']) && $section['totals']['count'] > 0) {
                        $totals = $section['totals'];
                        $col = 1;

                        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                        $sheet->setCellValue("A{$currentRow}", 'TOTAL ' . $section['label']);
                        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
                        $col = 6;

                        // Skip fixed value columns, just put totals
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ''); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ''); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ''); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", ''); $col++;

                        // Skip date columns
                        $col += self::SUB_COLS * count($this->dates);

                        // Totals
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $totals['total_hari_kerja']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $totals['total_overtime']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $totals['total_uang_makan']); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $totals['total_terima']);

                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCFCE7');
                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")->getFont()->setBold(true);
                        $currentRow++;
                    }

                    $currentRow++; // Blank row between sections
                }

                $grandTotalRow = $currentRow + 1;

                // --- Borders for data area ---
                $lastDataRow = $currentRow - 2;
                $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$lastDataRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // --- Number formats ---
                $numberCols = [];
                // F-I: TJ.MK, Tunjangan, Upah/Hari, Upah Lbr/Jam
                for ($c = 6; $c <= 9; $c++) {
                    $numberCols[] = self::colLetter($c);
                }
                // Per-date: Upah/Hari (offset 2), Nominal OT (offset 5), Uang Makan (offset 6)
                for ($i = 0; $i < count($this->dates); $i++) {
                    $base = self::FIXED_COLS + ($i * self::SUB_COLS);
                    $numberCols[] = self::colLetter($base + 3); // Upah/Hari
                    $numberCols[] = self::colLetter($base + 6); // Nominal OT
                    $numberCols[] = self::colLetter($base + 7); // Uang Makan
                }
                // Totals
                $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                for ($c = 1; $c <= 4; $c++) {
                    $numberCols[] = self::colLetter($totBase + $c);
                }

                foreach ($numberCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // --- Alignment ---
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Freeze pane
                $sheet->freezePane('D' . ($headerStartRow + 2));
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
