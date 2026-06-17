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

                // Fixed cols: NIP (A), Nama (B)
                // Per-date: Status (C,D,...), OT/LM
                $totalDates = count($this->dates);
                $totalCols = 2 + ($totalDates * 2);
                $lastCol = self::colLetter($totalCols);

                // --- Company name ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", $this->companyName);
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- Report title ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'LAPORAN KEHADIRAN');
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow++;

                // --- Period ---
                $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'PERIODE: ' . strtoupper($this->label));
                $sheet->getStyle("A{$currentRow}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $currentRow += 2;

                $dataStartRow = $currentRow;
                $lastDataRow = $currentRow;

                foreach ($this->sections as $section) {
                    if (empty($section['data'])) continue;

                    // --- Section label ---
                    $sheet->mergeCells("A{$currentRow}:{$lastCol}{$currentRow}");
                    $sheet->setCellValue("A{$currentRow}", $section['label'] . ' (' . count($section['data']) . ' karyawan)');
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
                        $endCol = self::colLetter($col + 1);
                        $formatted = Carbon::parse($d['date'])->translatedFormat('D, d/m');
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", strtoupper($formatted));
                        $sheet->mergeCells(self::colLetter($col) . "{$currentRow}:{$endCol}{$currentRow}");
                        // Weekend highlight
                        if (!empty($d['is_weekend'])) {
                            $sheet->getStyle(self::colLetter($col) . "{$currentRow}:{$endCol}{$currentRow}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF2F2');
                        }
                        $col += 2;
                    }

                    $currentRow++;

                    // --- Header Row 2: St / OT-LM ---
                    $col = 3;
                    foreach ($this->dates as $d) {
                        $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", 'St');
                        $sheet->setCellValue(self::colLetter($col + 1) . "{$currentRow}", $d['is_weekend'] ? 'LM' : 'OT');
                        $col += 2;
                    }

                    // Style headers
                    $sheet->getStyle("A{$headerStart}:{$lastCol}{$currentRow}")->getFont()->setBold(true)->setSize(8);
                    $sheet->getStyle("A{$headerStart}:{$lastCol}{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                    $sheet->getStyle("A{$headerStart}:{$lastCol}{$currentRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    $currentRow++;

                    // --- Data Rows ---
                    foreach ($section['data'] as $row) {
                        $sheet->setCellValue("A{$currentRow}", $row['employee_code']);
                        $sheet->setCellValue("B{$currentRow}", $row['name']);

                        $col = 3;
                        foreach ($this->dates as $d) {
                            $att = $row['attendance'][$d['date']] ?? null;
                            $status = $att['status'] ?? '-';
                            $isHoliday = $att['is_holiday'] ?? false;
                            $lm = $att['lm'] ?? null;
                            $ot = $att['overtime'] ?? null;
                            $countVal = $isHoliday ? $lm : $ot;
                            $countDisplay = ($countVal && $countVal > 0) ? round($countVal / 60, 1) : '-';

                            $sheet->setCellValue(self::colLetter($col) . "{$currentRow}", $status);
                            $sheet->setCellValue(self::colLetter($col + 1) . "{$currentRow}", $countDisplay);

                            // Weekend background
                            if ($isHoliday) {
                                $sheet->getStyle(self::colLetter($col) . "{$currentRow}:" . self::colLetter($col + 1) . "{$currentRow}")->getFill()
                                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF2F2');
                            }
                            $col += 2;
                        }

                        $currentRow++;
                    }

                    $lastDataRow = $currentRow - 1;
                    $currentRow++; // Spacer
                }

                // --- Borders ---
                $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastDataRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // --- Alignment ---
                $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- Column Widths ---
                $sheet->getColumnDimension('A')->setWidth(12);
                $sheet->getColumnDimension('B')->setWidth(28);
                for ($i = 3; $i <= $totalCols; $i++) {
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
