<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export gabungan Section A, B, C dalam 1 sheet.
 * Layout vertikal: A (ALL IN) → B (BULANAN PRINT) → C (UANG MAKAN).
 *
 * Semua penulisan cell dilakukan manual via AfterSheet — tidak pakai FromArray
 * agar tidak ada konflik layout antar-section.
 */
class RekapKerjaCombinedExport implements WithTitle, WithEvents, WithColumnWidths
{
    protected array $allIn;
    protected array $bulananPrint;
    protected array $uangMakan;
    protected string $periodName;
    protected string $dateStart;
    protected string $dateEnd;

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
            'A' => 5,
            'B' => 28,
            'C' => 7,
            'D' => 16,
            'E' => 16,
            'F' => 16,
            'G' => 18,
            'H' => 16,
        ];
    }

    public function registerEvents(): array
    {
        $allIn        = $this->allIn;
        $bulananPrint = $this->bulananPrint;
        $uangMakan    = $this->uangMakan;
        $periodName   = $this->periodName;
        $dateStart    = $this->dateStart;
        $dateEnd      = $this->dateEnd;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($allIn, $bulananPrint, $uangMakan, $periodName, $dateStart, $dateEnd) {
                $sheet = $event->sheet->getDelegate();
                $row = 1;

                // ─── Section A: ALL IN ───
                $row = $this->writeSection(
                    $sheet, $row,
                    'SECTION A: ALL IN',
                    $allIn,
                    ['No', 'BAGIAN', 'JML', 'GAJI', 'LEMBUR', 'TOTAL', 'BPJS (TK+KS)', ''],
                    ['gaji', 'lembur', 'total', 'bpjs'],
                    ['D', 'E', 'F', 'G'],
                    'FFDBEAF8', 'FFF2F6FC'
                );

                // Spacer
                $row += 2;

                // ─── Section B: BULANAN PRINT ───
                $row = $this->writeSection(
                    $sheet, $row,
                    'SECTION B: BULANAN PRINT',
                    $bulananPrint,
                    ['No', 'BAGIAN', 'JML', 'GAJI', 'LEMBUR', 'TOTAL', 'BPJS (TK+KS)', ''],
                    ['gaji', 'lembur', 'total', 'bpjs'],
                    ['D', 'E', 'F', 'G'],
                    'FFD5F5E3', 'FFF2FCF5'
                );

                // Spacer
                $row += 2;

                // ─── Section C: UANG MAKAN ───
                $this->writeSection(
                    $sheet, $row,
                    'SECTION C: UANG MAKAN',
                    $uangMakan,
                    ['No', 'BAGIAN', 'JML', 'UANG MAKAN', 'LEMBUR SABTU', 'LEMBUR MINGGU', 'INSENTIF', 'TOTAL'],
                    ['uang_makan', 'lembur_sabtu', 'lembur_minggu', 'insentif', 'total'],
                    ['D', 'E', 'F', 'G', 'H'],
                    'FFFDEBD0', 'FFFFFBF0'
                );

                // Freeze
                $sheet->freezePane('B1');
            },
        ];
    }

    /**
     * Tulis satu section lengkap.
     *
     * @return int  row berikutnya setelah section selesai
     */
    private function writeSection($sheet, int $startRow, string $title, array $rows, array $headers, array $totalKeys, array $numCols, string $headerColor, string $zebraColor): int
    {
        $lastCol = 'H';
        $r = $startRow;

        // Title row
        $sheet->setCellValue("A{$r}", "REKAP KERJA — {$title}");
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;

        // Company
        $sheet->setCellValue("A{$r}", 'PT KEMILAU UNGARAN SUKSES');
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;

        // Periode
        $sheet->setCellValue("A{$r}", "Periode: {$this->dateStart} s/d {$this->dateEnd}");
        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $r++;

        // Blank
        $r++;

        // Column headers
        $headerRow = $r;
        foreach ($headers as $ci => $hdr) {
            $col = chr(65 + $ci); // A=65
            $sheet->setCellValue("{$col}{$r}", $hdr);
        }
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
        $r++;

        // Data rows
        $dataStart = $r;
        $dataEnd   = $dataStart;

        foreach ($rows as $i => $row) {
            $sheet->setCellValue("A{$r}", $i + 1);
            $sheet->setCellValue("B{$r}", $row['bagian'] ?? '-');
            $sheet->setCellValue("C{$r}", (int) ($row['jml'] ?? 0));
            foreach ($totalKeys as $ki => $key) {
                $col = $numCols[$ki];
                $sheet->setCellValue("{$col}{$r}", (float) ($row[$key] ?? 0));
            }
            $dataEnd = $r;
            $r++;
        }

        if ($dataEnd >= $dataStart) {
            // Borders
            $borderRange = "A{$headerRow}:{$lastCol}{$dataEnd}";
            $sheet->getStyle($borderRange)->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Alignment
            $sheet->getStyle("A{$dataStart}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$dataStart}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$dataStart}:C{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Number format
            foreach ($numCols as $col) {
                $numRange = "{$col}{$dataStart}:{$col}{$dataEnd}";
                $sheet->getStyle($numRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle($numRange)->getNumberFormat()->setFormatCode('#,##0');
            }

            // Zebra
            for ($dr = $dataStart; $dr <= $dataEnd; $dr++) {
                if (($dr - $dataStart) % 2 === 1) {
                    $sheet->getStyle("A{$dr}:{$lastCol}{$dr}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB($zebraColor);
                }
            }
        }

        // ─── TOTAL Row ───
        $r++; // blank row
        $totalRow = $r;

        // Hitung total
        $totals = [];
        foreach ($totalKeys as $ki => $key) {
            $totals[$key] = array_sum(array_column($rows, $key));
        }

        $sheet->setCellValue("A{$totalRow}", 'TOTAL BULANAN');
        $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
        foreach ($totalKeys as $ki => $key) {
            $col = $numCols[$ki];
            $sheet->setCellValue("{$col}{$totalRow}", $totals[$key]);
        }

        $totalRange = "A{$totalRow}:{$lastCol}{$totalRow}";
        $sheet->getStyle($totalRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8E8E8');
        $sheet->getStyle($totalRange)->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach ($numCols as $col) {
            $sheet->getStyle("{$col}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        return $totalRow + 1;
    }
}
