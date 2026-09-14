<?php

namespace App\Modules\Reports\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PayrollResumeSheet implements WithTitle, WithEvents
{
    protected array $data;
    protected string $sectionLabel;
    protected string $periodName;

    public function __construct(array $data, string $sectionLabel, string $periodName)
    {
        $this->data = $data;
        $this->sectionLabel = $sectionLabel;
        $this->periodName = $periodName;
    }

    public function title(): string
    {
        return $this->sectionLabel;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = count($this->data);

                // ── Default font ──
                $sheet->getParent()->getDefaultStyle()->applyFromArray([
                    'font' => ['name' => 'Calibri', 'size' => 10],
                ]);

                $firstDataRow = 8;
                $lastDataRow = $firstDataRow + $rowCount - 1;
                $lastCol = 'U';

                // Row 1: Title
                $sheet->setCellValue('A1', 'PT. KEMILAU UNGARAN SUKSES');
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                // Row 2: Period name
                $sheet->setCellValue('A2', strtoupper($this->periodName));
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getFont()->setSize(11);

                // Row 3: empty
                // Row 4: RESUME GAJI
                $sheet->setCellValue('A4', 'RESUME GAJI');
                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(13);

                // Row 5: Section label (A. Karyawan All In / B. Karyawan Bulanan Print)
                $sheet->setCellValue('A5', strtoupper($this->sectionLabel));
                $sheet->mergeCells("A5:{$lastCol}5");
                $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(11);

                // Row 6-7: Headers
                $headers1 = ['No', 'BAGIAN', 'JML KARYAWAN', '', '', 'GAJI', 'LEMBUR', 'REVISI', 'TJ. MASA KERJA', 'TUNJANGAN', 'PREMI HADIR', 'PBLT', 'TOTAL', 'LEMBUR', 'TOTAL + LEMBUR', 'BPJS TENAGA KERJA', 'BPJS KESEHATAN', 'BPJS PENSIUN', 'CASHBON', 'REVISI PPH', 'TOTAL TERIMA'];
                $headers2 = ['', '', 'L', 'P', 'Total', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];

                foreach ($headers1 as $i => $h) {
                    $col = chr(65 + $i);
                    $sheet->setCellValue("{$col}6", $h);
                    $sheet->setCellValue("{$col}7", $headers2[$i]);
                }

                // Merge C6:E6 (JML KARYAWAN)
                $sheet->mergeCells('C6:E6');

                // Vertical merges for single-row headers
                $colsToMerge = ['A', 'B', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];
                foreach ($colsToMerge as $col) {
                    $sheet->mergeCells("{$col}6:{$col}7");
                }

                // Header styling
                $sheet->getStyle("A6:{$lastCol}7")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A6:{$lastCol}7")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // Data rows
                $colKeys = ['bagian', 'jml_karyawan_l', 'jml_karyawan_p', 'jml_karyawan_total', 'gaji', 'lembur', 'revisi', 'tj_masa_kerja', 'tunjangan', 'premi_hadir', 'pblt', 'total', 'lembur', 'total_plus_lembur', 'bpjs_tk', 'bpjs_ks', 'bpjs_pen', 'cashbon', 'revisi_pph', 'total_terima'];

                // Kolom finansial mulai kolom F. 'lembur' muncul 2x (sebelum & sesudah TOTAL),
                // karena itu akumulasi total dihitung dari key yang unik.
                $financialKeys = ['gaji', 'lembur', 'revisi', 'tj_masa_kerja', 'tunjangan', 'premi_hadir', 'pblt', 'total', 'lembur', 'total_plus_lembur', 'bpjs_tk', 'bpjs_ks', 'bpjs_pen', 'cashbon', 'revisi_pph', 'total_terima'];

                $totals = [];
                foreach ($this->data as $idx => $row) {
                    $r = $firstDataRow + $idx;

                    $sheet->setCellValue("A{$r}", $idx + 1);
                    $sheet->setCellValue("B{$r}", $row['bagian'] ?? '-');

                    // JML KARYAWAN
                    $sheet->setCellValue("C{$r}", $row['jml_karyawan_l'] ?? 0);
                    $sheet->setCellValue("D{$r}", $row['jml_karyawan_p'] ?? 0);
                    $sheet->setCellValue("E{$r}", $row['jml_karyawan_total'] ?? 0);

                    // Financial columns (F onwards)
                    foreach ($financialKeys as $fi => $key) {
                        $col = chr(70 + $fi); // F=70
                        $sheet->setCellValue("{$col}{$r}", (float) ($row[$key] ?? 0));
                    }

                    foreach (array_unique($financialKeys) as $key) {
                        $totals[$key] = ($totals[$key] ?? 0) + (float) ($row[$key] ?? 0);
                    }

                    // Collect totals for count columns
                    $totals['jml_karyawan_l'] = ($totals['jml_karyawan_l'] ?? 0) + (int) ($row['jml_karyawan_l'] ?? 0);
                    $totals['jml_karyawan_p'] = ($totals['jml_karyawan_p'] ?? 0) + (int) ($row['jml_karyawan_p'] ?? 0);
                    $totals['jml_karyawan_total'] = ($totals['jml_karyawan_total'] ?? 0) + (int) ($row['jml_karyawan_total'] ?? 0);

                    $sheet->getRowDimension($r)->setRowHeight(18);
                }

                // ── Total row ──
                if ($rowCount > 0) {
                    $totalRow = $lastDataRow + 1;
                    $sheet->setCellValue("A{$totalRow}", '');
                    $sheet->setCellValue("B{$totalRow}", 'TOTAL ' . strtoupper($this->sectionLabel));
                    $sheet->setCellValue("C{$totalRow}", $totals['jml_karyawan_l'] ?? 0);
                    $sheet->setCellValue("D{$totalRow}", $totals['jml_karyawan_p'] ?? 0);
                    $sheet->setCellValue("E{$totalRow}", $totals['jml_karyawan_total'] ?? 0);

                    foreach ($financialKeys as $fi => $key) {
                        $col = chr(70 + $fi);
                        $sheet->setCellValue("{$col}{$totalRow}", $totals[$key] ?? 0);
                    }

                    // Style total row
                    $sheet->getStyle("B{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getRowDimension($totalRow)->setRowHeight(20);

                    // Extend borders to include total row
                    $sheet->getStyle("A6:{$lastCol}{$totalRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Number format for financial columns
                if ($rowCount > 0) {
                    $sheet->getStyle("F{$firstDataRow}:{$lastCol}{$lastDataRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                }

                // Center alignment for No and JML KARYAWAN
                $sheet->getStyle("A{$firstDataRow}:E{$lastDataRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Set column widths
                $colWidths = [
                    'A' => 5, 'B' => 22, 'C' => 6, 'D' => 6, 'E' => 7,
                    'F' => 15, 'G' => 12, 'H' => 10, 'I' => 14, 'J' => 12,
                    'K' => 12, 'L' => 10, 'M' => 15, 'N' => 12, 'O' => 16,
                    'P' => 16, 'Q' => 16, 'R' => 14, 'S' => 12, 'T' => 12,
                    'U' => 16,
                ];
                foreach ($colWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // Freeze pane
                $sheet->freezePane('A8');
            },
        ];
    }
}
