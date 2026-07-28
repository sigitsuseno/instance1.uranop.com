<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export gabungan Section A, B, C dalam 1 sheet.
 * Layout vertikal: A (ALL IN) → B (BULANAN PRINT) → C (UANG MAKAN).
 */
class RekapKerjaCombinedExport implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected array $allIn;
    protected array $bulananPrint;
    protected array $uangMakan;
    protected string $periodName;
    protected string $dateStart;
    protected string $dateEnd;
    protected string $lastCol = 'H';

    public function __construct(
        array $allIn,
        array $bulananPrint,
        array $uangMakan,
        string $periodName,
        string $dateStart,
        string $dateEnd
    ) {
        $this->allIn        = $allIn;
        $this->bulananPrint = $bulananPrint;
        $this->uangMakan    = $uangMakan;
        $this->periodName   = $periodName;
        $this->dateStart    = $dateStart;
        $this->dateEnd      = $dateEnd;
    }

    public function title(): string
    {
        return 'REKAP KERJA';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 28,  // BAGIAN
            'C' => 7,   // JML
            'D' => 16,  // GAJI / UANG MAKAN
            'E' => 16,  // LEMBUR / LEMBUR SABTU
            'F' => 16,  // TOTAL / LEMBUR MINGGU
            'G' => 18,  // BPJS / INSENTIF
            'H' => 16,  // (kosong utk A/B) / TOTAL (utk C)
        ];
    }

    public function headings(): array
    {
        // Headings kosong — di-handle manual via array()
        // Karena section heading berbeda-beda per-section.
        return [];
    }

    public function array(): array
    {
        $data = [];

        // ─── Section A: ALL IN ───
        $data[] = ['REKAP KERJA — SECTION A: ALL IN'];
        $data[] = ['PT KEMILAU UNGARAN SUKSES'];
        $data[] = ["Periode: {$this->dateStart} s/d {$this->dateEnd}"];
        $data[] = [''];
        $data[] = [
            'No', 'BAGIAN', 'JML',
            'GAJI',
            'LEMBUR',
            'TOTAL',
            'BPJS (TK+KS)',
            '',
        ];

        $totalsA = [
            'gaji'   => array_sum(array_column($this->allIn, 'gaji')),
            'lembur' => array_sum(array_column($this->allIn, 'lembur')),
            'total'  => array_sum(array_column($this->allIn, 'total')),
            'bpjs'   => array_sum(array_column($this->allIn, 'bpjs')),
        ];

        foreach ($this->allIn as $i => $row) {
            $data[] = [
                $i + 1,
                $row['bagian'] ?? '-',
                (int) ($row['jml'] ?? 0),
                (float) ($row['gaji'] ?? 0),
                (float) ($row['lembur'] ?? 0),
                (float) ($row['total'] ?? 0),
                (float) ($row['bpjs'] ?? 0),
                '',
            ];
        }

        if (count($this->allIn) > 0) {
            $data[] = [''];
            $data[] = [
                'TOTAL BULANAN', '', '',
                $totalsA['gaji'],
                $totalsA['lembur'],
                $totalsA['total'],
                $totalsA['bpjs'],
                '',
            ];
        }

        // ─── Spacer ───
        $data[] = [''];
        $data[] = [''];

        // ─── Section B: BULANAN PRINT ───
        $data[] = ['REKAP KERJA — SECTION B: BULANAN PRINT'];
        $data[] = ['PT KEMILAU UNGARAN SUKSES'];
        $data[] = ["Periode: {$this->dateStart} s/d {$this->dateEnd}"];
        $data[] = [''];
        $data[] = [
            'No', 'BAGIAN', 'JML',
            'GAJI',
            'LEMBUR',
            'TOTAL',
            'BPJS (TK+KS)',
            '',
        ];

        $totalsB = [
            'gaji'   => array_sum(array_column($this->bulananPrint, 'gaji')),
            'lembur' => array_sum(array_column($this->bulananPrint, 'lembur')),
            'total'  => array_sum(array_column($this->bulananPrint, 'total')),
            'bpjs'   => array_sum(array_column($this->bulananPrint, 'bpjs')),
        ];

        foreach ($this->bulananPrint as $i => $row) {
            $data[] = [
                $i + 1,
                $row['bagian'] ?? '-',
                (int) ($row['jml'] ?? 0),
                (float) ($row['gaji'] ?? 0),
                (float) ($row['lembur'] ?? 0),
                (float) ($row['total'] ?? 0),
                (float) ($row['bpjs'] ?? 0),
                '',
            ];
        }

        if (count($this->bulananPrint) > 0) {
            $data[] = [''];
            $data[] = [
                'TOTAL BULANAN', '', '',
                $totalsB['gaji'],
                $totalsB['lembur'],
                $totalsB['total'],
                $totalsB['bpjs'],
                '',
            ];
        }

        // ─── Spacer ───
        $data[] = [''];
        $data[] = [''];

        // ─── Section C: UANG MAKAN ───
        $data[] = ['REKAP KERJA — SECTION C: UANG MAKAN'];
        $data[] = ['PT KEMILAU UNGARAN SUKSES'];
        $data[] = ["Periode: {$this->dateStart} s/d {$this->dateEnd}"];
        $data[] = [''];
        $data[] = [
            'No', 'BAGIAN', 'JML',
            'UANG MAKAN',
            'LEMBUR SABTU',
            'LEMBUR MINGGU',
            'INSENTIF',
            'TOTAL',
        ];

        $totalsC = [
            'uang_makan'    => array_sum(array_column($this->uangMakan, 'uang_makan')),
            'lembur_sabtu'  => array_sum(array_column($this->uangMakan, 'lembur_sabtu')),
            'lembur_minggu' => array_sum(array_column($this->uangMakan, 'lembur_minggu')),
            'insentif'      => array_sum(array_column($this->uangMakan, 'insentif')),
            'total'         => array_sum(array_column($this->uangMakan, 'total')),
        ];

        foreach ($this->uangMakan as $i => $row) {
            $data[] = [
                $i + 1,
                $row['bagian'] ?? '-',
                (int) ($row['jml'] ?? 0),
                (float) ($row['uang_makan'] ?? 0),
                (float) ($row['lembur_sabtu'] ?? 0),
                (float) ($row['lembur_minggu'] ?? 0),
                (float) ($row['insentif'] ?? 0),
                (float) ($row['total'] ?? 0),
            ];
        }

        if (count($this->uangMakan) > 0) {
            $data[] = [''];
            $data[] = [
                'TOTAL BULANAN', '', '',
                $totalsC['uang_makan'],
                $totalsC['lembur_sabtu'],
                $totalsC['lembur_minggu'],
                $totalsC['insentif'],
                $totalsC['total'],
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = $this->lastCol;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol) {
                $sheet = $event->sheet->getDelegate();

                // ─── Scan rows untuk styling per-section ───
                $highestRow = $sheet->getHighestRow();
                $currentRow = 1;

                while ($currentRow <= $highestRow) {
                    $valA = (string) $sheet->getCell("A{$currentRow}")->getValue();

                    // Deteksi section header
                    if (str_contains($valA, 'SECTION A')) {
                        $currentRow = $this->styleSection(
                            $sheet, $currentRow, $lastCol,
                            'FFDBEAF8',   // blue bg header
                            'FFF2F6FC',   // blue zebra
                            ['gaji' => 'D', 'lembur' => 'E', 'total' => 'F', 'bpjs' => 'G'],
                            'A,B,C'
                        );
                        continue;
                    }

                    if (str_contains($valA, 'SECTION B')) {
                        $currentRow = $this->styleSection(
                            $sheet, $currentRow, $lastCol,
                            'FFD5F5E3',   // green bg header
                            'FFF2FCF5',   // green zebra
                            ['gaji' => 'D', 'lembur' => 'E', 'total' => 'F', 'bpjs' => 'G'],
                            'A,B,C'
                        );
                        continue;
                    }

                    if (str_contains($valA, 'SECTION C')) {
                        $currentRow = $this->styleSection(
                            $sheet, $currentRow, $lastCol,
                            'FFFDEBD0',   // amber bg header
                            'FFFFFBF0',   // amber zebra
                            ['uang_makan' => 'D', 'lembur_sabtu' => 'E', 'lembur_minggu' => 'F', 'insentif' => 'G', 'total' => 'H'],
                            'A,B,C'
                        );
                        continue;
                    }

                    $currentRow++;
                }

                // Freeze pane
                $sheet->freezePane('B1');
            },
        ];
    }

    /**
     * Style satu section dari $sectionTitleRow sampai total row-nya.
     */
    private function styleSection($sheet, int $sectionTitleRow, string $lastCol, string $headerColor, string $zebraColor, array $numCols, string $mergeTotalCols): int
    {
        $highestRow = $sheet->getHighestRow();

        // Row 1 dari section = title (merge + bold)
        $sheet->mergeCells("A{$sectionTitleRow}:{$lastCol}{$sectionTitleRow}");
        $sheet->getStyle("A{$sectionTitleRow}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$sectionTitleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Row 2 = company name
        $r2 = $sectionTitleRow + 1;
        $sheet->mergeCells("A{$r2}:{$lastCol}{$r2}");
        $sheet->getStyle("A{$r2}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$r2}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Row 3 = periode
        $r3 = $sectionTitleRow + 2;
        $sheet->mergeCells("A{$r3}:{$lastCol}{$r3}");
        $sheet->getStyle("A{$r3}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$r3}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Row 4 = blank, Row 5 = column headers
        $headerRow = $sectionTitleRow + 4;
        $dataStart = $headerRow + 1;

        // Cari end of data: next empty row then total row
        // Format: data → blank row → TOTAL row → blank rows
        $dataEnd = $dataStart;
        for ($r = $dataStart; $r <= $highestRow; $r++) {
            $a = (string) $sheet->getCell("A{$r}")->getValue();
            $b = (string) $sheet->getCell("B{$r}")->getValue();
            if ($a === '' && $b === '') {
                // Blank row — next should be total or end
                $totalRow = $r + 1;
                $totalLabel = (string) $sheet->getCell("A{$totalRow}")->getValue();
                if (str_contains($totalLabel, 'TOTAL BULANAN')) {
                    $dataEnd = $r - 1;
                    break;
                }
                // Jika bukan total, berarti section ini kosong
                $dataEnd = $r - 1;
                break;
            }
            $dataEnd = $r;
        }

        // ─── Column Headers ───
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($headerColor);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        // ─── Data alignment ───
        $sheet->getStyle("A{$dataStart}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C{$dataStart}:C{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ─── Number columns ───
        $numRange = implode(',', array_map(fn($col) => "{$col}{$dataStart}:{$col}{$dataEnd}", array_values($numCols)));
        $sheet->getStyle($numRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('#,##0');

        // ─── Borders ───
        $borderRange = "A{$headerRow}:{$lastCol}{$dataEnd}";
        $sheet->getStyle($borderRange)->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ─── Zebra ───
        for ($r = $dataStart; $r <= $dataEnd; $r++) {
            if (($r - $dataStart) % 2 === 1) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($zebraColor);
            }
        }

        // ─── TOTAL Row (if exists) ───
        $nextRow = $dataEnd + 2;
        $totalLabel = (string) $sheet->getCell("A{$nextRow}")->getValue();
        if ($dataEnd >= $dataStart && str_contains($totalLabel, 'TOTAL BULANAN')) {
            $sheet->mergeCells("A{$nextRow}:C{$nextRow}");
            $totalRange = "A{$nextRow}:{$lastCol}{$nextRow}";
            $sheet->getStyle($totalRange)->getFont()->setBold(true);
            $sheet->getStyle($totalRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8E8E8');
            $sheet->getStyle($totalRange)->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A{$nextRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Number cols total
            foreach ($numCols as $col) {
                $sheet->getStyle("{$col}{$nextRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$col}{$nextRow}")->getNumberFormat()->setFormatCode('#,##0');
            }

            // Return pointer setelah total row
            return $nextRow + 1;
        }

        // Return pointer setelah data end
        return $dataEnd + 1;
    }
}
