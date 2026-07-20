<?php

namespace App\Modules\Supervisor\Payroll\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export gaji karyawan supervisor — format persis sample Excel.
 *
 * 25 kolom (A-Y):
 *   No | ID No | Nama | Bagian / Jabatan | L/P | MASA KERJA | Join Date | Status
 *   | Gaji Pokok | Premi | Tj. MK | HK | L/M | Lbr Hitung | P. Lembur
 *   | GAJI | Tunjangan | Premi Hadir | BPJS TK | BPJS KS | BPJS Pens.
 *   | PPh | Kasbon | PBLT | THP
 *
 * Layout:
 *   Row 1  : PT. KEMILAU UNGARAN SUKSES (merged, bold 14pt, center)
 *   Row 2  : LAPORAN SALARY BREAKDOWN — Periode (merged, center)
 *   Row 3  : Column headers (bold, bg #B4C6E7, center, wrap)
 *   Row 4+ : Data rows (zebra striping, Calibri 11pt, thin borders)
 */
class SupervisorPayrollExport implements FromArray, WithTitle, WithEvents
{
    protected array $data;
    protected string $periodLabel;
    protected string $companyName;

    private const LAST_COL = 'Y';
    private const COL_COUNT = 25;

    public function __construct(array $data, string $periodLabel, string $companyName = 'PT. KEMILAU UNGARAN SUKSES')
    {
        $this->data        = $data;
        $this->periodLabel = $periodLabel;
        $this->companyName = $companyName;
    }

    public function title(): string
    {
        return 'Salary Breakdown';
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $idx => $r) {
            $rows[] = $this->dataRow($r, $idx + 1);
        }

        return $rows;
    }

    private function dataRow(array $r, int $no): array
    {
        // Bagian / Jabatan digabung dengan " / "
        $bagianJabatan = trim(($r['department'] ?? '') . ' / ' . ($r['position'] ?? ''), ' / ');

        // Join date format: dd/mm/yyyy
        $joinDateStr = '-';
        if (!empty($r['join_date_raw'])) {
            $joinDateStr = $r['join_date_raw'];
        } elseif (!empty($r['join_year']) && $r['join_year'] !== '-') {
            $joinDateStr = $r['join_year'];
        }

        // L/M dalam jam (1 desimal)
        $lm = !empty($r['lm']) ? round($r['lm'], 1) : 0;

        // Lbr Hitung (jam, 1 desimal)
        $lemburCount = !empty($r['lembur_count']) ? round($r['lembur_count'], 1) : 0;

        return [
            $no,                                        // A — No
            $r['employee_code'] ?? '-',                 // B — ID No
            $r['name'] ?? '-',                          // C — Nama
            $bagianJabatan,                             // D — Bagian / Jabatan
            $r['gender'] ?? '-',                        // E — L/P
            ($r['masa_kerja'] ?? 0) . ' bln',          // F — MASA KERJA
            $joinDateStr,                               // G — Join Date
            $r['ptkp'] ?? '-',                          // H — Status
            $r['gaji_pokok'] ?? 0,                      // I — Gaji Pokok
            $r['premi'] ?? 0,                           // J — Premi
            $r['tj_masa_kerja'] ?? 0,                   // K — Tj. MK
            $r['hari_kerja'] ?? 0,                      // L — HK
            $lm,                                        // M — L/M (jam)
            $lemburCount,                               // N — Lbr Hitung (jam)
            $r['upah_lembur'] ?? 0,                     // O — P. Lembur
            $r['gaji'] ?? 0,                            // P — GAJI
            $r['tunjangan'] ?? 0,                       // Q — Tunjangan
            $r['premi_hadir'] ?? 0,                     // R — Premi Hadir
            $r['bpjs_tk'] ?? 0,                         // S — BPJS TK
            $r['bpjs_ks'] ?? 0,                         // T — BPJS KS
            $r['bpjs_pen'] ?? 0,                        // U — BPJS Pens.
            $r['pph'] ?? 0,                             // V — PPh
            $r['cashbon'] ?? 0,                         // W — Kasbon
            $r['pblt'] ?? 0,                            // X — PBLT
            $r['gaji_bersih'] ?? 0,                     // Y — THP
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $spreadsheet = $sheet->getParent();

                $dataRows = count($this->data);
                $lastCol = self::LAST_COL;

                // ── Shift data down 3 rows to make room for title + period + headers ──
                $sheet->insertNewRowBefore(1, 3);
                $lastDataRow = 3 + $dataRows; // row 1=title, row 2=period, row 3=headers, row 4+ = data

                // ── Default font ──
                $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                // ── Row 1: Company Name ──
                $sheet->setCellValue('A1', $this->companyName);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Calibri'],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // ── Row 2: Period label ──
                $sheet->setCellValue('A2', 'LAPORAN SALARY BREAKDOWN — ' . $this->periodLabel);
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri'],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(22);

                // ── Row 3: Column Headers ──
                $headerRow = 3;
                $headers = [
                    'No', 'ID No', 'Nama', 'Bagian / Jabatan', 'L/P',
                    'MASA KERJA', 'Join Date', 'Status',
                    'Gaji Pokok', 'Premi', 'Tj. MK', 'HK', 'L/M',
                    'Lbr Hitung', 'P. Lembur', 'GAJI', 'Tunjangan',
                    'Premi Hadir', 'BPJS TK', 'BPJS KS', 'BPJS Pens.',
                    'PPh', 'Kasbon', 'PBLT', 'THP',
                ];
                foreach ($headers as $colIdx => $hdr) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                    $sheet->setCellValue("{$colLetter}{$headerRow}", $hdr);
                }
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'B4C6E7'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ── ALL borders (header + data) ──
                $borderStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'        => ['rgb' => '000000'],
                        ],
                    ],
                ];
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")->applyFromArray($borderStyle);

                // ── Zebra striping (data rows: 4 to lastDataRow) ──
                for ($r = 4; $r <= $lastDataRow; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F9FAFB'],
                            ],
                        ]);
                    }
                }

                // ── Column width ──
                $colWidths = [
                    'A' => 5,   // No
                    'B' => 7,   // ID No
                    'C' => 28,  // Nama
                    'D' => 28,  // Bagian / Jabatan
                    'E' => 5,   // L/P
                    'F' => 10,  // MASA KERJA
                    'G' => 12,  // Join Date
                    'H' => 8,   // Status
                    'I' => 14,  // Gaji Pokok
                    'J' => 14,  // Premi
                    'K' => 10,  // Tj. MK
                    'L' => 6,   // HK
                    'M' => 6,   // L/M
                    'N' => 9,   // Lbr Hitung
                    'O' => 12,  // P. Lembur
                    'P' => 14,  // GAJI
                    'Q' => 14,  // Tunjangan
                    'R' => 12,  // Premi Hadir
                    'S' => 12,  // BPJS TK
                    'T' => 12,  // BPJS KS
                    'U' => 12,  // BPJS Pens.
                    'V' => 10,  // PPh
                    'W' => 10,  // Kasbon
                    'X' => 8,   // PBLT
                    'Y' => 14,  // THP
                ];
                foreach ($colWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ── Alignment per column ──
                // Center: A(No), B(ID No), E(L/P), F(MASA KERJA), G(Join Date), H(Status), L(HK), M(L/M), N(Lbr Hitung), X(PBLT)
                $centerCols = ['A', 'B', 'E', 'F', 'G', 'H', 'L', 'M', 'N', 'X'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$lastDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Right: I(Gaji Pokok), J(Premi), K(Tj. MK), O(P. Lembur), P(GAJI), Q(Tunjangan),
                //        R(Premi Hadir), S(BPJS TK), T(BPJS KS), U(BPJS Pens.),
                //        V(PPh), W(Kasbon), Y(THP)
                $rightCols  = ['I', 'J', 'K', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'Y'];
                foreach ($rightCols as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$lastDataRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // ── Vertical center for all data cells ──
                $sheet->getStyle("A4:{$lastCol}{$lastDataRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // ── Number formats ──
                // Numeric: I-Y (col 9-25) with #,##0
                $sheet->getStyle("I4:{$lastCol}{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');

                // L/M & Lbr Hitung: #,##0.0
                foreach (['M', 'N'] as $col) {
                    $sheet->getStyle("{$col}4:{$col}{$lastDataRow}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.0');
                }

                // HK: integer (no decimals)
                $sheet->getStyle("L4:L{$lastDataRow}")
                    ->getNumberFormat()
                    ->setFormatCode('0');

                // ── Freeze pane ──
                $sheet->freezePane('A4');
            },
        ];
    }
}
