<?php

namespace App\Modules\Payroll\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class KirimAllSheet implements WithTitle, WithEvents
{
    protected array $data;
    protected float $total;
    protected string $sectionLabel;
    protected string $tanggalPenggajian;
    protected string $titlePrefix;

    private const LAST_COL = 'G';
    private const HEADERS = ['PENERIMA', 'NOREK', 'SINGKATAN NAMA BANK', 'CABANG', 'NOMINAL', 'TANGGAL TRANSAKSI', 'KETERANGAN'];

    public function __construct(array $data, float $total, string $sectionLabel, ?string $tanggalPenggajian = null, string $titlePrefix = 'KIRIM ALL')
    {
        $this->data = $data;
        $this->total = $total;
        $this->sectionLabel = $sectionLabel;
        $this->tanggalPenggajian = $tanggalPenggajian ?? '';
        $this->titlePrefix = $titlePrefix;
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
                $lastCol = self::LAST_COL;

                // ── Default font ──
                $sheet->getParent()->getDefaultStyle()->applyFromArray([
                    'font' => ['name' => 'Calibri', 'size' => 10],
                ]);

                $row = 1;

                // ── Row 1: Title ──
                $title = $this->titlePrefix . ' - ' . strtoupper($this->sectionLabel);
                $sheet->setCellValue("A{$row}", $title);
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(32);

                // ── Row 2: Header ──
                $row = 2;
                foreach (self::HEADERS as $i => $header) {
                    $col = chr(65 + $i);
                    $sheet->setCellValue("{$col}{$row}", $header);
                }
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'B0B0B0'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(24);

                // ── Data rows (starting row 3) ──
                $dataStartRow = 3;
                $dataEndRow = $dataStartRow + count($this->data) - 1;

                foreach ($this->data as $idx => $item) {
                    $r = $dataStartRow + $idx;
                    $colIdx = 0;

                    // A: Penerima
                    $sheet->setCellValue("A{$r}", $item['bank_account_name'] && $item['bank_account_name'] !== '-'
                        ? $item['bank_account_name']
                        : ($item['name'] ?? '-'));
                    // B: Norek
                    $sheet->setCellValue("B{$r}", $item['bank_account_number'] ?? '');
                    // C: Singkatan Nama Bank
                    $sheet->setCellValue("C{$r}", $item['bank_name'] ?? '');
                    // D: Cabang
                    $sheet->setCellValue("D{$r}", $item['bank_cabang'] ?? '');
                    // E: Nominal
                    $sheet->setCellValue("E{$r}", round((float) ($item['gaji_bersih'] ?? 0)));
                    // F: Tanggal Transaksi
                    $sheet->setCellValue("F{$r}", $this->tanggalPenggajian);
                    // G: Keterangan
                    $sheet->setCellValue("G{$r}", $item['notes'] ?? '');

                    // Font defaults
                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '333333']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                    ]);
                    $sheet->getStyle("B{$r}")->applyFromArray([
                        'font' => ['name' => 'Consolas', 'size' => 10, 'color' => ['rgb' => '333333']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                    ]);
                    foreach (['C', 'D', 'F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$r}")->applyFromArray([
                            'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '333333']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                        ]);
                    }
                    // E: Nominal right-aligned with number format
                    $sheet->getStyle("E{$r}")->applyFromArray([
                        'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '333333']],
                        'numberFormat' => ['formatCode' => '#,##0'],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                    ]);

                    // Zebra striping
                    if ($idx % 2 === 1) {
                        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                            $sheet->getStyle("{$col}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setRGB('F2F7FB');
                        }
                    }

                    $sheet->getRowDimension($r)->setRowHeight(20);
                }

                // ── Total row ──
                $totalRow = $dataEndRow + 1;
                $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->setCellValue("E{$totalRow}", round($this->total));

                foreach (['A', 'B', 'C', 'D'] as $col) {
                    $sheet->getStyle("{$col}{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1F4E79']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F0']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                    ]);
                }
                $sheet->getStyle("E{$totalRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1F4E79']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F0']],
                    'numberFormat' => ['formatCode' => '#,##0'],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                ]);
                foreach (['F', 'G'] as $col) {
                    $sheet->getStyle("{$col}{$totalRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F0']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                    ]);
                }
                $sheet->getRowDimension($totalRow)->setRowHeight(24);

                // ── Column widths ──
                $colWidths = ['A' => 32, 'B' => 22, 'C' => 22, 'D' => 16, 'E' => 20, 'F' => 18, 'G' => 28];
                foreach ($colWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ── Freeze pane ──
                $sheet->freezePane('C3');
            },
        ];
    }
}
