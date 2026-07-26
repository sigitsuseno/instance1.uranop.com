<?php

namespace App\Modules\Payroll\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class KirimAllSheet implements FromArray, WithTitle, WithEvents
{
    protected array $data;
    protected float $total;
    protected string $sectionLabel;
    protected string $tanggalPenggajian;

    private const LAST_COL = 'G';

    public function __construct(array $data, float $total, string $sectionLabel, ?string $tanggalPenggajian = null)
    {
        $this->data = $data;
        $this->total = $total;
        $this->sectionLabel = $sectionLabel;
        $this->tanggalPenggajian = $tanggalPenggajian ?? '';
    }

    public function title(): string
    {
        return $this->sectionLabel;
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $item) {
            $recipient = (!empty($item['bank_account_name']) && $item['bank_account_name'] !== '-')
                ? $item['bank_account_name']
                : ($item['name'] ?? '-');

            $rows[] = [
                $recipient,                                    // A: Penerima
                $item['bank_account_number'] ?? '',            // B: Norek
                $item['bank_name'] ?? '',                      // C: Singkatan Nama Bank
                $item['bank_cabang'] ?? '',                    // D: Cabang
                (float) ($item['gaji_bersih'] ?? 0),           // E: Nominal
                '',                                            // F: Tanggal Transaksi (diisi di styling)
                $item['notes'] ?? '',                          // G: Keterangan
            ];
        }

        return $rows;
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
                $title = 'KIRIM ALL - ' . strtoupper($this->sectionLabel);
                $sheet->setCellValue('A1', $title);
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Calibri', 'color' => ['rgb' => '1F4E79']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(32);

                // ── Header row (row 2) ──
                $headerRow = 2;
                $headers = ['PENERIMA', 'NOREK', 'SINGKATAN NAMA BANK', 'CABANG', 'NOMINAL', 'TANGGAL TRANSAKSI', 'KETERANGAN'];
                foreach (range(0, 6) as $i) {
                    $col = chr(65 + $i); // A=65
                    $cell = $sheet->getCell("{$col}{$headerRow}");
                    $cell->setValue($headers[$i]);
                }
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri', 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F4E79'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(24);

                // ── Borders ──
                $dataStartRow = 3;
                $borderStyle = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'B0B0B0'],
                        ],
                    ],
                ];
                $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->applyFromArray($borderStyle);

                // ── Data rows styling ──
                $totalRowNum = $lastRow;

                foreach (range($dataStartRow, $totalRowNum - 1) as $r) {
                    $rowIdx = $r - $dataStartRow;

                    // Font
                    $sheet->getStyle("A{$r}")->getFont()->setName('Calibri')->setSize(10)->setColor('333333');
                    $sheet->getStyle("B{$r}")->getFont()->setName('Consolas')->setSize(10)->setColor('333333');
                    foreach (['C', 'D', 'F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$r}")->getFont()->setName('Calibri')->setSize(10)->setColor('333333');
                    }

                    // Alignment
                    $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
                    foreach (['C', 'D', 'F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                    }

                    // Number format for nominal
                    $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('#,##0');

                    // F column: tanggal penggajian
                    if ($this->tanggalPenggajian) {
                        $sheet->setCellValue("F{$r}", $this->tanggalPenggajian);
                    }

                    // Zebra striping
                    if ($rowIdx % 2 === 1) {
                        foreach (range('A', $lastCol) as $col) {
                            $sheet->getStyle("{$col}{$r}")->applyFromArray([
                                'fill' => [
                                    'fillType'   => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F2F7FB'],
                                ],
                            ]);
                        }
                    }

                    $sheet->getRowDimension($r)->setRowHeight(20);
                }

                // ── Total row ──
                if ($totalRowNum >= $dataStartRow) {
                    $sheet->mergeCells("A{$totalRowNum}:D{$totalRowNum}");
                    $sheet->setCellValue("A{$totalRowNum}", 'TOTAL');
                    $sheet->getStyle("A{$totalRowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri', 'color' => ['rgb' => '1F4E79']],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D6E4F0'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    // Fill merged cells with same style
                    foreach (['B', 'C', 'D'] as $col) {
                        $sheet->getStyle("{$col}{$totalRowNum}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => '1F4E79']],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D6E4F0'],
                            ],
                        ]);
                    }

                    // Total nominal
                    $sheet->setCellValue("E{$totalRowNum}", $this->total);
                    $sheet->getStyle("E{$totalRowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'name' => 'Calibri', 'color' => ['rgb' => '1F4E79']],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D6E4F0'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getStyle("E{$totalRowNum}")->getNumberFormat()->setFormatCode('#,##0');

                    // Fill F & G
                    foreach (['F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$totalRowNum}")->applyFromArray([
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'D6E4F0'],
                            ],
                        ]);
                    }

                    $sheet->getRowDimension($totalRowNum)->setRowHeight(24);
                }

                // ── Column widths ──
                $colWidths = [
                    'A' => 32, 'B' => 22, 'C' => 22, 'D' => 16,
                    'E' => 20, 'F' => 18, 'G' => 28,
                ];
                foreach ($colWidths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // ── Freeze pane ──
                $sheet->freezePane('C3');
            },
        ];
    }
}
