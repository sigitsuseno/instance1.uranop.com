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
    /** Sub-columns per date (same count for both types: 6) */
    protected const SUB_COLS = 6;
    /** End totals columns */
    protected const TOT_COLS = 4; // Total Hari Kerja, Total Overtime, Total Uang Makan, Total Terima

    /** Sub-headers per section type */
    protected const SUB_HEADERS = [
        'uang_makan' => ['Kode', 'H/A', 'Upah/Hari', 'L/M', 'Lembur', 'Nominal'],
        'lembur'     => ['Kode', 'H/A', 'Upah/Hari', 'Tarif Lbr', 'U.Makan', 'Konfirm'],
    ];

    /** Data keys per section type (in order matching sub-headers) */
    protected const DATA_KEYS = [
        'uang_makan' => ['kode', 'ha', 'upah_per_hari', 'lm', 'lembur', 'nominal'],
        'lembur'     => ['kode', 'ha', 'upah_per_hari', 'tarif_lembur', 'uang_makan', 'konfirmasi'],
    ];

    /** Track row ranges per section type for number formatting */
    protected $sectionRanges = [];

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
            'A' => 5,  'B' => 10, 'C' => 28, 'D' => 22, 'E' => 5,
            'F' => 15, 'G' => 14, 'H' => 14, 'I' => 14,
        ];

        // After fixed cols, 6 sub-cols per date, then 4 totals
        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates)) + self::TOT_COLS;
        $col = 'J';

        // Date sub-columns
        for ($i = 0; $i < count($this->dates); $i++) {
            $widths[$col] = 7;  $col = self::nextCol($col); // Col 1: varies
            $widths[$col] = 7;  $col = self::nextCol($col); // Col 2: varies
            $widths[$col] = 14; $col = self::nextCol($col); // Upah/Hari
            $widths[$col] = 10; $col = self::nextCol($col); // Col 4: varies
            $widths[$col] = 10; $col = self::nextCol($col); // Col 5: varies
            $widths[$col] = 16; $col = self::nextCol($col); // Col 6: varies
        }

        // Totals
        $widths[$col] = 16; $col = self::nextCol($col);
        $widths[$col] = 16; $col = self::nextCol($col);
        $widths[$col] = 16; $col = self::nextCol($col);
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
                $currentRow += 2;

                // --- Per-section tables ---
                $this->sectionRanges = [];
                $dataStartRow = $currentRow;
                $lastDataRow = $currentRow;

                foreach ($this->sections as $section) {
                    $type = $section['type'] ?? 'uang_makan';
                    $subHeaders = self::SUB_HEADERS[$type] ?? self::SUB_HEADERS['uang_makan'];
                    $dataKeys = self::DATA_KEYS[$type] ?? self::DATA_KEYS['uang_makan'];

                    // ── Section label row ──────────────────────────
                    $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", $section['label']);
                    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(10);
                    $sheet->getStyle("A{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
                    $currentRow++;

                    // ── Section header row 1 (fixed + date groups) ─
                    $headerStartRow = $currentRow;

                    $fixedHeaders = ['No', 'ID No', 'Nama', 'Bagian / Jabatan', 'L/P', 'Tj. MK', 'Tunjangan', 'Upah/Hari', 'Upah Lbr/Jam'];
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

                    // ── Section header row 2 (sub-headers per type) ─
                    $dateStartCol = self::FIXED_COLS + 1;
                    foreach ($this->dates as $dateStr) {
                        foreach ($subHeaders as $sh) {
                            $sheet->setCellValue(self::colLetter($dateStartCol) . "{$currentRow}", $sh);
                            $dateStartCol++;
                        }
                    }

                    // Style section headers
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

                    // ── Data rows ──────────────────────────────────
                    $sectionDataStart = $currentRow;
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
                            foreach ($dataKeys as $key) {
                                $val = $d[$key] ?? '';
                                $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $val);
                                $col++;
                            }
                        }

                        // Totals
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_hari_kerja'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_overtime'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_uang_makan'] ?? 0); $col++;
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $item['total_terima'] ?? 0);

                        $currentRow++;
                    }

                    $sectionDataEnd = $currentRow - 1;

                    // Track section range for number formatting
                    $this->sectionRanges[] = [
                        'type'      => $type,
                        'dataStart' => $sectionDataStart,
                        'dataEnd'   => $sectionDataEnd,
                    ];

                    // ── Section totals row ──────────────────────────
                    if (!empty($section['totals']) && $section['totals']['count'] > 0) {
                        $totals = $section['totals'];
                        $col = 1;

                        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
                        $sheet->setCellValue("A{$currentRow}", 'TOTAL ' . $section['label']);
                        $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
                        $col = 6;

                        // Skip fixed value columns
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

                    $lastDataRow = $currentRow - 1;
                    $currentRow++; // Blank row between sections
                }

                // ── Borders for data area ──────────────────────────
                $borderEndRow = $lastDataRow;
                $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$borderEndRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // ── Number formats (per section type) ───────────────
                foreach ($this->sectionRanges as $range) {
                    // Skip empty sections (dataStart > dataEnd)
                    if ($range['dataStart'] > $range['dataEnd']) continue;

                    $type = $range['type'];
                    $rangeStr = $range['dataStart'] . ':' . $range['dataEnd'];

                    // Fixed number cols (F-I): Tj.MK, Tunjangan, Upah/Hari, Upah Lbr/Jam
                    for ($c = 6; $c <= 9; $c++) {
                        $colLetter = self::colLetter($c);
                        $sheet->getStyle("{$colLetter}{$range['dataStart']}:{$colLetter}{$range['dataEnd']}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }

                    // Per-date number columns
                    for ($i = 0; $i < count($this->dates); $i++) {
                        $base = self::FIXED_COLS + ($i * self::SUB_COLS);

                        // Upah/Hari is always at offset 2 (col index base+3 in 1-based)
                        $upahCol = self::colLetter($base + 3);
                        $sheet->getStyle("{$upahCol}{$range['dataStart']}:{$upahCol}{$range['dataEnd']}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');

                        if ($type === 'uang_makan') {
                            // Nominal at offset 5 (col index base+6 in 1-based)
                            $nomCol = self::colLetter($base + 6);
                            $sheet->getStyle("{$nomCol}{$range['dataStart']}:{$nomCol}{$range['dataEnd']}")
                                ->getNumberFormat()->setFormatCode('#,##0.00');
                        } else {
                            // Tarif Lbr at offset 3 (col index base+4), U.Makan at offset 4 (base+5)
                            $tarifCol = self::colLetter($base + 4);
                            $sheet->getStyle("{$tarifCol}{$range['dataStart']}:{$tarifCol}{$range['dataEnd']}")
                                ->getNumberFormat()->setFormatCode('#,##0.00');
                            $umCol = self::colLetter($base + 5);
                            $sheet->getStyle("{$umCol}{$range['dataStart']}:{$umCol}{$range['dataEnd']}")
                                ->getNumberFormat()->setFormatCode('#,##0.00');
                        }
                    }

                    // Totals columns (always number)
                    $totBase = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
                    for ($c = 1; $c <= self::TOT_COLS; $c++) {
                        $totCol = self::colLetter($totBase + $c);
                        $sheet->getStyle("{$totCol}{$range['dataStart']}:{$totCol}{$range['dataEnd']}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                // ── Alignment ──────────────────────────────────────
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Freeze pane
                $sheet->freezePane('D' . ($dataStartRow + 1));
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
