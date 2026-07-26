<?php

namespace App\Modules\Employee\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

/**
 * Export Laporan BPJS — sesuai format sample_laporan_bpjs.xlsx
 * Kolom: No | Nama | Thn Masuk | Masa Kerja | Gaji Pokok | Tunj MK | Tunjangan | Dasar BPJS
 *         | No KPJ TK | No KPJ KES
 *         | [Perusahaan] JHT | JKM | JKK | Total TK | Pensiun | KES
 *         | [Karyawan] JHT | Pensiun | KES
 *         | Total BPJS TK | BPJS KES
 */
class BpjsReportExport implements FromArray, WithEvents
{
    protected array  $groups;      // array of ['name' => ..., 'records' => [...]]
    protected string $periodName;

    // Column indices (1-based for PhpSpreadsheet)
    const COL_NO          = 'A';
    const COL_NAMA        = 'B';
    const COL_THN_MASUK   = 'C';
    const COL_MASA_KERJA  = 'D';
    const COL_GAJI_POKOK  = 'E';
    const COL_TUNJ_MK     = 'F';
    const COL_TUNJANGAN   = 'G';
    const COL_DASAR       = 'H';
    const COL_KPJ_TK      = 'I';
    const COL_KPJ_KES     = 'J';
    // Perusahaan
    const COL_P_JHT       = 'K';
    const COL_P_JKM       = 'L';
    const COL_P_JKK       = 'M';
    const COL_P_TOTAL_TK  = 'N';
    const COL_P_JP        = 'O';
    const COL_P_KES       = 'P';
    // Karyawan
    const COL_E_JHT       = 'Q';
    const COL_E_JP        = 'R';
    const COL_E_KES       = 'S';
    // Grand
    const COL_TOTAL_TK    = 'T';
    const COL_TOTAL_KES   = 'U';
    const LAST_COL        = 'U';

    public function __construct(array $groups, string $periodName)
    {
        $this->groups     = $groups;
        $this->periodName = $periodName;
    }

    public function array(): array
    {
        return [[]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->buildSheet($sheet);
            },
        ];
    }

    protected function buildSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        // Column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(18);
        // Perusahaan cols
        foreach (['K','L','M','N','O','P'] as $c) {
            $sheet->getColumnDimension($c)->setWidth(13);
        }
        // Karyawan cols
        foreach (['Q','R','S'] as $c) {
            $sheet->getColumnDimension($c)->setWidth(13);
        }
        $sheet->getColumnDimension('T')->setWidth(14);
        $sheet->getColumnDimension('U')->setWidth(14);

        // ── Title Row ─────────────────────────────────────────────
        $row = 1;
        $sheet->setCellValue("A{$row}", 'LAPORAN IURAN BPJS | PERIODE: ' . strtoupper($this->periodName));
        $sheet->mergeCells("A{$row}:" . self::LAST_COL . "{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Grand totals accumulator
        $grandTotals = array_fill_keys([
            'gaji_pokok','tj_masa_kerja','tunjangan','bpjs_base_salary',
            'employer_jht','employer_jkm','employer_jkk','employer_tk_total','employer_jp','employer_kesehatan',
            'employee_jht','employee_jp','employee_kesehatan',
            'total_tk','total_kes',
        ], 0);

        // ── Render each group ─────────────────────────────────────
        foreach ($this->groups as $group) {
            $row = $this->renderGroup($sheet, $row, $group, $grandTotals);
            $row++; // blank row between groups
        }

        // ── Grand Total ────────────────────────────────────────────
        $row = $this->renderGrandTotal($sheet, $row, $grandTotals);
    }

    protected function renderGroup(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $startRow,
        array $group,
        array &$grandTotals
    ): int {
        $row = $startRow;

        // Group title
        $sheet->setCellValue("A{$row}", strtoupper($group['name']));
        $sheet->mergeCells("A{$row}:" . self::LAST_COL . "{$row}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E3A5F']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        // Header row 1 (grouped)
        $this->writeHeaderRow1($sheet, $row);
        $row++;

        // Header row 2 (sub-columns)
        $this->writeHeaderRow2($sheet, $row);
        $row++;

        // Data rows
        $no = 1;
        $groupTotals = array_fill_keys([
            'gaji_pokok','tj_masa_kerja','tunjangan','bpjs_base_salary',
            'employer_jht','employer_jkm','employer_jkk','employer_tk_total','employer_jp','employer_kesehatan',
            'employee_jht','employee_jp','employee_kesehatan',
            'total_tk','total_kes',
        ], 0);

        $dataStartRow = $row;

        foreach ($group['records'] as $r) {
            $totalTk  = $this->calcTotalTk($r);
            $totalKes = $this->calcTotalKes($r);

            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValue("B{$row}", $r['employee']['name'] ?? '-');
            $sheet->setCellValue("C{$row}", $r['join_year'] ?? '-');
            $sheet->setCellValue("D{$row}", $r['masa_kerja'] ?? 0);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->setCellValue("E{$row}", (float)($r['gaji_pokok'] ?? 0));
            $sheet->setCellValue("F{$row}", (float)($r['tj_masa_kerja'] ?? 0));
            $sheet->setCellValue("G{$row}", (float)($r['tunjangan'] ?? 0));
            $sheet->setCellValue("H{$row}", (float)($r['bpjs_base_salary'] ?? 0));
            $sheet->setCellValue("I{$row}", $r['kpj_tk'] ?? '-');
            $sheet->setCellValue("J{$row}", $r['kpj_ks'] ?? '-');
            // Perusahaan
            $sheet->setCellValue("K{$row}", (float)($r['employer_jht'] ?? 0));
            $sheet->setCellValue("L{$row}", (float)($r['employer_jkm'] ?? 0));
            $sheet->setCellValue("M{$row}", (float)($r['employer_jkk'] ?? 0));
            $sheet->setCellValue("N{$row}", (float)($r['employer_tk_total'] ?? 0));
            $sheet->setCellValue("O{$row}", (float)($r['employer_jp'] ?? 0));
            $sheet->setCellValue("P{$row}", (float)($r['employer_kesehatan'] ?? 0));
            // Karyawan
            $sheet->setCellValue("Q{$row}", (float)($r['employee_jht'] ?? 0));
            $sheet->setCellValue("R{$row}", (float)($r['employee_jp'] ?? 0));
            $sheet->setCellValue("S{$row}", (float)($r['employee_kesehatan'] ?? 0));
            // Grand
            $sheet->setCellValue("T{$row}", $totalTk);
            $sheet->setCellValue("U{$row}", $totalKes);

            // Number format for money columns
            $moneyFmt = '#,##0';
            foreach (['E','F','G','H','K','L','M','N','O','P','Q','R','S','T','U'] as $c) {
                $sheet->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            }

            // Accumulate
            foreach (['gaji_pokok','tj_masa_kerja','tunjangan','bpjs_base_salary',
                      'employer_jht','employer_jkm','employer_jkk','employer_tk_total',
                      'employer_jp','employer_kesehatan',
                      'employee_jht','employee_jp','employee_kesehatan'] as $k) {
                $groupTotals[$k] += (float)($r[$k] ?? 0);
            }
            $groupTotals['total_tk']  += $totalTk;
            $groupTotals['total_kes'] += $totalKes;

            // Row borders & alternating shade
            $this->applyDataRowStyle($sheet, $row, $no % 2 === 0);

            $row++;
        }

        $dataEndRow = $row - 1;

        // Group subtotal row
        $sheet->setCellValue("A{$row}", '');
        $sheet->setCellValue("B{$row}", 'TOTAL ' . strtoupper($group['name']));
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);

        $totalsMap = [
            'E' => 'gaji_pokok',    'F' => 'tj_masa_kerja',  'G' => 'tunjangan',
            'H' => 'bpjs_base_salary',
            'K' => 'employer_jht',  'L' => 'employer_jkm',   'M' => 'employer_jkk',
            'N' => 'employer_tk_total', 'O' => 'employer_jp', 'P' => 'employer_kesehatan',
            'Q' => 'employee_jht',  'R' => 'employee_jp',    'S' => 'employee_kesehatan',
            'T' => 'total_tk',      'U' => 'total_kes',
        ];
        foreach ($totalsMap as $col => $key) {
            $sheet->setCellValue("{$col}{$row}", $groupTotals[$key]);
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $this->applySubtotalRowStyle($sheet, $row);
        $row++;

        // Accumulate to grand totals
        foreach ($groupTotals as $k => $v) {
            $grandTotals[$k] += $v;
        }

        return $row;
    }

    protected function writeHeaderRow1(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): void
    {
        // Rows 1+2 will be merged vertically for fixed cols (A-J)
        // We write the label here; merge happens in row2 method
        $fixedCols = [
            'A' => 'No',
            'B' => 'NAMA KARYAWAN',
            'C' => "THN\nMASUK",
            'D' => "MASA\nKERJA\n(bln)",
            'E' => "GAJI\nPOKOK",
            'F' => "TUNJ.\nMK",
            'G' => 'TUNJANGAN',
            'H' => "DASAR\nBPJS",
            'I' => "NO. KPJ\nTK",
            'J' => "NO. KPJ\nKES",
        ];
        foreach ($fixedCols as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
            // Merge vertically with next row
            $nextRow = $row + 1;
            $sheet->mergeCells("{$col}{$row}:{$col}{$nextRow}");
        }

        // Group headers spanning multiple cols
        $sheet->setCellValue("K{$row}", 'BPJS DIBAYAR PERUSAHAAN');
        $sheet->mergeCells("K{$row}:P{$row}");

        $sheet->setCellValue("Q{$row}", 'BPJS DIBAYAR KARYAWAN');
        $sheet->mergeCells("Q{$row}:S{$row}");

        $sheet->setCellValue("T{$row}", "TOTAL\nBPJS TK");
        $nextRow = $row + 1;
        $sheet->mergeCells("T{$row}:T{$nextRow}");

        $sheet->setCellValue("U{$row}", "BPJS\nKES");
        $sheet->mergeCells("U{$row}:U{$nextRow}");

        // Style header row 1
        $sheet->getStyle("A{$row}:" . self::LAST_COL . "{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);

        // Perusahaan header color
        $sheet->getStyle("K{$row}:P{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1565C0']],
        ]);

        // Karyawan header color
        $sheet->getStyle("Q{$row}:S{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E65100']],
        ]);

        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    protected function writeHeaderRow2(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): void
    {
        // Sub-headers for Perusahaan
        $pHeaders = [
            'K' => "JHT\n3.7%",
            'L' => "JKM\n0.24%",
            'M' => "JKK\n0.3%",
            'N' => 'TOTAL TK',
            'O' => "PENSIUN\n2%",
            'P' => "KESEHATAN\n4%",
        ];
        foreach ($pHeaders as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }

        // Sub-headers for Karyawan
        $eHeaders = [
            'Q' => "JHT\n2%",
            'R' => "PENSIUN\n1%",
            'S' => "KESEHATAN\n1%",
        ];
        foreach ($eHeaders as $col => $label) {
            $sheet->setCellValue("{$col}{$row}", $label);
        }

        // Style sub-header
        $sheet->getStyle("A{$row}:" . self::LAST_COL . "{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);

        $sheet->getStyle("K{$row}:P{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('1565C0');
        $sheet->getStyle("Q{$row}:S{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB('E65100');

        $sheet->getRowDimension($row)->setRowHeight(28);
    }

    protected function applyDataRowStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, bool $shade): void
    {
        $bgRgb = $shade ? 'F5F7FA' : 'FFFFFF';
        $sheet->getStyle("A{$row}:" . self::LAST_COL . "{$row}")->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRgb]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Money cols right-aligned
        foreach (['E','F','G','H','K','L','M','N','O','P','Q','R','S','T','U'] as $c) {
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Perusahaan cols subtle blue bg
        $sheet->getStyle("K{$row}:P{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB($shade ? 'EBF3FB' : 'F0F7FF');
        // Karyawan cols subtle orange bg
        $sheet->getStyle("Q{$row}:S{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setRGB($shade ? 'FFF3E0' : 'FFF8F0');

        // Bold total cols
        $sheet->getStyle("N{$row}")->getFont()->setBold(true);
        $sheet->getStyle("T{$row}:U{$row}")->getFont()->setBold(true);
    }

    protected function applySubtotalRowStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): void
    {
        $sheet->getStyle("A{$row}:" . self::LAST_COL . "{$row}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F7']],
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1565C0']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1565C0']],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (['E','F','G','H','K','L','M','N','O','P','Q','R','S','T','U'] as $c) {
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getRowDimension($row)->setRowHeight(18);
    }

    protected function renderGrandTotal(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        array $grandTotals
    ): int {
        $sheet->setCellValue("A{$row}", 'GRAND TOTAL');
        $sheet->mergeCells("A{$row}:D{$row}");

        $totalsMap = [
            'E' => 'gaji_pokok',    'F' => 'tj_masa_kerja',  'G' => 'tunjangan',
            'H' => 'bpjs_base_salary',
            'K' => 'employer_jht',  'L' => 'employer_jkm',   'M' => 'employer_jkk',
            'N' => 'employer_tk_total', 'O' => 'employer_jp', 'P' => 'employer_kesehatan',
            'Q' => 'employee_jht',  'R' => 'employee_jp',    'S' => 'employee_kesehatan',
            'T' => 'total_tk',      'U' => 'total_kes',
        ];
        foreach ($totalsMap as $col => $key) {
            $sheet->setCellValue("{$col}{$row}", $grandTotals[$key]);
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $sheet->getStyle("A{$row}:" . self::LAST_COL . "{$row}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1E3A5F']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (['E','F','G','H','K','L','M','N','O','P','Q','R','S','T','U'] as $c) {
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getRowDimension($row)->setRowHeight(20);

        return $row + 1;
    }

    protected function calcTotalTk(array $r): float
    {
        return (float)($r['employer_jht'] ?? 0)
             + (float)($r['employer_jkm'] ?? 0)
             + (float)($r['employer_jkk'] ?? 0)
             + (float)($r['employer_jp'] ?? 0)
             + (float)($r['employee_jht'] ?? 0)
             + (float)($r['employee_jp'] ?? 0);
    }

    protected function calcTotalKes(array $r): float
    {
        return (float)($r['employer_kesehatan'] ?? 0)
             + (float)($r['employee_kesehatan'] ?? 0);
    }
}
