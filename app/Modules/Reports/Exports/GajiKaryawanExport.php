<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GajiKaryawanExport implements WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected $secAData;
    protected $secBData;
    protected $periodName;

    // Track writing position
    protected $row = 1;

    // Columns that carry employee data
    const COLS = ['employee_code','name','gender','department','position','join_year','premi','gaji_pokok','tj_masa_kerja','hari_kerja','lm','lembur_count','gaji','upah_lembur','revisi','tunjangan','premi_hadir','pblt','total','bpjs_tk','bpjs_ks','bpjs_pen','cashbon','pph','gaji_bersih'];
    const NUM_COLS = ['premi','gaji_pokok','tj_masa_kerja','hari_kerja','lm','lembur_count','gaji','upah_lembur','revisi','tunjangan','premi_hadir','pblt','total','bpjs_tk','bpjs_ks','bpjs_pen','cashbon','pph','gaji_bersih'];

    public function __construct(array $secAData, array $secBData, string $periodName)
    {
        $this->secAData = $secAData;
        $this->secBData = $secBData;
        $this->periodName = $periodName;
    }

    public function headings(): array
    {
        // Not used — we write manually in registerEvents
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 10, 'C' => 25, 'D' => 5,  'E' => 15, 'F' => 15,
            'G' => 10, 'H' => 12, 'I' => 12, 'J' => 12, 'K' => 5,  'L' => 5,
            'M' => 8,  'N' => 12, 'O' => 12, 'P' => 12, 'Q' => 12, 'R' => 12,
            'S' => 10, 'T' => 15, 'U' => 12, 'V' => 12, 'W' => 12, 'X' => 12,
            'Y' => 12, 'Z' => 15,
        ];
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
                $this->writeSheet($sheet);
            },
        ];
    }

    protected function writeSheet(Worksheet $sheet): void
    {
        $this->row = 1;

        // ── Title Row ──
        $sheet->setCellValue('A1', 'LAPORAN GAJI KARYAWAN');
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('M1', strtoupper($this->periodName));
        $sheet->mergeCells('M1:Z1');
        $sheet->getStyle('M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('M1')->getFont()->setBold(true)->setSize(12);

        $this->row = 2;

        // ── Column Headers ──
        $headers = ['No', 'ID No', 'NAMA KARYAWAN', 'L/P', 'BAGIAN', 'JABATAN', 'THN MSK',
                     'PREMI', 'GAJI POKOK', 'TJ. MK', 'HK', 'L/M', 'LBR JAM', 'GAJI',
                     'LEMBUR', 'REVISI', 'TUNJANGAN', 'PR. HADIR', 'PBLT', 'TOTAL',
                     'BPJS TK', 'BPJS KES', 'BPJS PEN', 'CASH BON', 'PPH', 'TRIMA'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue("{$col}{$this->row}", $h);
            $col++;
        }
        $sheet->getStyle("A{$this->row}:Z{$this->row}")->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("A{$this->row}:Z{$this->row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $headerRow = $this->row;
        $this->row++;

        // ── Section A ──
        $secAEnd = $this->writeSection($sheet, 'A. KARYAWAN ALL IN', $this->secAData, 'A');
        // ── Section B ──
        $secBEnd = $this->writeSection($sheet, 'B. KARYAWAN BULANAN PRINT', $this->secBData, 'B');

        $lastRow = max($secBEnd, $headerRow + 1);

        // ── Grand Total ──
        $allData = array_merge($this->secAData, $this->secBData);
        if (count($allData) > 0) {
            $this->row++;
            $sheet->setCellValue("A{$this->row}", '');
            $sheet->mergeCells("A{$this->row}:G{$this->row}");
            $sheet->setCellValue("G{$this->row}", 'TOTAL KESELURUHAN');
            $sheet->getStyle("G{$this->row}")->getFont()->setBold(true);
            $sheet->getStyle("G{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $this->writeTotalsRow($sheet, $this->row, $allData, 'primary');
            $lastRow = $this->row;
        }

        // ── Borders ──
        $sheet->getStyle("A{$headerRow}:Z{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ── Number format ──
        for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
            // Column H (8) onwards: number format
            $sheet->getStyle("H{$r}:Z{$r}")->getNumberFormat()->setFormatCode('#,##0');
        }

        // ── Freeze Pane ──
        $sheet->freezePane('D' . ($headerRow + 1));
    }

    protected function writeSection(Worksheet $sheet, string $label, array $data, string $sectionKey): int
    {
        if (empty($data)) {
            return $this->row - 1;
        }

        // Section header row
        $sheet->setCellValue("A{$this->row}", $label . ' (' . count($data) . ' Karyawan)');
        $sheet->mergeCells("A{$this->row}:Z{$this->row}");
        $style = $sheet->getStyle("A{$this->row}:Z{$this->row}");
        $style->getFont()->setBold(true)->setSize(10);
        $style->getFill()->setFillType(Fill::FILL_SOLID);
        $style->getFill()->getStartColor()->setARGB('FFE8F5E9'); // light green
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sectionStartRow = $this->row;
        $this->row++;

        // Data rows
        $num = 1;
        foreach ($data as $row) {
            $sheet->setCellValue("A{$this->row}", $num++);
            $sheet->setCellValue("B{$this->row}", $row['employee_code'] ?? '-');
            $sheet->setCellValue("C{$this->row}", $row['name'] ?? '-');
            $sheet->setCellValue("D{$this->row}", $row['gender'] ?? '-');
            $sheet->setCellValue("E{$this->row}", $row['department'] ?? '-');
            $sheet->setCellValue("F{$this->row}", $row['position'] ?? '-');
            $sheet->setCellValue("G{$this->row}", $row['join_year'] ?? '-');

            $col = 'H';
            foreach (self::NUM_COLS as $key) {
                $val = $row[$key] ?? 0;
                // Convert LM and lembur_count from minutes to hours
                if ($key === 'lm' || $key === 'lembur_count') {
                    $val = round((float) $val / 60, 1);
                }
                $sheet->setCellValue("{$col}{$this->row}", is_numeric($val) ? (float) $val : 0);
                $col++;
            }

            $this->row++;
        }

        // Section total row
        $sheet->setCellValue("A{$this->row}", '');
        $sheet->mergeCells("A{$this->row}:G{$this->row}");
        $sheet->setCellValue("G{$this->row}", 'TOTAL ' . $label);
        $sheet->getStyle("G{$this->row}")->getFont()->setBold(true);
        $sheet->getStyle("G{$this->row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $this->writeTotalsRow($sheet, $this->row, $data, 'section');

        $sectionEndRow = $this->row;
        $this->row++;

        return $sectionEndRow;
    }

    protected function writeTotalsRow(Worksheet $sheet, int $row, array $data, string $type): void
    {
        $totals = $this->computeTotals($data);

        $col = 'H';
        foreach (self::NUM_COLS as $key) {
            $val = $totals[$key] ?? 0;
            $sheet->setCellValue("{$col}{$row}", $val);
            $col++;
        }

        $style = $sheet->getStyle("A{$row}:Z{$row}");
        $style->getFont()->setBold(true);

        if ($type === 'primary') {
            $style->getFill()->setFillType(Fill::FILL_SOLID);
            $style->getFill()->getStartColor()->setARGB('FFE3F2FD'); // light blue
        }
    }

    protected function computeTotals(array $data): array
    {
        $totals = [];
        foreach (self::NUM_COLS as $key) {
            $sum = array_sum(array_map(fn($r) => (float) ($r[$key] ?? 0), $data));
            // Convert LM and lembur_count from minutes to hours
            if ($key === 'lm' || $key === 'lembur_count') {
                $sum = round($sum / 60, 1);
            }
            $totals[$key] = $sum;
        }
        return $totals;
    }
}
