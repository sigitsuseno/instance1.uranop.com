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

class UangMakanHarianExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $dates;
    protected $label;
    protected $rowNumber = 0;

    /** Number of fixed columns before date columns */
    protected const FIXED_COLS = 7;
    /** Number of sub-columns per date */
    protected const SUB_COLS = 6;

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
        $gender = ($row['gender'] ?? '') === 'male' ? 'L' : (($row['gender'] ?? '') === 'female' ? 'P' : ($row['gender'] ?? ''));

        $fixed = [
            $this->rowNumber,
            $row['name'] ?? '',
            $row['jabatan'] ?? '',
            $gender,
            $row['tj_mk'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['upah_lembur_per_jam'] ?? 0,
        ];

        $dayCells = [];
        $days = $row['days'] ?? [];
        foreach ($this->dates as $dateStr) {
            $d = $days[$dateStr] ?? null;
            $dayCells[] = $d['kode'] ?? '';
            $dayCells[] = $d['ha'] ?? '';
            $dayCells[] = ($d['upah_per_hari'] ?? 0) > 0 ? $d['upah_per_hari'] : 0;
            $dayCells[] = ($d['lm'] ?? 0) > 0 ? $d['lm'] : '';
            $dayCells[] = ($d['lembur'] ?? 0) > 0 ? $d['lembur'] : '';
            $dayCells[] = ($d['nominal'] ?? 0) > 0 ? $d['nominal'] : 0;
        }

        return array_merge($fixed, $dayCells);
    }

    public function headings(): array
    {
        Carbon::setLocale('id');

        // Row 1: Title (will be merged in AfterSheet)
        $row1 = ['LAPORAN UANG MAKAN HARIAN — ' . strtoupper($this->label)];

        // Row 2: Empty
        $row2 = [''];

        // Row 3: Main headers
        $row3 = ['No', 'Nama', 'Bagian / Jabatan', 'L/P', 'Tj. Masa Kerja', 'Tunjangan', 'Upah Lembur Per Jam'];
        foreach ($this->dates as $dateStr) {
            $formatted = Carbon::parse($dateStr)->translatedFormat('D, d/m');
            $row3[] = strtoupper($formatted);
            // Pad with empty cells for the sub-headers (colspan handled in AfterSheet)
            for ($i = 1; $i < self::SUB_COLS; $i++) {
                $row3[] = '';
            }
        }

        // Row 4: Sub-headers
        $row4 = ['', '', '', '', '', '', '']; // Empty for fixed cols
        foreach ($this->dates as $dateStr) {
            $row4[] = 'Kode';
            $row4[] = 'H/A';
            $row4[] = 'Upah/Hari';
            $row4[] = 'L/M';
            $row4[] = 'Lembur';
            $row4[] = 'Nominal';
        }

        return [$row1, $row2, $row3, $row4];
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,  'B' => 30, 'C' => 22, 'D' => 5,
            'E' => 16, 'F' => 16, 'G' => 16,
        ];

        $col = 'H';
        foreach ($this->dates as $i => $dateStr) {
            $widths[$col] = 8;  $col = self::nextCol($col); // Kode
            $widths[$col] = 6;  $col = self::nextCol($col); // H/A
            $widths[$col] = 14; $col = self::nextCol($col); // Upah/Hari
            $widths[$col] = 8;  $col = self::nextCol($col); // L/M
            $widths[$col] = 8;  $col = self::nextCol($col); // Lembur
            $widths[$col] = 14; $col = self::nextCol($col); // Nominal
        }

        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        // Basic styles are handled here; complex merges are in registerEvents
        return [];
    }

    public function registerEvents(): array
    {
        $totalCols = self::FIXED_COLS + (self::SUB_COLS * count($this->dates));
        $lastCol = self::colLetter($totalCols);
        $dataStartRow = 5; // Row 1=title, 2=empty, 3=main header, 4=sub header
        $lastRow = count($this->data) + $dataStartRow - 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($totalCols, $lastCol, $dataStartRow, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                // --- Title row ---
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // --- Merge fixed columns in row 3 and 4 ---
                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->mergeCells('C3:C4');
                $sheet->mergeCells('D3:D4');
                $sheet->mergeCells('E3:E4');
                $sheet->mergeCells('F3:F4');
                $sheet->mergeCells('G3:G4');

                // --- Merge date headers in row 3 ---
                $col = self::FIXED_COLS + 1; // 1-based, first date col = H (8)
                foreach ($this->dates as $dateStr) {
                    $start = self::colLetter($col);
                    $end   = self::colLetter($col + self::SUB_COLS - 1);
                    $sheet->mergeCells("{$start}3:{$end}3");
                    $col += self::SUB_COLS;
                }

                // --- Style row 3 & 4 headers ---
                $sheet->getStyle("A3:{$lastCol}4")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A3:{$lastCol}4")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A3:{$lastCol}4")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Date header row 3 — blue tint
                $firstDateCol = self::colLetter(self::FIXED_COLS + 1);
                $sheet->getStyle("{$firstDateCol}3:{$lastCol}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDBEAFE');

                // --- Borders ---
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // --- Number formats ---
                // E (Tj.MK) through G (Upah Lbr/Jam): #,##0.00
                $sheet->getStyle("E{$dataStartRow}:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Nominal & Upah/Hari columns: last sub-col + sub-col 3
                $colOffset = self::FIXED_COLS;
                foreach ($this->dates as $i => $dateStr) {
                    $upahCol = self::colLetter($colOffset + 3);   // Upah/Hari
                    $nominalCol = self::colLetter($colOffset + self::SUB_COLS); // Nominal
                    $sheet->getStyle("{$upahCol}{$dataStartRow}:{$upahCol}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("{$nominalCol}{$dataStartRow}:{$nominalCol}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $colOffset += self::SUB_COLS;
                }

                // --- Alignment ---
                $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Center all day sub-columns except upah/hari & nominal
                $colOffset = self::FIXED_COLS;
                foreach ($this->dates as $dateStr) {
                    $kodeCol    = self::colLetter($colOffset + 1);
                    $haCol      = self::colLetter($colOffset + 2);
                    $upahCol    = self::colLetter($colOffset + 3);
                    $lmCol      = self::colLetter($colOffset + 4);
                    $lemburCol  = self::colLetter($colOffset + 5);
                    $nominalCol = self::colLetter($colOffset + 6);

                    // Center: Kode, H/A, L/M, Lembur
                    $sheet->getStyle("{$kodeCol}{$dataStartRow}:{$lemburCol}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    // Right: Upah/Hari, Nominal
                    $sheet->getStyle("{$upahCol}{$dataStartRow}:{$upahCol}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("{$nominalCol}{$dataStartRow}:{$nominalCol}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $colOffset += self::SUB_COLS;
                }

                // --- Freeze pane ---
                $sheet->freezePane('C5');
            },
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────

    /** Convert 1-based column index to letter (A, B, ..., Z, AA, AB, ...) */
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

    /** Get next column letter */
    protected static function nextCol(string $col): string
    {
        return self::colLetter(self::colIndex($col) + 1);
    }

    /** Convert column letter to 1-based index */
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
