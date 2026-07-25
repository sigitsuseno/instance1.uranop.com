<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class AttendanceMatrixExport implements FromArray, WithStyles, WithColumnWidths, WithEvents
{
    protected $sections;
    protected $dates;
    protected $label;
    protected $companyName;

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

                $currentRow = 1;

                $totalDates = count($this->dates);

                // Max layout: 2 + (totalDates * 2) untuk merges full-width
                $maxCols = 2 + ($totalDates * 2);
                $maxColLetter = self::colLetter($maxCols);

                // --- Company name ---
                $sheet->mergeCells("A{$currentRow}:{$maxColLetter}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $this->companyName);
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- Report title ---
                $sheet->mergeCells("A{$currentRow}:{$maxColLetter}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'LAPORAN KEHADIRAN');
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- Period ---
                $sheet->mergeCells("A{$currentRow}:{$maxColLetter}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'PERIODE: ' . strtoupper($this->label));
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow += 2;

                $dataStartRow = $currentRow;
                $lastDataRow = $currentRow;

                // ─── Pre-compute section layouts ─────────────────────
                $sectionLayouts = [];
                foreach ($this->sections as $section) {
                    if (empty($section['data'])) continue;

                    $isSectionA = str_starts_with($section['label'], 'A.');
                    // Section A: 1 col per date + Uang Makan col
                    // Section B/C: 2 cols per date (Status + OT/LM)
                    $colsPerDate = $isSectionA ? 1 : 2;
                    $totalSectionCols = 2 + ($totalDates * $colsPerDate) + ($isSectionA ? 1 : 0);
                    $colLetter = self::colLetter($totalSectionCols);

                    $sectionLayouts[] = [
                        'label'          => $section['label'],
                        'data'           => $section['data'],
                        'isSectionA'     => $isSectionA,
                        'colsPerDate'    => $colsPerDate,
                        'totalCols'      => $totalSectionCols,
                        'lastColLetter'  => $colLetter,
                    ];
                }

                foreach ($sectionLayouts as $layout) {
                    $isA = $layout['isSectionA'];
                    $colsPerDate = $layout['colsPerDate'];
                    $lsCol = $layout['lastColLetter'];

                    // --- Section label ---
                    $sheet->mergeCells("A{$currentRow}:{$maxColLetter}{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", $layout['label'] . ' (' . count($layout['data']) . ' karyawan)');
                    $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(10);
                    $sheet->getStyle("A{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
                    $currentRow++;

                    $headerStart = $currentRow;

                    // --- Header Row 1: Date groups ---
                    $sheet->setCellValue("A{$currentRow}", 'NIP');
                    $sheet->mergeCells("A{$currentRow}:A" . ($currentRow + 1));
                    $sheet->setCellValue("B{$currentRow}", 'Nama');
                    $sheet->mergeCells("B{$currentRow}:B" . ($currentRow + 1));

                    $col = 3;
                    foreach ($this->dates as $d) {
                        if ($colsPerDate === 1) {
                            // 1 col: Status
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", strtoupper(Carbon::parse($d['date'])->translatedFormat('D, d/m')));
                            $endCol = self::colLetter($col);
                            $col++;
                        } else {
                            // 2 cols: Status + OT/LM
                            $endCol = self::colLetter($col + 1);
                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", strtoupper(Carbon::parse($d['date'])->translatedFormat('D, d/m')));
                            $sheet->mergeCells(self::colLetter($col) . "{$currentRow}:{$endCol}{$currentRow}");
                            $col += 2;
                        }
                        // Weekend highlight
                        if (!empty($d['is_weekend'])) {
                            $sheet->getStyle(self::colLetter($col - $colsPerDate) . "{$currentRow}:{$endCol}{$currentRow}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF2F2');
                        }
                    }

                    // Uang Makan header (Section A only)
                    if ($isA) {
                        $umCol = self::colLetter($col);
                        $sheet->setCellValue("{$umCol}{$currentRow}", 'Uang Makan');
                        $sheet->mergeCells("{$umCol}{$currentRow}:{$umCol}" . ($currentRow + 1));
                    }

                    $currentRow++;

                    // --- Header Row 2: Sub-headers ---
                    $col = 3;
                    foreach ($this->dates as $d) {
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", 'St');
                        if ($colsPerDate === 2) {
                            $sheet->setCellValue(self::colLetter($col + 1) . "{$currentRow}", $d['is_weekend'] ? 'LM' : 'OT');
                            $col += 2;
                        } else {
                            $col++;
                        }
                    }

                    // Style headers
                    $sheet->getStyle("A{$headerStart}:{$lsCol}{$currentRow}")->getFont()->setBold(true)->setSize(8);
                    $sheet->getStyle("A{$headerStart}:{$lsCol}{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                    $sheet->getStyle("A{$headerStart}:{$lsCol}{$currentRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $currentRow++;

                    // --- Data Rows ---
                    foreach ($layout['data'] as $row) {
                        $sheet->setCellValue("A{$currentRow}", $row['employee_code']);
                        $sheet->setCellValue("B{$currentRow}", $row['name']);

                        $col = 3;
                        foreach ($this->dates as $d) {
                            $att = $row['attendance'][$d['date']] ?? null;
                            $status = $att['status'] ?? '-';

                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $status);

                            if ($colsPerDate === 2) {
                                $isHoliday = $att['is_holiday'] ?? false;
                                $lm = $att['lm'] ?? null;
                                $ot = $att['overtime'] ?? null;
                                $countVal = $isHoliday ? $lm : $ot;
                                $countDisplay = ($countVal && $countVal > 0) ? round($countVal / 60, 1) : '-';
                                $sheet->setCellValue(self::colLetter($col + 1) . "{$currentRow}", $countDisplay);

                                if ($isHoliday) {
                                    $sheet->getStyle(self::colLetter($col) . "{$currentRow}:" . self::colLetter($col + 1) . "{$currentRow}")->getFill()
                                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF2F2');
                                }
                                $col += 2;
                            } else {
                                // Section A: highlight weekend
                                if (!empty($d['is_weekend'])) {
                                    $sheet->getStyle(self::colLetter($col) . "{$currentRow}")->getFill()
                                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF2F2');
                                }
                                $col++;
                            }
                        }

                        // Uang Makan value (Section A only)
                        if ($isA) {
                            $umCol = self::colLetter($col);
                            $uangMakan = $row['total_uang_makan'] ?? 0;
                            $sheet->setCellValue("{$umCol}{$currentRow}", $uangMakan > 0 ? $uangMakan : '-');
                        }

                        $currentRow++;
                    }

                    $lastDataRow = $currentRow - 1;
                    $currentRow++; // Spacer
                }

                // --- Borders (use maxColLetter for full table width) ---
                $sheet->getStyle("A{$dataStartRow}:{$maxColLetter}{$lastDataRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // --- Alignment ---
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- Column Widths ---
                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(28);
                for ($i = 3; $i <= $maxCols; $i++) {
                    $sheet->getColumnDimension(self::colLetter($i))->setWidth(7);
                }

                // --- Freeze ---
                $sheet->freezePane('C' . ($dataStartRow + 1));
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
}
