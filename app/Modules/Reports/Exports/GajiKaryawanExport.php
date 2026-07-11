<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GajiKaryawanExport implements FromArray, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected array $secAData;
    protected array $secBData;
    protected string $periodLabel;

    // 28 columns: A=No ... AB=TRIMA
    private const LAST_COL = 'AB';
    private const COL_COUNT = 28;

    public function __construct(array $secAData, array $secBData, string $periodLabel)
    {
        $this->secAData = $secAData;
        $this->secBData = $secBData;
        $this->periodLabel = $periodLabel;
    }

    public function title(): string
    {
        return 'Gaji Karyawan';
    }

    public function headings(): array
    {
        return [
            'No',            // A
            'ID No',         // B
            'NAMA KARYAWAN', // C
            'L/P',           // D
            'MASA KERJA',    // E
            'BAGIAN',        // F
            'JABATAN',       // G
            'THN MSK',       // H
            'STATUS',        // I
            'PREMI',         // J
            'GAJI POKOK',    // K
            'TJ. MK',        // L
            'HK',            // M
            'L/M',           // N
            'LBR JAM',       // O
            'GAJI',          // P
            'LEMBUR',        // Q
            'REVISI',        // R
            'TUNJANGAN',     // S
            'PR. HADIR',     // T
            'PBLT',          // U
            'TOTAL',         // V
            'BPJS TK',       // W
            'BPJS KES',      // X
            'BPJS PEN',      // Y
            'CASH BON',      // Z
            'PPH',           // AA
            'TRIMA',         // AB
        ];
    }

    public function array(): array
    {
        $rows = [];

        // ── Section A ──
        $countA = count($this->secAData);
        $rows[] = $this->sectionRow("A. KARYAWAN ALL IN ({$countA} Karyawan)");
        $no = 1;
        foreach ($this->secAData as $r) {
            $rows[] = $this->dataRow($r, $no++);
        }
        $rows[] = $this->totalRow('TOTAL A. KARYAWAN ALL IN', $this->secAData);

        // ── Section B ──
        $countB = count($this->secBData);
        $rows[] = $this->sectionRow("B. KARYAWAN BULANAN PRINT ({$countB} Karyawan)");
        $no = 1;
        foreach ($this->secBData as $r) {
            $rows[] = $this->dataRow($r, $no++);
        }
        $rows[] = $this->totalRow('TOTAL B. KARYAWAN BULANAN PRINT', $this->secBData);

        // ── Blank ──
        $rows[] = array_fill(0, self::COL_COUNT, null);

        // ── Grand ──
        $rows[] = $this->totalRow('TOTAL KESELURUHAN', array_merge($this->secAData, $this->secBData));

        return $rows;
    }

    private function dataRow(array $r, int $no): array
    {
        return [
            $no,                           // A
            $r['employee_code'] ?? '-',    // B
            $r['name'] ?? '-',             // C
            $r['gender'] ?? '-',           // D
            $r['masa_kerja'] ? $r['masa_kerja'] . ' bln' : '0',  // E
            $r['department'] ?? '-',       // F
            $r['position'] ?? '-',         // G
            $r['join_year'] ?? '-',        // H
            $r['ptkp'] ?? '-',             // I
            $r['premi'] ?? 0,              // J
            $r['gaji_pokok'] ?? 0,         // K
            $r['tj_masa_kerja'] ?? 0,      // L
            $r['hari_kerja'] ?? 0,         // M
            $r['lm'] ? round($r['lm'] / 60, 1) : 0,                  // N (L/M dalam jam)
            $r['lembur_count'] ? round($r['lembur_count'], 1) : 0, // O (LBR JAM — sudah jam)
            $r['gaji'] ?? 0,               // P
            $r['upah_lembur'] ?? 0,        // Q
            $r['revisi'] ?? 0,             // R
            $r['tunjangan'] ?? 0,          // S
            $r['premi_hadir'] ?? 0,        // T
            $r['pblt'] ?? 0,               // U
            $r['total'] ?? 0,              // V
            $r['bpjs_tk'] ?? 0,            // W
            $r['bpjs_ks'] ?? 0,            // X
            $r['bpjs_pen'] ?? 0,           // Y
            $r['cashbon'] ?? 0,            // Z
            $r['pph'] ?? 0,                // AA
            $r['gaji_bersih'] ?? 0,        // AB
        ];
    }

    private function sectionRow(string $label): array
    {
        $row = array_fill(0, self::COL_COUNT, null);
        $row[0] = $label;
        return $row;
    }

    private function totalRow(string $label, array $data): array
    {
        $sum = fn($key) => array_sum(array_map(fn($r) => (float) ($r[$key] ?? 0), $data));

        return [
            null, null, null, null, null, null,  // A-F
            $label,                               // G — label di kolom G
            null,                                 // H
            null,                                 // I
            $sum('premi'),                        // J
            $sum('gaji_pokok'),                   // K
            $sum('tj_masa_kerja'),                // L
            $sum('hari_kerja'),                   // M
            $sum('lm'),                           // N
            $sum('lembur_count'),                 // O
            $sum('gaji'),                         // P
            $sum('upah_lembur'),                  // Q
            $sum('revisi'),                       // R
            $sum('tunjangan'),                    // S
            $sum('premi_hadir'),                  // T
            $sum('pblt'),                         // U
            $sum('total'),                        // V
            $sum('bpjs_tk'),                      // W
            $sum('bpjs_ks'),                      // X
            $sum('bpjs_pen'),                     // Y
            $sum('cashbon'),                      // Z
            $sum('pph'),                          // AA
            $sum('gaji_bersih'),                  // AB
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
                $spreadsheet = $sheet->getParent();
                $lastRow = $sheet->getHighestRow();
                $lastCol = self::LAST_COL;

                // ── Default font ──
                $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

                // ── Title row (row 1) ──
                $sheet->insertNewRowBefore(1, 1);
                $sheet->setCellValue('A1', 'LAPORAN GAJI KARYAWAN');
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Calibri'],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(32);

                // Period label (right-aligned in last column, same row)
                $sheet->setCellValue("{$lastCol}1", strtoupper($this->periodLabel));
                $sheet->getStyle("{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // ── Header row (row 2) ──
                $headerRow = 2;
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9, 'name' => 'Calibri'],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E2F3'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(28);

                // ── ALL borders ──
                $dataStartRow = 3;
                $borderStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ];
                $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")->applyFromArray($borderStyle);
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray($borderStyle);

                // ── Section header rows ──
                foreach (range(3, $lastRow) as $r) {
                    $val = $sheet->getCell("A{$r}")->getValue();
                    if (is_string($val) && (str_starts_with($val, 'A.') || str_starts_with($val, 'B.'))) {
                        $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                        $sheet->getStyle("A{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D9E2F3'],
                            ],
                        ]);
                        $sheet->getRowDimension($r)->setRowHeight(22);
                    }
                }

                // ── Total rows (label in column G) ──
                foreach (range(3, $lastRow) as $r) {
                    $val = $sheet->getCell("G{$r}")->getValue();
                    if (is_string($val) && str_starts_with($val, 'TOTAL')) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D9E2F3'],
                            ],
                        ]);
                        $sheet->mergeCells("A{$r}:F{$r}");
                        $sheet->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getRowDimension($r)->setRowHeight(22);
                    }
                }

                // ── Grand total (last row) ──
                $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri'],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'B4C6E7'],
                    ],
                ]);
                $sheet->getRowDimension($lastRow)->setRowHeight(26);

                // ── Number format (#,##0) for numeric columns (J through AB) ──
                $numStartCol = 'J';
                $sheet->getStyle("{$numStartCol}{$dataStartRow}:{$lastCol}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // ── Integer columns (no decimals) ──
                foreach (['M', 'U'] as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // ── L/M & LBR JAM — 1 decimal (jam) ──
                foreach (['N', 'O'] as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.0');
                }

                // ── Column widths ──
                $colWidths = [
                    'A' => 5,   'B' => 8,   'C' => 28,  'D' => 5,
                    'E' => 9,   // MASA KERJA
                    'F' => 18,  'G' => 18,  'H' => 14,
                    'I' => 9,   // STATUS
                    'J' => 14,  'K' => 14,  'L' => 10,  'M' => 6,
                    'N' => 6,   'O' => 8,   'P' => 14,  'Q' => 12,
                    'R' => 10,  'S' => 14,  'T' => 12,  'U' => 8,
                    'V' => 14,  'W' => 12,  'X' => 12,  'Y' => 12,
                    'Z' => 12,  'AA' => 10, 'AB' => 14,
                ];
                foreach ($colWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ── Alignment ──
                $centerCols = ['A', 'B', 'D', 'E', 'H', 'I', 'M', 'N', 'O', 'U'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $rightCols = ['J', 'K', 'L', 'P', 'Q', 'R', 'S', 'T', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB'];
                foreach ($rightCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ── Vertical center ──
                $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // ── Freeze pane ──
                $sheet->freezePane('C3');
            },
        ];
    }
}
