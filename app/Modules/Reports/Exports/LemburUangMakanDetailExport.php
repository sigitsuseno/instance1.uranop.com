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
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Carbon\Carbon;

class LemburUangMakanDetailExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    protected $sections;
    protected $dates;
    protected $label;
    protected $companyName;

    /** Fixed columns before date columns */
    protected const FIXED_COLS = 9; // No, ID, Nama, Jabatan, L/P, Tj.MK, Tunjangan, Upah/Hari, Upah Lbr/Jam
    /** Sub-columns per date */
    protected const SUB_COLS = 6;
    /** End totals columns */
    protected const TOT_COLS = 4; // Total Hari Kerja, Total Overtime, Total Uang Makan+Ins, Total Terima

    protected const SUB_HEADERS = [
        'uang_makan' => ['Kode', 'H/A', 'Upah/Hari', 'L/M', 'Lembur', 'Nominal'],
        'lembur'     => ['Kode', 'H/A', 'Upah/Hari', 'L/M', 'Lbr', 'Nominal'],
    ];

    protected const DATA_KEYS = [
        'uang_makan' => ['kode', 'ha', 'upah_per_hari', 'lm', 'lembur', 'nominal'],
        'lembur'     => ['kode', 'ha', 'upah_per_hari', 'lm', 'lembur', 'nominal'],
    ];

    // ── Corporate colors ──────────────────────────────────────────
    protected const COLOR_PRIMARY    = '1F4E79';   // Dark navy
    protected const COLOR_ACCENT     = '2E75B6';   // Medium blue
    protected const COLOR_HEADER_BG  = 'D6E4F0';   // Light blue bg
    protected const COLOR_DATE_BG    = 'E9F0F8';   // Very light blue
    protected const COLOR_SECTION_BG = 'F2F7FB';   // Soft blue section
    protected const COLOR_TOTAL_BG   = 'B4D6F0';   // Blue for totals
    protected const COLOR_GRAND_BG   = '1F4E79';   // Dark navy for grand
    protected const COLOR_ALT_ROW    = 'F8F9FA';   // Alternate row tint
    protected const COLOR_WHITE      = 'FFFFFF';
    protected const COLOR_GREEN_BG   = 'E8F5E9';   // Green section label
    protected const COLOR_GOLD_BG    = 'FFF8E1';   // Gold for grand total label

    protected $sectionRanges = [];

    public function __construct($sections, $dates, $label, $companyName = 'PT. KEMILAU UNGARAN SUKSES')
    {
        $this->sections = $sections;
        $this->dates = $dates;
        $this->label = $label;
        $this->companyName = $companyName;
    }

    public function title(): string
    {
        return 'Detail';
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
            'A' => 5,   // No
            'B' => 10,  // ID
            'C' => 30,  // Nama
            'D' => 24,  // Jabatan
            'E' => 5,   // L/P
            'F' => 14,  // Tj. MK
            'G' => 14,  // Tunjangan
            'H' => 14,  // Upah/Hari
            'I' => 14,  // Upah Lbr/Jam
        ];

        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates)) + self::TOT_COLS;
        $col = 'J';

        for ($i = 0; $i < count($this->dates); $i++) {
            $widths[$col] = 8;  $col = self::nextCol($col); // Kode
            $widths[$col] = 7;  $col = self::nextCol($col); // H/A
            $widths[$col] = 14; $col = self::nextCol($col); // Upah/Hari
            $widths[$col] = 10; $col = self::nextCol($col); // L/M / Lbr
            $widths[$col] = 10; $col = self::nextCol($col); // Lembur
            $widths[$col] = 16; $col = self::nextCol($col); // Nominal
        }

        // Totals
        $widths[$col] = 16; $col = self::nextCol($col);
        $widths[$col] = 16; $col = self::nextCol($col);
        $widths[$col] = 18; $col = self::nextCol($col);
        $widths[$col] = 16;

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

                // Repeat header rows when printing
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 6);

                // ── Default row height ──────────────────────────────
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

                // Row 2: Location & tagline
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'UNGARAN');
                $sheet->getStyle("A{$currentRow}")->getFont()->setSize(10)->setColor(new Color('666666'));
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // Row 3: Report title
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'RINCIAN GAJI & LEMBUR');
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

                // Row 5: Separator line
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->getRowDimension($currentRow)->setRowHeight(4);
                $currentRow++;

                // Row 6: Blank spacer
                $currentRow++;

                // ══════════════════════════════════════════════════════
                // TABLE DATA (per section)
                // ══════════════════════════════════════════════════════

                $this->sectionRanges = [];
                $dataStartRow = $currentRow;
                $lastDataRow = $currentRow;
                $currentIsAlt = false;

                foreach ($this->sections as $section) {
                    $type = $section['type'] ?? 'uang_makan';
                    $subHeaders = self::SUB_HEADERS[$type] ?? self::SUB_HEADERS['uang_makan'];
                    $dataKeys = self::DATA_KEYS[$type] ?? self::DATA_KEYS['uang_makan'];

                    // ── Section label row ────────────────────────────
                    $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", $section['label']);
                    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(10)->setColor(new Color(self::COLOR_PRIMARY));
                    $sheet->getStyle("A{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_SECTION_BG);
                    $sheet->getRowDimension($currentRow)->setRowHeight(20);
                    $currentRow++;

                    // ── Header row 1: Fixed + date groups ───────────
                    $headerStartRow = $currentRow;

                    $fixedHeaders = ['No', 'ID', 'Nama', 'Bagian / Jabatan', 'L/P', 'Tj. MK', 'Tunjangan', 'Upah/Hari', 'Upah Lbr/Jam'];
                    foreach ($fixedHeaders as $i => $h) {
                        $col = self::colLetter($i + 1);
                        $sheet->setCellValue("{$col}{$currentRow}", $h);
                        $sheet->mergeCells("{$col}{$currentRow}:{$col}" . ($currentRow + 1));
                    }

                    $dateStartCol = self::FIXED_COLS + 1;
                    foreach ($this->dates as $dateStr) {
                        $start = self::colLetter($dateStartCol);
                        $end = self::colLetter($dateStartCol + self::SUB_COLS - 1);
                        $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
                        $sheet->setCellValue("{$start}{$currentRow}", strtoupper($formatted));
                        $sheet->mergeCells("{$start}{$currentRow}:{$end}{$currentRow}");
                        $dateStartCol += self::SUB_COLS;
                    }

                    // Total headers (rowspan 2)
                    $totStartCol = $dateStartCol;
                    $totalHeaders = ['Total\nHari Kerja', 'Total\nOvertime', 'Total\nU.Mkn+Ins', 'Total\nTerima'];
                    foreach ($totalHeaders as $i => $th) {
                        $sheet->setCellValue(self::colLetter($totStartCol) . "{$currentRow}", str_replace('\n', "\n", $th));
                        $sheet->getStyle(self::colLetter($totStartCol) . "{$currentRow}")->getAlignment()->setWrapText(true);
                        $sheet->mergeCells(self::colLetter($totStartCol) . "{$currentRow}:" . self::colLetter($totStartCol) . ($currentRow + 1));
                        $totStartCol++;
                    }

                    $currentRow++;

                    // ── Header row 2: sub-headers per date ─────────
                    $dateStartCol = self::FIXED_COLS + 1;
                    foreach ($this->dates as $dateStr) {
                        foreach ($subHeaders as $sh) {
                            $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", $sh);
                            $dateStartCol++;
                        }
                    }

                    // ── Header styling ───────────────────────────────
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getFont()->setBold(true)->setSize(8)
                        ->setColor(new Color(self::COLOR_PRIMARY));
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_HEADER_BG);
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getRowDimension($headerStartRow)->setRowHeight(18);
                    $sheet->getRowDimension($currentRow)->setRowHeight(16);

                    // Blue tint for date header row 1
                    $firstDateCol = self::colLetter(self::FIXED_COLS + 1);
                    $lastDateCol = self::colLetter(self::FIXED_COLS + (self::SUB_COLS * count($this->dates)));
                    $sheet->getStyle("{$firstDateCol}{$headerStartRow}:{$lastDateCol}{$headerStartRow}")
                        ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_DATE_BG);

                    // Border around header area
                    $sheet->getStyle("A{$headerStartRow}:{$lastCol}{$currentRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $currentRow++;

                    // ── Data rows ────────────────────────────────────
                    $sectionDataStart = $currentRow;
                    $counter = 0;

                    foreach ($section['data'] as $item) {
                        $counter++;
                        $colIdx = 1;

                        // Fixed columns
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $counter); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['id']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['name']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['jabatan']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['gender']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['tj_mk'] ?? 0); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['tunjangan'] ?? 0); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['upah_per_hari'] ?? 0); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['upah_lembur_per_jam'] ?? 0); $colIdx++;

                        // Date sub-columns
                        $days = $item['days'] ?? [];
                        foreach ($this->dates as $dateStr) {
                            $d = $days[$dateStr] ?? null;
                            if ($d) {
                                foreach ($dataKeys as $key) {
                                    $val = $d[$key] ?? '';
                                    $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $val);
                                    $colIdx++;
                                }
                            } else {
                                foreach ($dataKeys as $key) {
                                    $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", '');
                                    $colIdx++;
                                }
                            }
                        }

                        // Totals
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['total_hari_kerja'] ?? 0); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['total_overtime'] ?? 0); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['total_uang_makan'] ?? 0); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $item['total_terima'] ?? 0);

                        // Alternating row color
                        if ($currentIsAlt) {
                            $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_ALT_ROW);
                        }
                        $currentIsAlt = !$currentIsAlt;
                        $currentRow++;
                    }

                    $sectionDataEnd = $currentRow - 1;

                    $this->sectionRanges[] = [
                        'type'      => $type,
                        'dataStart' => $sectionDataStart,
                        'dataEnd'   => $sectionDataEnd,
                    ];

                    // ── Section totals row ──────────────────────────
                    if (!empty($section['totals']) && ($section['totals']['count'] ?? 0) > 0) {
                        $totals = $section['totals'];
                        $colIdx = 1;

                        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                        $sheet->setCellValue("A{$currentRow}", 'TOTAL ' . $section['label']);
                        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(9);
                        $colIdx = 6;

                        // Skip fixed cols
                        $emptyCols = 4; // Tj.MK, Tunjangan, Upah/Hari, Upah Lbr/Jam
                        for ($i = 0; $i < $emptyCols; $i++) {
                            $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", '');
                            $colIdx++;
                        }

                        // Skip date columns
                        $colIdx += self::SUB_COLS * count($this->dates);

                        // Totals
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $totals['total_hari_kerja']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $totals['total_overtime']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $totals['total_uang_makan']); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $totals['total_terima']);

                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_TOTAL_BG);
                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getFont()->setBold(true)->setSize(9);
                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
                        $sheet->getRowDimension($currentRow)->setRowHeight(18);
                        $currentRow++;
                    }

                    $lastDataRow = $currentRow - 1;
                    $currentRow++; // Blank row
                }

                // ══════════════════════════════════════════════════════
                // GRAND TOTAL (cari dari section terakhir atau hitung ulang)
                // ══════════════════════════════════════════════════════
                if ($this->sections) {
                    $allData = collect();
                    foreach ($this->sections as $section) {
                        foreach (($section['data'] ?? []) as $emp) {
                            $allData->push($emp);
                        }
                    }

                    if ($allData->isNotEmpty()) {
                        $gtHariKerja = round($allData->sum('total_hari_kerja'), 2);
                        $gtOvertime = round($allData->sum('total_overtime'), 2);
                        $gtUangMakan = round($allData->sum('total_uang_makan'), 2);
                        $gtTerima = round($allData->sum('total_terima'), 2);

                        $colIdx = 1;
                        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                        $sheet->setCellValue("A{$currentRow}", 'GRAND TOTAL');
                        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11)->setColor(new Color(self::COLOR_WHITE));
                        $colIdx = 6;

                        for ($i = 0; $i < 4; $i++) {
                            $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", '');
                            $colIdx++;
                        }

                        $colIdx += self::SUB_COLS * count($this->dates);

                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $gtHariKerja); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $gtOvertime); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $gtUangMakan); $colIdx++;
                        $sheet->setCellValue(self::colLetter($colIdx) . "{$currentRow}", $gtTerima);

                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::COLOR_GRAND_BG);
                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getFont()->setBold(true)->setSize(10)->setColor(new Color(self::COLOR_WHITE));
                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                        $sheet->getStyle("A{$currentRow}:{$lastCol}{$currentRow}")
                            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
                        $sheet->getRowDimension($currentRow)->setRowHeight(22);
                        $lastDataRow = $currentRow;
                        $currentRow += 2;
                    }
                }

                // ══════════════════════════════════════════════════════
                // FOOTER: Generated info
                // ══════════════════════════════════════════════════════
                $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'Dicetak: ' . Carbon::now()->translatedFormat('d F Y H:i'));
                $sheet->getStyle("A{$currentRow}")->getFont()->setSize(8)->setColor(new Color('999999'));
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // ══════════════════════════════════════════════════════
                // BORDERS (data area)
                // ══════════════════════════════════════════════════════
                $borderEndRow = max($lastDataRow, $dataStartRow);
                if ($borderEndRow >= $dataStartRow) {
                    // Thin borders for all data cells
                    $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$borderEndRow}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    // Thick outer border for the entire data area
                    $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$borderEndRow}")
                        ->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);

                    // Thin bottom for section data rows
                    $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$borderEndRow}")
                        ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
                }

                // ══════════════════════════════════════════════════════
                // NUMBER FORMATS
                // ══════════════════════════════════════════════════════
                foreach ($this->sectionRanges as $range) {
                    if ($range['dataStart'] > $range['dataEnd']) continue;

                    $type = $range['type'];
                    $rStart = $range['dataStart'];
                    $rEnd = $range['dataEnd'];

                    // Fixed cols F-I: Tj.MK, Tunjangan, Upah/Hari, Upah Lbr/Jam
                    for ($c = 6; $c <= 9; $c++) {
                        $cl = self::colLetter($c);
                        $sheet->getStyle("{$cl}{$rStart}:{$cl}{$rEnd}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }

                    // Per-date number columns
                    for ($i = 0; $i < count($this->dates); $i++) {
                        $base = self::FIXED_COLS + ($i * self::SUB_COLS);

                        // Upah/Hari (offset 3)
                        $sheet->getStyle(self::colLetter($base + 3) . "{$rStart}:" . self::colLetter($base + 3) . "{$rEnd}")
                            ->getNumberFormat()->setFormatCode('#,##0');

                        if ($type === 'uang_makan') {
                            // Nominal (offset 6)
                            $sheet->getStyle(self::colLetter($base + 6) . "{$rStart}:" . self::colLetter($base + 6) . "{$rEnd}")
                                ->getNumberFormat()->setFormatCode('#,##0');
                        } else {
                            // L/M (offset 4), Lbr (offset 5), Nominal (offset 6)
                            $sheet->getStyle(self::colLetter($base + 4) . "{$rStart}:" . self::colLetter($base + 4) . "{$rEnd}")
                                ->getNumberFormat()->setFormatCode('#,##0');
                            $sheet->getStyle(self::colLetter($base + 5) . "{$rStart}:" . self::colLetter($base + 5) . "{$rEnd}")
                                ->getNumberFormat()->setFormatCode('#,##0');
                            $sheet->getStyle(self::colLetter($base + 6) . "{$rStart}:" . self::colLetter($base + 6) . "{$rEnd}")
                                ->getNumberFormat()->setFormatCode('#,##0');
                        }
                    }

                    // Totals columns
                    $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                    for ($c = 1; $c <= self::TOT_COLS; $c++) {
                        $totCol = self::colLetter($totBase + $c);
                        $sheet->getStyle("{$totCol}{$rStart}:{$totCol}{$rEnd}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Also format grand total row
                if (isset($gtHariKerja)) {
                    $gtRow = $currentRow - 2; // Row we wrote grand total to
                    $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                    for ($c = 1; $c <= self::TOT_COLS; $c++) {
                        $totCol = self::colLetter($totBase + $c);
                        $sheet->getStyle("{$totCol}{$gtRow}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // ══════════════════════════════════════════════════════
                // ALIGNMENT
                // ══════════════════════════════════════════════════════

                // Center: No (A), ID (B), L/P (E), date sub-columns
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Right-align: all number columns
                for ($c = 6; $c <= 9; $c++) {
                    $cl = self::colLetter($c);
                    $sheet->getStyle("{$cl}{$dataStartRow}:{$cl}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Right-align date sub-column number cells
                for ($i = 0; $i < count($this->dates); $i++) {
                    $base = self::FIXED_COLS + ($i * self::SUB_COLS);
                    // Upah/Hari (offset 3), L/M/Lbr (offset 4/5), Nominal (offset 6)
                    $sheet->getStyle(self::colLetter($base + 3) . "{$dataStartRow}:" . self::colLetter($base + 3) . "{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle(self::colLetter($base + 6) . "{$dataStartRow}:" . self::colLetter($base + 6) . "{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Right-align totals
                $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                for ($c = 1; $c <= self::TOT_COLS; $c++) {
                    $totCol = self::colLetter($totBase + $c);
                    $sheet->getStyle("{$totCol}{$dataStartRow}:{$totCol}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ══════════════════════════════════════════════════════
                // FREEZE PANE
                // ══════════════════════════════════════════════════════
                // $dataStartRow = first section label row
                // +3 = skip label + 2 header rows → freeze at first data row
                $freezeRow = $dataStartRow + 3;
                $sheet->freezePane('J' . $freezeRow);
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
