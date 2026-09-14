<?php

namespace App\Modules\Employee\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CompensationBankSheet implements WithTitle, WithEvents, WithColumnWidths
{
    protected array $data;
    protected int $year;
    protected int $month;

    private const LAST_COL = 'G';
    private const HEADERS = ['PENERIMA', 'NOREK', 'SINGKATAN NAMA BANK', 'CABANG', 'NOMINAL', 'TANGGAL TRANSAKSI', 'KETERANGAN'];

    public function __construct(Collection $contracts, int $year, int $month)
    {
        $this->year = $year;
        $this->month = $month;

        $this->data = [];
        foreach ($contracts as $contract) {
            $employee = $contract->employee;
            if (! $employee) {
                continue;
            }

            $period = sprintf('%d-%02d', $this->year, $this->month);

            $gajiPokok = $employee->gaji_pokok($period);
            $tjMasaKerja = $employee->tjMasaKerja($period);
            $durationMonths = (int) ($contract->duration_months ?? 0);
            $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
            $totalRaw = $durationMonths * $monthlyRate;
            $totalRounded = (float) (ceil($totalRaw / 100) * 100);
            // Nominal transfer bank = kompensasi bersih setelah potongan admin.
            $potAdmin = $contract->pot_admin === null ? null : (float) $contract->pot_admin;

            $this->data[] = [
                'penerima'  => $employee->bank_account_name ?: $employee->name,
                'norek'     => (string) ($employee->bank_account_number ?? ''),
                'bank'      => (string) ($employee->bank_name ?? ''),
                'cabang'    => (string) ($employee->bank_cabang ?? ''),
                'nominal'   => $totalRounded - (float) ($potAdmin ?? 0),
                'tanggal'   => $contract->compensation_paid_at?->format('d-m-Y') ?? '',
                'keterangan'=> 'KOMPENSASI',
            ];
        }
    }

    public function title(): string
    {
        return 'TRANSFER BANK';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 32,
            'B' => 22,
            'C' => 22,
            'D' => 16,
            'E' => 18,
            'F' => 18,
            'G' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;

                $sheet->getParent()->getDefaultStyle()->applyFromArray([
                    'font' => ['name' => 'Calibri', 'size' => 10],
                ]);

                // ── Row 1: Header ──
                $row = 1;
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

                // ── Data rows (starting row 2) ──
                $dataStartRow = 2;

                foreach ($this->data as $idx => $item) {
                    $r = $dataStartRow + $idx;

                    $sheet->setCellValue("A{$r}", $item['penerima']);
                    $sheet->setCellValueExplicit("B{$r}", $item['norek'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $sheet->setCellValue("C{$r}", $item['bank']);
                    $sheet->setCellValue("D{$r}", $item['cabang']);
                    $sheet->setCellValue("E{$r}", $item['nominal']);
                    $sheet->setCellValue("F{$r}", $item['tanggal']);
                    $sheet->setCellValue("G{$r}", $item['keterangan']);

                    $borderStyle = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]];

                    $sheet->getStyle("A{$r}")->applyFromArray([
                        'font' => ['size' => 10, 'color' => ['rgb' => '333333']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => $borderStyle,
                    ]);
                    $sheet->getStyle("B{$r}")->applyFromArray([
                        'font' => ['name' => 'Consolas', 'size' => 10, 'color' => ['rgb' => '333333']],
                        'numberFormat' => ['formatCode' => '@'],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => $borderStyle,
                    ]);
                    foreach (['C', 'D', 'F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$r}")->applyFromArray([
                            'font' => ['size' => 10, 'color' => ['rgb' => '333333']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                            'borders' => $borderStyle,
                        ]);
                    }
                    $sheet->getStyle("E{$r}")->applyFromArray([
                        'font' => ['size' => 10, 'color' => ['rgb' => '333333']],
                        'numberFormat' => ['formatCode' => '0'],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => $borderStyle,
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

                // ── Freeze pane ──
                $sheet->freezePane('A2');
            },
        ];
    }
}
