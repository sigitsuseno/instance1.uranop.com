<?php

namespace App\Modules\Employee\Exports\Sheets;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CompensationMainSheet implements FromArray, WithEvents, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $contracts;
    protected int $year;
    protected int $month;

    // 13 kolom: A=NO, B=NAMA, C=REKENING, D=BANK, E=CABANG,
    // F..J = di bawah header group (start_date, end_date, gaji_pokok, durasi, nominal),
    // K=PEMBULATAN, L=POTONGAN, M=TOTAL TERIMA
    private const LAST_COL = 'M';
    private const COL_COUNT = 13;

    public function __construct(Collection $contracts, int $year, int $month)
    {
        $this->contracts = $contracts;
        $this->year = $year;
        $this->month = $month;
    }

    public function title(): string
    {
        return 'KOMPENSASI';
    }

    public function array(): array
    {
        $grouped = $this->contracts->groupBy(fn ($c) => $c->comp_group ?: '(Tanpa Group)');

        // ── Baris 1: judul KOMPENSASI UNGARAN {bulan} {tahun} ──
        $bulanLabel = Carbon::createFromDate($this->year, $this->month, 1)
            ->locale('id')
            ->isoFormat('MMMM YYYY');
        $titleRow = array_fill(0, self::COL_COUNT, null);
        $titleRow[0] = "KOMPENSASI UNGARAN {$bulanLabel}";

        $rows = [$titleRow];

        $totalGajiPokok = 0;
        $totalRawSum = 0.0;
        $totalPembulatan = 0;
        $totalPotAdmin = 0.0;
        $totalTerima = 0.0;

        foreach ($grouped as $groupName => $groupContracts) {
            // ── Section header row (nama group + tahun periode, merged F:J) ──
            $row1 = array_fill(0, self::COL_COUNT, null);
            $row1[0]  = 'NO';
            $row1[1]  = 'NAMA';
            $row1[2]  = 'REKENING';
            $row1[3]  = 'BANK';
            $row1[4]  = 'CABANG';
            $row1[5]  = trim($groupName . ' ' . $this->year);
            $row1[10] = 'PEMBULATAN';
            $row1[11] = 'POTONGAN';
            $row1[12] = 'TOTAL TERIMA';
            $rows[] = $row1;

            // ── Data rows ──
            $no = 1;
            foreach ($groupContracts as $contract) {
                $employee = $contract->employee;
                $period = sprintf('%d-%02d', $this->year, $this->month);

                $gajiPokok = $employee ? $employee->gaji_pokok($period) : 0;
                $tjMasaKerja = $employee ? $employee->tjMasaKerja($period) : 0;
                $durationMonths = (int) ($contract->duration_months ?? 0);
                $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
                $totalRaw = $durationMonths * $monthlyRate;
                $totalRounded = (float) (ceil($totalRaw / 100) * 100);
                $pembulatan = (int) floor($totalRounded - $totalRaw);
                $potAdmin = $contract->pot_admin === null ? null : (float) $contract->pot_admin;
                $totalTerima = $totalRounded - (float) ($potAdmin ?? 0);

                $rows[] = [
                    $no++,
                    $employee?->name ?? '-',
                    $employee?->bank_account_number ?? '-',
                    $employee?->bank_name ?? '-',
                    $employee?->bank_cabang ?? '-',
                    Carbon::parse($contract->start_date)->format('d-m-Y'),
                    Carbon::parse($contract->end_date)->format('d-m-Y'),
                    $gajiPokok,
                    $durationMonths,
                    round($totalRaw, 2),
                    $pembulatan,
                    $potAdmin,
                    $totalTerima,
                ];

                $totalGajiPokok += $gajiPokok;
                $totalRawSum += $totalRaw;
                $totalPembulatan += $pembulatan;
                $totalPotAdmin += (float) ($potAdmin ?? 0);
                $totalTerima += $totalTerima;
            }

            // ── Blank row pemisah antar section ──
            $rows[] = array_fill(0, self::COL_COUNT, null);
        }

        // ── Baris terakhir: TOTAL ──
        $totalRow = array_fill(0, self::COL_COUNT, null);
        $totalRow[0]  = 'TOTAL';
        $totalRow[7]  = $totalGajiPokok;
        $totalRow[9]  = round($totalRawSum, 2);
        $totalRow[10] = $totalPembulatan;
        $totalRow[11] = (float) $totalPotAdmin;
        $totalRow[12] = (float) $totalTerima;
        $rows[] = $totalRow;

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 28,
            'C' => 16,
            'D' => 12,
            'E' => 14,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 8,
            'J' => 14,
            'K' => 12,
            'L' => 14,
            'M' => 14,
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
                $lastCol = self::LAST_COL;
                $lastRow = $sheet->getHighestRow();

                $sectionHeaderRows = [];
                $titleRow = null;
                $totalRow = null;

                for ($r = 1; $r <= $lastRow; $r++) {
                    $aVal = $sheet->getCell("A{$r}")->getValue();
                    if ($aVal === 'NO') {
                        $sectionHeaderRows[] = $r;
                    } elseif ($aVal === 'TOTAL') {
                        $totalRow = $r;
                    } elseif (is_string($aVal) && str_starts_with($aVal, 'KOMPENSASI UNGARAN')) {
                        $titleRow = $r;
                    }
                }

                // ── Judul: merged A:L ──
                if ($titleRow) {
                    $sheet->mergeCells("A{$titleRow}:{$lastCol}{$titleRow}");
                    $sheet->getStyle("A{$titleRow}:{$lastCol}{$titleRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 14],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                    $sheet->getRowDimension($titleRow)->setRowHeight(28);
                }

                // ── Section header row: merge F:J untuk nama group ──
                foreach ($sectionHeaderRows as $r) {
                    $sheet->mergeCells("F{$r}:J{$r}");

                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'D9E2F3'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                    $sheet->getCell("F{$r}")->getStyle()->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getRowDimension($r)->setRowHeight(22);
                }

                // ── Data rows: border + alignment + number format ──
                $dataRows = [];
                for ($r = 1; $r <= $lastRow; $r++) {
                    if ($r === $titleRow || $r === $totalRow || in_array($r, $sectionHeaderRows, true)) {
                        continue;
                    }
                    $aVal = $sheet->getCell("A{$r}")->getValue();
                    $fVal = $sheet->getCell("F{$r}")->getValue();
                    if ($aVal === null && $fVal === null && $sheet->getCell("B{$r}")->getValue() === null) {
                        continue; // blank pemisah
                    }
                    $dataRows[] = $r;
                }

                foreach ($dataRows as $r) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => '000000'],
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }

                if (! empty($dataRows)) {
                    $firstData = min($dataRows);
                    $lastData = max($dataRows);

                    // Alignment per kolom
                    $sheet->getStyle("A{$firstData}:A{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$firstData}:B{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$firstData}:E{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F{$firstData}:G{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$firstData}:H{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("I{$firstData}:I{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$firstData}:{$lastCol}{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Number format untuk kolom numerik
                    foreach (['H', 'I', 'J', 'K', 'L', 'M'] as $col) {
                        $sheet->getStyle("{$col}{$firstData}:{$col}{$lastData}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // ── Baris TOTAL ──
                if ($totalRow) {
                    $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                    $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF2CC'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            'vertical'   => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                    // Label TOTAL di tengah (merged A:E)
                    $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Number format kolom numerik
                    foreach (['H', 'I', 'J', 'K', 'L', 'M'] as $col) {
                        $sheet->getStyle("{$col}{$totalRow}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }

                    $sheet->getRowDimension($totalRow)->setRowHeight(22);
                }

                // ── Print setup: landscape F4 ──
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);
            },
        ];
    }
}
