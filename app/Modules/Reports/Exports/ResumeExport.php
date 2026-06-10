<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class ResumeExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $dates;
    protected $label;
    protected $rowNumber = 0;

    protected const FIXED_COLS = 4; // No, Bagian, L, P
    protected const SUB_COLS = 2;   // Hari Kerja, Overtime
    protected const TAIL_COLS = 3;  // Total Hari Kerja, Total Overtime, Total Terima

    public function __construct($data, $dates, $label)
    {
        $this->data  = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->dates = $dates;
        $this->label = $label;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $this->rowNumber++;

        $fixed = [
            $this->rowNumber,
            $row['bagian'] ?? '',
            $row['l'] ?? 0,
            $row['p'] ?? 0,
        ];

        $dayCells = [];
        $days = $row['days'] ?? [];
        foreach ($this->dates as $dateStr) {
            $d = $days[$dateStr] ?? ['hari_kerja' => 0, 'overtime' => 0];
            $dayCells[] = ($d['hari_kerja'] ?? 0) > 0 ? $d['hari_kerja'] : 0;
            $dayCells[] = ($d['overtime'] ?? 0) > 0 ? $d['overtime'] : 0;
        }

        $tail = [
            $row['total_hari_kerja'] ?? 0,
            $row['total_overtime'] ?? 0,
            $row['total_terima'] ?? 0,
        ];

        return array_merge($fixed, $dayCells, $tail);
    }

    public function headings(): array
    {
        Carbon::setLocale('id');

        $row1 = ['RESUME OVERTIME — ' . strtoupper($this->label)];
        $row2 = [''];

        // Row 3: Main headers
        $row3 = ['No', 'Bagian', 'L', 'P'];
        foreach ($this->dates as $dateStr) {
            $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
            $row3[] = strtoupper($formatted);
            $row3[] = ''; // placeholder for colspan
        }
        $row3[] = 'Total Hari Kerja';
        $row3[] = 'Total Overtime';
        $row3[] = 'Total Terima';

        // Row 4: Sub-headers
        $row4 = ['', '', '', ''];
        foreach ($this->dates as $dateStr) {
            $row4[] = 'Hari Kerja';
            $row4[] = 'Overtime';
        }
        $row4[] = '';
        $row4[] = '';
        $row4[] = '';

        return [$row1, $row2, $row3, $row4];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,  'B' => 24, 'C' => 5, 'D' => 5,
        ];

        $col = 'E';
        foreach ($this->dates as $dateStr) {
            $widths[$col] = 14; $col = self::nextCol($col); // Hari Kerja
            $widths[$col] = 14; $col = self::nextCol($col); // Overtime
        }

        // Tail columns
        $widths[$col] = 16; $col = self::nextCol($col); // Total Hari Kerja
        $widths[$col] = 16; $col = self::nextCol($col); // Total Overtime
        $widths[$col] = 16; // Total Terima

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates)) + self::TAIL_COLS;
        $lastCol = self::colLetter($totalCols);
        $dataStartRow = 5;
        $lastRow = count($this->data) + $dataStartRow - 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($totalCols, $lastCol, $dataStartRow, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                // Title
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Merge date headers in row 3
                $col = self::FIXED_COLS + 1; // E = 5
                foreach ($this->dates as $dateStr) {
                    $start = self::colLetter($col);
                    $end   = self::colLetter($col + self::SUB_COLS - 1);
                    $sheet->mergeCells("{$start}3:{$end}3");
                    $col += self::SUB_COLS;
                }

                // Style headers
                $sheet->getStyle("A3:{$lastCol}4")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A3:{$lastCol}4")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A3:{$lastCol}4")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Date header row 3 — blue tint
                $firstDateCol = self::colLetter(self::FIXED_COLS + 1);
                $lastDateCol = self::colLetter(self::FIXED_COLS + (self::SUB_COLS * count($this->dates)));
                $sheet->getStyle("{$firstDateCol}3:{$lastDateCol}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');

                // Borders
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Number formats: all monetary columns (date sub-cols + tail)
                $monetaryStartCol = self::colLetter(self::FIXED_COLS + 1);
                $sheet->getStyle("{$monetaryStartCol}{$dataStartRow}:{$lastCol}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // Alignment
                $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Right-align all monetary columns
                $sheet->getStyle("{$monetaryStartCol}{$dataStartRow}:{$lastCol}{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Bold total columns
                $totalStartCol = self::colLetter($totalCols - self::TAIL_COLS + 1);
                $sheet->getStyle("{$totalStartCol}{$dataStartRow}:{$lastCol}{$lastRow}")
                    ->getFont()->setBold(true);

                // Freeze pane
                $sheet->freezePane('C5');
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
