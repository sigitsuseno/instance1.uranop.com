<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class AttendanceSectionSheet implements FromArray, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
    protected $section;
    protected $dates;
    protected $label;
    protected $companyName;

    // Section accent colors
    protected const SECTION_COLORS = [
        'A.' => '1E40AF',
        'B.' => '065F46',
        'C.' => '9A3412',
    ];

    // Status → color mapping
    protected const STATUS_COLORS = [
        'H'   => '16A34A',
        'L'   => '2563EB',
        'A'   => 'DC2626',
        'C'   => 'D97706',
        'I'   => '7C3AED',
        'S'   => 'EA580C',
        'Off' => '9CA3AF',
    ];

    public function __construct($section, $dates, $label, $companyName)
    {
        $this->section = $section;
        $this->dates = $dates;
        $this->label = $label;
        $this->companyName = $companyName;
    }

    public function title(): string
    {
        // Sheet title — max 31 chars, no special chars
        $label = $this->section['label'];
        $safe = str_replace(['.', ' '], ['', '_'], $label);
        return substr($safe, 0, 31);
    }

    public function array(): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        return [];
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
                Carbon::setLocale('id');

                $totalDates = count($this->dates);
                $isA = str_starts_with($this->section['label'], 'A.');
                $isB = str_starts_with($this->section['label'], 'B.');
                $showUangMakan = $isA || $isB;
                $data = $this->section['data'];
                $hasSpr = collect($data)->contains(fn($r) => in_array('GRP-SPR', $r['group_codes'] ?? []));
                $showInsentif = $isB && $hasSpr;
                $colsPerDate = $isA ? 1 : 2;
                $totalCols = 2 + ($totalDates * $colsPerDate) + ($showUangMakan ? 1 : 0) + ($showInsentif ? 1 : 0);
                $maxColLetter = self::colLetter($totalCols);
                $accentColor = $this->getAccentColor();

                // ── Date range ────────────────────────────
                $tanggalRange = '';
                if (count($this->dates) > 0) {
                    $first = Carbon::parse($this->dates[0]['date']);
                    $last = Carbon::parse($this->dates[count($this->dates) - 1]['date']);
                    $tanggalRange = $first->translatedFormat('d F') . ' – ' . $last->translatedFormat('d F Y');
                }

                // ── Page Setup ────────────────────────────
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageMargins()
                    ->setTop(0.5)->setBottom(0.5)
                    ->setLeft(0.4)->setRight(0.4);

                $r = 1; // current row tracker

                // ═══════════════════════════════════════════
                // HEADER
                // ═══════════════════════════════════════════

                // Row 1: Company
                $sheet->mergeCells("A{$r}:{$maxColLetter}{$r}");
                $sheet->setCellValue("A{$r}", $this->companyName);
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Calibri', 'color' => ['rgb' => '1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($r)->setRowHeight(28);
                $r++;

                // Row 2: Title
                $sheet->mergeCells("A{$r}:{$maxColLetter}{$r}");
                $sheet->setCellValue("A{$r}", 'LAPORAN KEHADIRAN HARIAN');
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'name' => 'Calibri', 'color' => ['rgb' => '374151']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($r)->setRowHeight(22);
                $r++;

                // Row 3: Section label + Period
                $sheet->mergeCells("A{$r}:{$maxColLetter}{$r}");
                $sheet->setCellValue("A{$r}", strtoupper($this->section['label']) . '  |  Periode: ' . strtoupper($this->label) . '  |  ' . $tanggalRange);
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($r)->setRowHeight(18);
                $r++;

                // Row 4: Timestamp + count
                $sheet->mergeCells("A{$r}:{$maxColLetter}{$r}");
                $sheet->setCellValue("A{$r}", count($data) . ' Karyawan  |  Dicetak: ' . Carbon::now()->translatedFormat('d F Y, H:i') . ' WIB');
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['size' => 8, 'name' => 'Calibri', 'color' => ['rgb' => '9CA3AF'], 'italic' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($r)->setRowHeight(14);
                $r += 2;

                $dataStartRow = $r;

                // ═══════════════════════════════════════════
                // TABLE HEADER ROW 1 — Date groups
                // ═══════════════════════════════════════════
                $header1 = $r;
                $sheet->getRowDimension($r)->setRowHeight(18);

                $sheet->setCellValue("A{$r}", 'NIP');
                $sheet->mergeCells("A{$r}:A" . ($r + 1));
                $sheet->setCellValue("B{$r}", 'Nama');
                $sheet->mergeCells("B{$r}:B" . ($r + 1));

                $col = 3;
                foreach ($this->dates as $d) {
                    $dateLabel = strtoupper(Carbon::parse($d['date'])->translatedFormat('D, d/m'));
                    if ($colsPerDate === 1) {
                        $sheet->setCellValue(self::colLetter($col) . "{$r}", $dateLabel);
                        $endCol = self::colLetter($col);
                        $col++;
                    } else {
                        $endCol = self::colLetter($col + 1);
                        $sheet->setCellValue(self::colLetter($col) . "{$r}", $dateLabel);
                        $sheet->mergeCells(self::colLetter($col) . "{$r}:{$endCol}{$r}");
                        $col += 2;
                    }
                    if (!empty($d['is_weekend'])) {
                        $sheet->getStyle(self::colLetter($col - $colsPerDate) . "{$r}:{$endCol}{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']],
                            'font' => ['color' => ['rgb' => 'DC2626']],
                        ]);
                    }
                }

                if ($showUangMakan) {
                    $umCol = self::colLetter($col);
                    $sheet->setCellValue("{$umCol}{$r}", 'UANG MAKAN');
                    $sheet->mergeCells("{$umCol}{$r}:{$umCol}" . ($r + 1));
                    $col++;
                }
                if ($showInsentif) {
                    $insCol = self::colLetter($col);
                    $sheet->setCellValue("{$insCol}{$r}", 'INSENTIF');
                    $sheet->mergeCells("{$insCol}{$r}:{$insCol}" . ($r + 1));
                    $col++;
                }
                $r++;

                // ═══════════════════════════════════════════
                // TABLE HEADER ROW 2 — Sub-headers
                // ═══════════════════════════════════════════
                $header2 = $r;
                $sheet->getRowDimension($r)->setRowHeight(17);
                $col = 3;
                foreach ($this->dates as $d) {
                    $sheet->setCellValue(self::colLetter($col) . "{$r}", 'St');
                    if ($colsPerDate === 2) {
                        $sheet->setCellValue(self::colLetter($col + 1) . "{$r}", $d['is_weekend'] ? 'LM' : 'OT');
                        $col += 2;
                    } else {
                        $col++;
                    }
                    if (!empty($d['is_weekend'])) {
                        $sheet->getStyle(self::colLetter($col - $colsPerDate) . "{$r}:" . self::colLetter($col - 1) . "{$r}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']],
                        ]);
                    }
                }

                // Style both header rows
                $sheet->getStyle("A{$header1}:{$maxColLetter}{$header2}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 7, 'name' => 'Calibri', 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $r++;

                // ═══════════════════════════════════════════
                // DATA ROWS
                // ═══════════════════════════════════════════
                $rowIdx = 0;
                foreach ($data as $row) {
                    $sheet->getRowDimension($r)->setRowHeight(16);
                    $isTitipan = !empty($row['is_titipan']);
                    $rowBg = $isTitipan ? 'F5F3FF' : ($rowIdx % 2 === 0 ? 'FFFFFF' : 'F9FAFB');

                    // NIP
                    $sheet->setCellValue("A{$r}", $row['employee_code']);
                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'font' => ['size' => 9, 'name' => 'Consolas', 'color' => ['rgb' => '6B7280']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // Nama
                    $sheet->setCellValue("B{$r}", $row['name']);
                    $sheet->getStyle("B{$r}")->applyFromArray([
                        'font' => ['size' => 9, 'name' => 'Calibri', 'color' => ['rgb' => '111827']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    // Date columns
                    $col = 3;
                    foreach ($this->dates as $d) {
                        $att = $row['attendance'][$d['date']] ?? null;
                        $status = $att['status'] ?? '-';
                        $isHoliday = $att['is_holiday'] ?? false;
                        $isWeekend = !empty($d['is_weekend']);

                        $cellBg = ($isHoliday || $isWeekend) ? 'FFF5F5' : $rowBg;

                        // Status
                        $statusColor = self::STATUS_COLORS[$status] ?? '374151';
                        $sheet->setCellValue(self::colLetter($col) . "{$r}", $status);
                        $sheet->getStyle(self::colLetter($col) . "{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri', 'color' => ['rgb' => $statusColor]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cellBg]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);

                        if ($colsPerDate === 2) {
                            $lm = $att['lm'] ?? null;
                            $ot = $att['overtime'] ?? null;
                            $val = $isHoliday ? $lm : $ot;
                            $display = ($val && $val > 0) ? round($val / 60, 1) : '';
                            $sheet->setCellValue(self::colLetter($col + 1) . "{$r}", $display);
                            $sheet->getStyle(self::colLetter($col + 1) . "{$r}")->applyFromArray([
                                'font' => ['size' => 8, 'name' => 'Calibri', 'color' => ['rgb' => '6B7280']],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $cellBg]],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                            ]);
                            $col += 2;
                        } else {
                            $col++;
                        }
                    }

                    // Uang Makan (Section A & B)
                    if ($showUangMakan) {
                        $uang = $row['total_uang_makan'] ?? 0;
                        $sheet->setCellValue(self::colLetter($col) . "{$r}", $uang > 0 ? $uang : '-');
                        $sheet->getStyle(self::colLetter($col) . "{$r}")->applyFromArray([
                            'font' => ['size' => 9, 'name' => 'Calibri', 'color' => ['rgb' => $uang > 0 ? '065F46' : '9CA3AF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                            'numberFormat' => ['formatCode' => $uang > 0 ? '#,##0' : '@'],
                        ]);
                        $col++;
                    }
                    // Insentif (khusus GRP-SPR di section B)
                    if ($showInsentif) {
                        $isSpr = in_array('GRP-SPR', $row['group_codes'] ?? []);
                        $ins  = $isSpr ? ($row['total_insentif'] ?? 0) : 0;
                        $sheet->setCellValue(self::colLetter($col) . "{$r}", $ins > 0 ? $ins : '-');
                        $sheet->getStyle(self::colLetter($col) . "{$r}")->applyFromArray([
                            'font' => ['size' => 9, 'name' => 'Calibri', 'color' => ['rgb' => $ins > 0 ? 'B45309' : '9CA3AF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                            'numberFormat' => ['formatCode' => $ins > 0 ? '#,##0' : '@'],
                        ]);
                        $col++;
                    }

                    $r++;
                    $rowIdx++;
                }

                // ═══════════════════════════════════════════
                // FOOTER — Total Uang Makan & Insentif (Section A & B)
                // ═══════════════════════════════════════════
                if (($showUangMakan || $showInsentif) && count($data) > 0) {
                    $fr = $r;
                    $sheet->getRowDimension($fr)->setRowHeight(22);

                    $sheet->mergeCells("A{$fr}:B{$fr}");
                    $sheet->setCellValue("A{$fr}", 'TOTAL UANG MAKAN' . ($showInsentif ? ' & INSENTIF' : ''));
                    $sheet->getStyle("A{$fr}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $accentColor]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);

                    for ($dc = 3; $dc < $totalCols; $dc++) {
                        $sheet->getStyle(self::colLetter($dc) . "{$fr}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $accentColor]],
                        ]);
                    }

                    // Hitung ulang posisi kolom (setelah tanggal)
                    $colFooter = 3 + ($totalDates * $colsPerDate);

                    // Total Uang Makan
                    $totalUM = array_sum(array_column($data, 'total_uang_makan'));
                    $sheet->setCellValue(self::colLetter($colFooter) . "{$fr}", $totalUM > 0 ? $totalUM : 0);
                    $sheet->getStyle(self::colLetter($colFooter) . "{$fr}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $accentColor]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'numberFormat' => ['formatCode' => '#,##0'],
                    ]);
                    $colFooter++;

                    // Total Insentif
                    if ($showInsentif) {
                        $totalIns = array_sum(array_column($data, 'total_insentif'));
                        $sheet->setCellValue(self::colLetter($colFooter) . "{$fr}", $totalIns > 0 ? $totalIns : 0);
                        $sheet->getStyle(self::colLetter($colFooter) . "{$fr}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => 'FFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $accentColor]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                            'numberFormat' => ['formatCode' => '#,##0'],
                        ]);
                        $colFooter++;
                    }

                    $r = $fr;
                }

                $lastDataRow = $r;

                // ═══════════════════════════════════════════
                // BORDERS
                // ═══════════════════════════════════════════
                $sheet->getStyle("A{$dataStartRow}:{$maxColLetter}{$lastDataRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
                    ],
                ]);

                // ═══════════════════════════════════════════
                // COLUMN WIDTHS
                // ═══════════════════════════════════════════
                $sheet->getColumnDimension('A')->setWidth(11);
                $sheet->getColumnDimension('B')->setWidth(30);
                for ($i = 3; $i <= $totalCols; $i++) {
                    $sheet->getColumnDimension(self::colLetter($i))->setWidth(6);
                }
                if ($showUangMakan) {
                    $umCol = $showInsentif ? $totalCols - 1 : $totalCols;
                    $sheet->getColumnDimension(self::colLetter($umCol))->setWidth(15);
                }
                if ($showInsentif) {
                    $sheet->getColumnDimension(self::colLetter($totalCols))->setWidth(15);
                }

                // ═══════════════════════════════════════════
                // FREEZE & FILTER
                // ═══════════════════════════════════════════
                $sheet->freezePane('C' . $dataStartRow);
                $sheet->setAutoFilter("A{$dataStartRow}:{$maxColLetter}{$lastDataRow}");
            },
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────

    protected function getAccentColor(): string
    {
        foreach (self::SECTION_COLORS as $prefix => $color) {
            if (str_starts_with($this->section['label'], $prefix)) {
                return $color;
            }
        }
        return '374151';
    }

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
}
