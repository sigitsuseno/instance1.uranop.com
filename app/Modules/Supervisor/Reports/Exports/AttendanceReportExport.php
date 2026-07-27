<?php

namespace App\Modules\Supervisor\Reports\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

class AttendanceReportExport implements WithMultipleSheets
{
    protected string $startDate;
    protected string $endDate;
    protected string $companyName;
    protected array $dates;
    protected array $sectionAllin;
    protected array $sectionBulanan;

    public function __construct(
        array $sectionAllin,
        array $sectionBulanan,
        string $startDate,
        string $endDate,
        string $companyName = ''
    ) {
        $this->sectionAllin  = $sectionAllin;
        $this->sectionBulanan = $sectionBulanan;
        $this->startDate     = $startDate;
        $this->endDate       = $endDate;
        $this->companyName   = $companyName;

        // Build date range
        $this->dates = [];
        $current = Carbon::parse($startDate);
        $end     = Carbon::parse($endDate);
        while ($current <= $end) {
            $this->dates[] = $current->copy();
            $current->addDay();
        }
    }

    public function sheets(): array
    {
        $sheets = [];

        if (count($this->sectionAllin)) {
            $sheets[] = new AttendanceReportSheet($this->sectionAllin, $this->dates, $this->startDate, $this->endDate, $this->companyName, 'A');
        }
        if (count($this->sectionBulanan)) {
            $sheets[] = new AttendanceReportSheet($this->sectionBulanan, $this->dates, $this->startDate, $this->endDate, $this->companyName, 'B');
        }

        return $sheets;
    }
}

class AttendanceReportSheet implements FromArray, ShouldAutoSize, WithEvents
{
    protected string $startDate;
    protected string $endDate;
    protected string $companyName;
    protected string $sheetLabel;
    protected array $dates;
    protected array $employees;
    protected array $rows;

    public function __construct(
        array $employees,
        array $dates,
        string $startDate,
        string $endDate,
        string $companyName = '',
        string $sheetLabel = ''
    ) {
        $this->employees   = $employees;
        $this->dates       = $dates;
        $this->startDate   = $startDate;
        $this->endDate     = $endDate;
        $this->companyName = $companyName;
        $this->sheetLabel  = $sheetLabel;

        $this->buildRows();
    }

    protected function buildRows(): void
    {
        $this->rows = [];
        $totalCols  = 2 + count($this->dates) * 2; // NIP + Nama + (status + jam) per date

        // Row 1: Company name (merged)
        $row1 = array_fill(0, $totalCols, '');
        $row1[0] = $this->companyName;
        $this->rows[] = $row1;

        // Row 2: Title
        $row2 = array_fill(0, $totalCols, '');
        $label = $this->sheetLabel === 'A' ? 'ALLIN, GUDANG, SOPIR' : 'BULANAN';
        $row2[0] = "LAPORAN ABSENSI KARYAWAN {$label}";
        $this->rows[] = $row2;

        // Row 3: Period
        $row3 = array_fill(0, $totalCols, '');
        $row3[0] = 'Periode: ' . $this->startDate . ' s/d ' . $this->endDate;
        $this->rows[] = $row3;

        // Row 4: Empty
        $this->rows[] = array_fill(0, $totalCols, '');

        // Row 5: Header row 1 (NIP, Nama, dates...)
        $row5 = ['NIP', 'Nama'];
        foreach ($this->dates as $date) {
            $row5[] = (string) $date->format('d');
            $row5[] = '';
        }
        $this->rows[] = $row5;

        // Row 6: Sub-header (No, No, day_name, OT/LM)
        $row6 = ['', ''];
        foreach ($this->dates as $date) {
            $row6[] = $date->translatedFormat('D');
            $row6[] = $date->isSunday() ? 'LM' : 'OT';
        }
        $this->rows[] = $row6;

        // Data rows
        foreach ($this->employees as $emp) {
            $row = [$emp['employee_code'], $emp['name']];
            foreach ($this->dates as $date) {
                $dateStr = $date->toDateString();
                $day = $emp['days'][$dateStr] ?? null;

                // Status
                $row[] = $day['status'] ?? '-';

                // Jam lembur (angka desimal)
                $menit = $date->isSunday() ? ($day['lm'] ?? 0) : ($day['lembur'] ?? 0);
                $jam   = $menit > 0 ? round($menit / 60, 1) : 0;
                $row[] = $jam > 0 ? str_replace('.', ',', (string) $jam) : '';
            }
            $this->rows[] = $row;
        }
    }

    public function title(): string
    {
        return $this->sheetLabel === 'A' ? 'A - ALLIN/GUDANG/SOPIR' : 'B - BULANAN';
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

                // ── Merge header: "NIP" row 5-6, "Nama" row 5-6 ──
                $sheet->mergeCells("A{$headerRow1}:A{$headerRow2}");
                $sheet->mergeCells("B{$headerRow1}:B{$headerRow2}");

                // ── Merge date headers: each date spans 2 cols ──
                for ($i = 0; $i < count($this->dates); $i++) {
                    $colStart = $this->colLetter(3 + $i * 2);
                    $colEnd   = $this->colLetter(3 + $i * 2 + 1);
                    $sheet->mergeCells("{$colStart}{$headerRow1}:{$colEnd}{$headerRow1}");
                }

                // ── Global border + font ──
                $sheet->getStyle("A1:{$lastColLetter}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                    'font'    => ['size' => 8, 'name' => 'Calibri'],
                ]);

                // ── Title row 1 (company) ──
                $sheet->getStyle("A1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // ── Title row 2 (LAPORAN) ──
                $sheet->getStyle("A2")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Title row 3 (periode) ──
                $sheet->getStyle("A3")->applyFromArray([
                    'font'      => ['size' => 8, 'color' => ['rgb' => '6B7280']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Header styling ──
                $headerRange = "A{$headerRow1}:{$lastColLetter}{$headerRow2}";
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($headerRow1)->setRowHeight(18);
                $sheet->getRowDimension($headerRow2)->setRowHeight(16);

                // ── Center NIP column ──
                $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ── Alternating row colors ──
                for ($row = $dataStartRow; $row <= $lastRow; $row++) {
                    if (($row - $dataStartRow) % 2 === 1) {
                        $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->setStartColor(new Color('F9FAFB'));
                    }
                }

                // ── Status & jam coloring per column ──
                for ($i = 0; $i < count($this->dates); $i++) {
                    $statusCol = $this->colLetter(3 + $i * 2);
                    $jamCol    = $this->colLetter(3 + $i * 2 + 1);

                    $sheet->getStyle("{$statusCol}{$dataStartRow}:{$jamCol}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    foreach ($this->employees as $r => $emp) {
                        $dateStr = $this->dates[$i]->toDateString();
                        $day     = $emp['days'][$dateStr] ?? null;
                        $status  = $day['status'] ?? '-';
                        $rowNum  = $dataStartRow + $r;

                        $colorMap = [
                            'H'   => ['bg' => 'D4EDDA', 'text' => '155724'],
                            'S'   => ['bg' => 'FFF3CD', 'text' => '856404'],
                            'I'   => ['bg' => 'E8DAEF', 'text' => '6C3483'],
                            'C'   => ['bg' => 'D6EAF8', 'text' => '1A5276'],
                            'L'   => ['bg' => 'FDEBD0', 'text' => '935116'],
                            'Off' => ['bg' => 'EAEDED', 'text' => '7F8C8D'],
                            'A'   => ['bg' => 'F8D7DA', 'text' => '721C24'],
                        ];

                        if (isset($colorMap[$status])) {
                            $c = $colorMap[$status];
                            $sheet->getStyle("{$statusCol}{$rowNum}")->applyFromArray([
                                'fill'  => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c['bg']]],
                                'font'  => ['color' => ['rgb' => $c['text']], 'bold' => $status === 'H'],
                            ]);
                        } elseif ($status === '-' || empty($status)) {
                            $sheet->getStyle("{$statusCol}{$rowNum}")->getFont()
                                ->setColor(new Color('D1D5DB'));
                        }

                        // Jam cell: bold + orange if > 0
                        $menit = $day ? ($this->dates[$i]->isSunday() ? ($day['lm'] ?? 0) : ($day['lembur'] ?? 0)) : 0;
                        if ($menit > 0) {
                            $sheet->getStyle("{$jamCol}{$rowNum}")->applyFromArray([
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
}
