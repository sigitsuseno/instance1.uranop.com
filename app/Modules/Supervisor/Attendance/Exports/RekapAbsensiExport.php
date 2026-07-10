<?php

namespace App\Modules\Supervisor\Attendance\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RekapAbsensiExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    protected string $startDate;
    protected string $endDate;
    protected string $companyName;
    protected array $dates;
    protected array $employees;
    protected array $rows;

    public function __construct(array $employees, string $startDate, string $endDate, string $companyName = '')
    {
        $this->employees = $employees;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->companyName = $companyName;

        // Build date range
        $this->dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $this->dates[] = $current->copy();
            $current->addDay();
        }

        $this->buildRows();
    }

    protected function buildRows(): void
    {
        $this->rows = [];

        $totalCols = 2 + count($this->dates) * 2; // No + Nama + (status + lembur) per date
        $lastColLetter = $this->colLetter($totalCols);

        // Row 1: Company name
        $row1 = array_fill(0, $totalCols, '');
        $row1[0] = $this->companyName;
        $this->rows[] = $row1;

        // Row 2: Title
        $row2 = array_fill(0, $totalCols, '');
        $row2[0] = 'LAPORAN REKAP ABSENSI KARYAWAN';
        $this->rows[] = $row2;

        // Row 3: Period
        $row3 = array_fill(0, $totalCols, '');
        $row3[0] = 'Periode: ' . $this->startDate . ' s/d ' . $this->endDate;
        $this->rows[] = $row3;

        // Row 4: Empty
        $this->rows[] = array_fill(0, $totalCols, '');

        // Row 5: Header row 1 (No, Nama, dates...)
        $row5 = ['No', 'Nama'];
        foreach ($this->dates as $date) {
            $row5[] = $date->format('d');
            $row5[] = ''; // will be merged with next
        }
        // Pad to totalCols
        while (count($row5) < $totalCols) {
            $row5[] = '';
        }
        $this->rows[] = $row5;

        // Row 6: Header row 2 (empty, empty, day_short, L, ...)
        $row6 = ['', ''];
        foreach ($this->dates as $date) {
            $row6[] = $date->translatedFormat('D');
            $row6[] = 'L';
        }
        while (count($row6) < $totalCols) {
            $row6[] = '';
        }
        $this->rows[] = $row6;

        // Data rows
        foreach ($this->employees as $emp) {
            $row = [$emp['no'], $emp['employee_name']];
            foreach ($this->dates as $date) {
                $dateStr = $date->toDateString();
                $day = $emp['days'][$dateStr] ?? null;
                $row[] = $day['status'] ?? '';
                $lembur = $day['lembur'] ?? 0;
                // Format koma Indonesia
                $row[] = $lembur > 0 ? str_replace('.', ',', (string) $lembur) : '';
            }
            while (count($row) < $totalCols) {
                $row[] = '';
            }
            $this->rows[] = $row;
        }
    }

    protected function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = intdiv($n, 26);
        }
        return $letter;
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $totalCols = 2 + count($this->dates) * 2;
                $lastColLetter = $this->colLetter($totalCols);
                $headerRow1 = 5;
                $headerRow2 = 6;
                $dataStartRow = 7;
                $lastRow = $dataStartRow + count($this->employees) - 1;

                // ── Merge title rows ──
                $sheet->mergeCells("A1:{$lastColLetter}1");
                $sheet->mergeCells("A2:{$lastColLetter}2");
                $sheet->mergeCells("A3:{$lastColLetter}3");

                // ── Merge header: "No" row 5-6, "Nama" row 5-6 ──
                $sheet->mergeCells("A{$headerRow1}:A{$headerRow2}");
                $sheet->mergeCells("B{$headerRow1}:B{$headerRow2}");

                // ── Merge date headers: each date spans 2 cols ──
                for ($i = 0; $i < count($this->dates); $i++) {
                    $colStart = $this->colLetter(3 + $i * 2);       // C, E, G, ...
                    $colEnd = $this->colLetter(3 + $i * 2 + 1);     // D, F, H, ...
                    $sheet->mergeCells("{$colStart}{$headerRow1}:{$colEnd}{$headerRow1}");
                }

                // ── Global border + font ──
                $sheet->getStyle("A1:{$lastColLetter}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                    ],
                    'font' => ['size' => 8],
                ]);

                // ── Title row 1 (company) ──
                $sheet->getStyle("A1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // ── Title row 2 (LAPORAN) ──
                $sheet->getStyle("A2")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Title row 3 (periode) ──
                $sheet->getStyle("A3")->applyFromArray([
                    'font' => ['size' => 8, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Header row 1 + 2 style ──
                $headerRange = "A{$headerRow1}:{$lastColLetter}{$headerRow2}";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($headerRow1)->setRowHeight(18);
                $sheet->getRowDimension($headerRow2)->setRowHeight(16);

                // ── Center: No column ──
                $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ── Alternating row colors ──
                for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                    if (($row - $dataStartRow) % 2 === 1) {
                        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F9FAFB'));
                    }
                }

                // ── Status cell coloring per column ──
                for ($i = 0; $i < count($this->dates); $i++) {
                    $statusCol = $this->colLetter(3 + $i * 2);      // C, E, G, ...
                    $lemburCol = $this->colLetter(3 + $i * 2 + 1);  // D, F, H, ...

                    // Center status + lembur columns
                    $sheet->getStyle("{$statusCol}{$dataStartRow}:{$lemburCol}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Per-row per-cell coloring for status
                    foreach ($this->employees as $r => $emp) {
                        $dateStr = $this->dates[$i]->toDateString();
                        $day = $emp['days'][$dateStr] ?? null;
                        $status = $day['status'] ?? '';
                        $rowNum = $dataStartRow + $r;

                        $colorMap = [
                            'H' => ['bg' => 'D4EDDA', 'text' => '155724'],
                            'S' => ['bg' => 'FFF3CD', 'text' => '856404'],
                            'I' => ['bg' => 'E8DAEF', 'text' => '6C3483'],
                            'C' => ['bg' => 'D6EAF8', 'text' => '1A5276'],
                            'L' => ['bg' => 'FDEBD0', 'text' => '935116'],
                            'O' => ['bg' => 'EAEDED', 'text' => '7F8C8D'],
                        ];

                        if (isset($colorMap[$status])) {
                            $c = $colorMap[$status];
                            $sheet->getStyle("{$statusCol}{$rowNum}")->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c['bg']]],
                                'font' => ['color' => ['rgb' => $c['text']], 'bold' => $status === 'H'],
                            ]);
                        } elseif ($status === '' || $status === '-') {
                            $sheet->getStyle("{$statusCol}{$rowNum}")->getFont()
                                ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D1D5DB'));
                        }

                        // Lembur cell color
                        if (($day['lembur'] ?? 0) > 0) {
                            $sheet->getStyle("{$lemburCol}{$rowNum}")->applyFromArray([
                                'font' => ['color' => ['rgb' => 'C2410C'], 'bold' => true],
                            ]);
                        }
                    }
                }

                // ── Freeze panes ──
                $sheet->freezePane('C7');
            },
        ];
    }
}
