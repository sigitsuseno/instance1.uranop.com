<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Carbon\Carbon;

class LemburUangMakanResumeExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    protected $sections;
    protected $dates;
    protected $label;
    protected $companyName;

    protected const FIXED_COLS = 4; // No, Bagian, L, P
    protected const SUB_COLS = 3; // Hari Kerja, Overtime, Uang Makan
    protected const TOT_COLS = 4; // Total Hari Kerja, Total Overtime, Total Uang Makan, Total Terima

    // ── Corporate colors ──────────────────────────────────────────
    protected const COLOR_PRIMARY    = '1F4E79';
    protected const COLOR_ACCENT     = '2E75B6';
    protected const COLOR_HEADER_BG  = 'D6E4F0';
    protected const COLOR_DATE_BG    = 'E9F0F8';
    protected const COLOR_SECTION_BG = 'F2F7FB';
    protected const COLOR_TOTAL_BG   = 'B4D6F0';
    protected const COLOR_GRAND_BG   = '1F4E79';
    protected const COLOR_ALT_ROW    = 'F8F9FA';
    protected const COLOR_WHITE      = 'FFFFFF';

    public function __construct($sections, $dates, $label, $companyName = 'PT. KEMILAU UNGARAN SUKSES')
    {
        $this->sections = $sections;
        $this->dates = $dates;
        $this->label = $label;
        $this->companyName = $companyName;
    }

    public function title(): string
    {
        return 'Resume';
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
            'A' => 5,
            'B' => 28,
            'C' => 6,
            'D' => 6,
        ];

        $col = 'E';
        foreach ($this->dates as $dateStr) {
            $widths[$col] = 16; $col = self::nextCol($col);
            $widths[$col] = 16; $col = self::nextCol($col);
            $widths[$col] = 15; $col = self::nextCol($col);
        }

        $widths[$col] = 18; $col = self::nextCol($col);
        $widths[$col] = 18; $col = self::nextCol($col);
        $widths[$col] = 16; $col = self::nextCol($col);
        $widths[$col] = 18;

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates)) + self::TOT_COLS;
        $lastCol = self::colLetter($totalCols);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($totalCols, $lastCol) {
                $sheet = $event->sheet->getDelegate();
                Carbon::setLocale('id');

                // ── Page setup ──────────────────────────────────────
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setRight(0.3);
                $sheet->getPageMargins()->setBottom(0.5);
                $sheet->getPageMargins()->setLeft(0.3);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 6);

                $sheet->getDefaultRowDimension()->setRowHeight(15);

                $currentRow = 1;

                // ══════════════════════════════════════════════════════
                // HEADER AREA
                // ══════════════════════════════════════════════════════

                // Row 1: Company name
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $this->companyName);
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(16)->setColor(new Color(self::COLOR_PRIMARY));
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($currentRow)->setRowHeight(28);
                $currentRow++;

                // Row 2: Location
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'UNGARAN');
                $sheet->getStyle("A{$currentRow}")->getFont()->setSize(10)->setColor(new Color('666666'));
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // Row 3: Report title
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'RESUME GAJI & LEMBUR');
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(13)->setColor(new Color(self::COLOR_PRIMARY));
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($currentRow)->setRowHeight(22);
                $currentRow++;

                // Row 4: Period
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'PERIODE: ' . strtoupper($this->label));
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11)->setColor(new Color(self::COLOR_ACCENT));
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // Row 5: Separator
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->getRowDimension($currentRow)->setRowHeight(4);
                $currentRow++;

                // Row 6: Spacer
                $currentRow++;

                // ══════════════════════════════════════════════════════
                // TABLE DATA (per section)
                // ══════════════════════════════════════════════════════

                $dataStartRow = $currentRow;
                $lastDataRow = $currentRow;
                $currentIsAlt = false;

                // ── Grand total accumulators (across all sections) ────
                $grandL = 0;
                $grandP = 0;
                $grandDays = [];
                foreach ($this->dates as $dateStr) {
                    $grandDays[$dateStr] = ['hari_kerja' => 0, 'overtime' => 0, 'uang_makan' => 0];
                }
                $grandTotalHariKerja = 0;
                $grandTotalOvertime = 0;
                $grandTotalUangMakan = 0;
                $grandTotalTerima = 0;

                foreach ($this->sections as $section) {
                    // ── Section label ───────────────────────────────
                    $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", $section['label']);
                    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(10)->setColor(new Color(self::COLOR_PRIMARY));
                    $sheet->getStyle("A{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_SECTION_BG);
                    $sheet->getRowDimension($currentRow)->setRowHeight(20);
                    $currentRow++;

                    // ── Header row 1 ────────────────────────────────
                    $headerStartRow = $currentRow;

                    $sheet->setCellValue("A{$currentRow}", 'No');
                    $sheet->mergeCells("A{$currentRow}:A" . ($currentRow + 1));
                    $sheet->setCellValue("B{$currentRow}", 'Bagian / Jabatan');
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
                    $totalHeaders = ['Total\nHari Kerja', 'Total\nOvertime', 'Total\nU.Makan', 'Total\nTerima'];
                    foreach ($totalHeaders as $th) {
                        $sheet->setCellValue(self::colLetter($totCol) . "{$currentRow}", str_replace('\n', "\n", $th));
                        $sheet->getStyle(self::colLetter($totCol) . "{$currentRow}")->getAlignment()->setWrapText(true);
                        $sheet->mergeCells(self::colLetter($totCol) . "{$currentRow}:" . self::colLetter($totCol) . ($currentRow + 1));
                        $totCol++;
                    }

                    $currentRow++;

                    // ── Header row 2 ────────────────────────────────
                    $sheet->setCellValue("C{$currentRow}", 'L');
                    $sheet->setCellValue("D{$currentRow}", 'P');

                    $dateStartCol = self::FIXED_COLS + 1;
                    foreach ($this->dates as $dateStr) {
                        $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", 'Hari Kerja'); $dateStartCol++;
                        $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", 'Overtime'); $dateStartCol++;
                        $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", 'U.Makan'); $dateStartCol++;
                    }

                    // ── Header styling ──────────────────────────────
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getFont()->setBold(true)->setSize(8)->setColor(new Color(self::COLOR_PRIMARY));
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_HEADER_BG);
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getRowDimension($headerStartRow)->setRowHeight(18);
                    $sheet->getRowDimension($currentRow)->setRowHeight(16);

                    $firstDateCol = self::colLetter(self::FIXED_COLS + 1);
                    $lastDateCol = self::colLetter(self::FIXED_COLS + (self::SUB_COLS * count($this->dates)));
                    $sheet->getStyle("{$firstDateCol}{$headerStartRow}:{$lastDateCol}{$headerStartRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_DATE_BG);

                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $currentRow++;

                    // ── Data rows ──────────────────────────────────
                    $sectionDataStart = $currentRow;
                    $counter = 0;

                    // Accumulators for column sums
                    $sumL = 0;
                    $sumP = 0;
                    $sumDays = [];
                    foreach ($this->dates as $dateStr) {
                        $sumDays[$dateStr] = ['hari_kerja' => 0, 'overtime' => 0, 'uang_makan' => 0];
                    }
                    $sumTotalHariKerja = 0;
                    $sumTotalOvertime = 0;
                    $sumTotalUangMakan = 0;
                    $sumTotalTerima = 0;

                    foreach ($section['data'] as $item) {
                        $counter++;
                        $colIdx = 1;

                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $counter); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['bagian']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['l']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['p']); $colIdx++;

                        $sumL += (int)($item['l'] ?? 0);
                        $sumP += (int)($item['p'] ?? 0);

                        $days = $item['days'] ?? [];
                        foreach ($this->dates as $dateStr) {
                            $d = $days[$dateStr] ?? null;
                            $valHk = ($d['hari_kerja'] ?? 0) > 0 ? $d['hari_kerja'] : 0;
                            $valOt = ($d['overtime'] ?? 0) > 0 ? $d['overtime'] : 0;
                            $valUm = ($d['uang_makan'] ?? 0) > 0 ? $d['uang_makan'] : 0;
                            $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valHk); $colIdx++;
                            $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valOt); $colIdx++;
                            $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valUm); $colIdx++;
                            $sumDays[$dateStr]['hari_kerja'] += $valHk;
                            $sumDays[$dateStr]['overtime'] += $valOt;
                            $sumDays[$dateStr]['uang_makan'] += $valUm;
                        }

                        // Totals
                        $valThk = (int)($item['total_hari_kerja'] ?? 0);
                        $valTot = (int)($item['total_overtime'] ?? 0);
                        $valTum = (int)($item['total_uang_makan'] ?? 0);
                        $valTtr = (int)($item['total_terima'] ?? 0);
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valThk); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valTot); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valTum); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $valTtr);
                        $sumTotalHariKerja += $valThk;
                        $sumTotalOvertime += $valTot;
                        $sumTotalUangMakan += $valTum;
                        $sumTotalTerima += $valTtr;

                        // Alternating rows
                        if ($currentIsAlt) {
                            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ALT_ROW);
                        }
                        $currentIsAlt = !$currentIsAlt;
                        $currentRow++;
                    }

                    // ── SUM / TOTAL ROW ─────────────────────────────
                    $sumRow = $currentRow;

                    // Merge No + Bagian columns for "TOTAL" label
                    $sheet->mergeCells("A{$sumRow}:B{$sumRow}");
                    $sheet->setCellValue("A{$sumRow}", 'TOTAL');

                    $sheet->setCellValue(self::colLetter(3) . "{$sumRow}", $sumL);
                    $sheet->setCellValue(self::colLetter(4) . "{$sumRow}", $sumP);

                    // After merged A-B + C + D = 4 fixed cols, then date columns: 3 per date
                    $sumColStart = self::FIXED_COLS + 1;
                    foreach ($this->dates as $dateStr) {
                        $sheet->setCellValue(self::colLetter($sumColStart) . "{$sumRow}", $sumDays[$dateStr]['hari_kerja']);
                        $sheet->setCellValue(self::colLetter($sumColStart + 1) . "{$sumRow}", $sumDays[$dateStr]['overtime']);
                        $sheet->setCellValue(self::colLetter($sumColStart + 2) . "{$sumRow}", $sumDays[$dateStr]['uang_makan']);
                        $sumColStart += self::SUB_COLS;
                    }

                    // Total columns
                    $sheet->setCellValue(self::colLetter($sumColStart) . "{$sumRow}", $sumTotalHariKerja); $sumColStart++;
                    $sheet->setCellValue(self::colLetter($sumColStart) . "{$sumRow}", $sumTotalOvertime); $sumColStart++;
                    $sheet->setCellValue(self::colLetter($sumColStart) . "{$sumRow}", $sumTotalUangMakan); $sumColStart++;
                    $sheet->setCellValue(self::colLetter($sumColStart) . "{$sumRow}", $sumTotalTerima);

                    // Style the sum row
                    $sheet->getStyle("A{$sumRow}:{$lastCol}{$sumRow}")
                        ->getFont()->setBold(true)->setSize(9)->setColor(new Color(self::COLOR_PRIMARY));
                    $sheet->getStyle("A{$sumRow}:{$lastCol}{$sumRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_TOTAL_BG);
                    $sheet->getStyle("A{$sumRow}:{$lastCol}{$sumRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("A{$sumRow}:B{$sumRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension($sumRow)->setRowHeight(18);

                    // Accumulate into grand totals
                    $grandL += $sumL;
                    $grandP += $sumP;
                    foreach ($this->dates as $dateStr) {
                        $grandDays[$dateStr]['hari_kerja'] += $sumDays[$dateStr]['hari_kerja'];
                        $grandDays[$dateStr]['overtime'] += $sumDays[$dateStr]['overtime'];
                        $grandDays[$dateStr]['uang_makan'] += $sumDays[$dateStr]['uang_makan'];
                    }
                    $grandTotalHariKerja += $sumTotalHariKerja;
                    $grandTotalOvertime += $sumTotalOvertime;
                    $grandTotalUangMakan += $sumTotalUangMakan;
                    $grandTotalTerima += $sumTotalTerima;

                    $currentRow++; // Move past sum row
                    $currentRow++; // Blank row
                }

                $lastDataRow = $currentRow - 2;

                // ══════════════════════════════════════════════════════
                // GRAND TOTAL ROW
                // ══════════════════════════════════════════════════════
                $gtRow = $currentRow;

                // Merge No + Bagian columns for label
                $sheet->mergeCells("A{$gtRow}:B{$gtRow}");
                $sheet->setCellValue("A{$gtRow}", 'GRAND TOTAL');

                $sheet->setCellValue(self::colLetter(3) . "{$gtRow}", $grandL);
                $sheet->setCellValue(self::colLetter(4) . "{$gtRow}", $grandP);

                $gtColStart = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $sheet->setCellValue(self::colLetter($gtColStart) . "{$gtRow}", $grandDays[$dateStr]['hari_kerja']);
                    $sheet->setCellValue(self::colLetter($gtColStart + 1) . "{$gtRow}", $grandDays[$dateStr]['overtime']);
                    $sheet->setCellValue(self::colLetter($gtColStart + 2) . "{$gtRow}", $grandDays[$dateStr]['uang_makan']);
                    $gtColStart += self::SUB_COLS;
                }

                $sheet->setCellValue(self::colLetter($gtColStart) . "{$gtRow}", $grandTotalHariKerja); $gtColStart++;
                $sheet->setCellValue(self::colLetter($gtColStart) . "{$gtRow}", $grandTotalOvertime); $gtColStart++;
                $sheet->setCellValue(self::colLetter($gtColStart) . "{$gtRow}", $grandTotalUangMakan); $gtColStart++;
                $sheet->setCellValue(self::colLetter($gtColStart) . "{$gtRow}", $grandTotalTerima);

                // Style the grand total row
                $sheet->getStyle("A{$gtRow}:{$lastCol}{$gtRow}")
                    ->getFont()->setBold(true)->setSize(10)->setColor(new Color(self::COLOR_WHITE));
                $sheet->getStyle("A{$gtRow}:{$lastCol}{$gtRow}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_GRAND_BG);
                $sheet->getStyle("A{$gtRow}:{$lastCol}{$gtRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A{$gtRow}:B{$gtRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($gtRow)->setRowHeight(22);

                // Thick top border on grand total
                $sheet->getStyle("A{$gtRow}:{$lastCol}{$gtRow}")
                    ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK)
                    ->getColor()->setARGB(self::COLOR_GRAND_BG);

                $lastDataRow = $gtRow;

                // ══════════════════════════════════════════════════════
                // BORDERS
                // ══════════════════════════════════════════════════════
                $borderEndRow = max($lastDataRow, $dataStartRow);
                if ($borderEndRow >= $dataStartRow) {
                    $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$borderEndRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$borderEndRow}")
                        ->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
                }

                // ══════════════════════════════════════════════════════
                // NUMBER FORMATS
                // ══════════════════════════════════════════════════════
                $numberCols = [];
                $dateStartCol = self::FIXED_COLS + 1;
                foreach ($this->dates as $dateStr) {
                    $numberCols[] = self::colLetter($dateStartCol);
                    $numberCols[] = self::colLetter($dateStartCol + 1);
                    $numberCols[] = self::colLetter($dateStartCol + 2);
                    $dateStartCol += self::SUB_COLS;
                }
                $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                for ($c = 1; $c <= self::TOT_COLS; $c++) {
                    $numberCols[] = self::colLetter($totBase + $c);
                }

                foreach ($numberCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }

                // ══════════════════════════════════════════════════════
                // ALIGNMENT
                // ══════════════════════════════════════════════════════
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$dataStartRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                foreach ($numberCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ══════════════════════════════════════════════════════
                // FOOTER
                // ══════════════════════════════════════════════════════
                $currentRow = $lastDataRow + 2;
                $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'Dicetak: ' . Carbon::now()->translatedFormat('d F Y H:i'));
                $sheet->getStyle("A{$currentRow}")->getFont()->setSize(8)->setColor(new Color('999999'));

                // ══════════════════════════════════════════════════════
                // FREEZE PANE
                // ══════════════════════════════════════════════════════
                $sheet->freezePane('E' . ($headerStartRow + 1));
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
