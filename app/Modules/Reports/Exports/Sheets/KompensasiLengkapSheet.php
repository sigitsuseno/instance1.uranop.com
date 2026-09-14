<?php

namespace App\Modules\Reports\Exports\Sheets;

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

/**
 * Sheet "Kompensasi" untuk Export Lengkap.
 *
 * Layout mengikuti file GAJI_KUS_*.xlsx (12 kolom, A–L), TANPA kolom POTONGAN:
 *   A=NO, B=NAMA, C=REKENING, D=BANK, E=CABANG,
 *   F..J = di bawah header group (tgl mulai, tgl akhir, gaji pokok, durasi, nominal),
 *   K=PEMBULATAN, L=TOTAL TERIMA.
 *
 * TOTAL TERIMA = nominal yang sudah dibulatkan ke atas per Rp100 (potongan admin
 * tidak diperhitungkan di sheet ini).
 */
class KompensasiLengkapSheet implements FromArray, WithEvents, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $contracts;
    protected int $year;
    protected int $month;
    protected string $groupLabel;
    protected string $sheetTitle;

    private const LAST_COL = 'L';
    private const COL_COUNT = 12;

    /** Total kolom L — diambil Export Lengkap untuk baris kontrol di sheet Resume. */
    public float $totalTerima = 0.0;

    public function __construct(Collection $contracts, int $year, int $month, string $groupLabel)
    {
        $this->contracts  = $contracts;
        $this->year       = $year;
        $this->month      = $month;
        $this->groupLabel = $groupLabel;
        $this->sheetTitle = self::makeSheetTitle($groupLabel);
    }

    /**
     * Sheet name mengikuti sample: "INPUT KOMPENSASI AGUSTUS (2)" → "Kompensasi Agustus (2)".
     * Excel membatasi 31 karakter.
     */
    public static function makeSheetTitle(string $groupLabel): string
    {
        $clean = trim(preg_replace('/^INPUT\s+/i', '', $groupLabel));
        if ($clean === '') {
            return 'Kompensasi';
        }

        return mb_substr(ucwords(mb_strtolower($clean)), 0, 31);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    /** Jumlah kontrak yang akan ditulis — dipakai menghitung nomor baris TOTAL. */
    public function countContracts(): int
    {
        return $this->contracts->count();
    }

    /**
     * Perhitungan kompensasi satu kontrak. Dipakai bersama oleh sheet ini dan
     * blok "RESUME UANG KOMPENSASI" supaya totalnya dijamin sama.
     *
     * @return array{gaji_pokok: float, tj_masa_kerja: float, duration_months: int,
     *               nominal: float, pembulatan: int, total_terima: float}
     */
    public static function computeRow(?object $contract, int $year, int $month): array
    {
        $employee = $contract?->employee;
        $period   = sprintf('%d-%02d', $year, $month);

        $gajiPokok      = $employee ? (float) $employee->gaji_pokok($period) : 0.0;
        $tjMasaKerja    = $employee ? (float) $employee->tjMasaKerja($period) : 0.0;
        $durationMonths = (int) ($contract->duration_months ?? 0);

        $monthlyRate  = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0.0;
        $totalRaw     = $durationMonths * $monthlyRate;
        $totalRounded = (float) (ceil($totalRaw / 100) * 100);
        // Dibulatkan ke rupiah terdekat supaya nominal (format #,##0) + PEMBULATAN = TOTAL TERIMA.
        $pembulatan   = (int) round($totalRounded - $totalRaw);

        return [
            'gaji_pokok'      => $gajiPokok,
            'tj_masa_kerja'   => $tjMasaKerja,
            'duration_months' => $durationMonths,
            'nominal'         => round($totalRaw),
            'pembulatan'      => $pembulatan,
            'total_terima'    => $totalRounded,
        ];
    }

    public function array(): array
    {
        $bulanLabel = Carbon::createFromDate($this->year, $this->month, 1)
            ->locale('id')
            ->isoFormat('MMMM YYYY');

        // ── Baris 1: judul ──
        $titleRow = array_fill(0, self::COL_COUNT, null);
        $titleRow[0] = "KOMPENSASI UNGARAN {$bulanLabel}";
        $rows = [$titleRow];

        // ── Baris 2: header kolom (F:J di-merge, diisi nama group) ──
        $headerRow = array_fill(0, self::COL_COUNT, null);
        $headerRow[0]  = 'NO';
        $headerRow[1]  = 'NAMA';
        $headerRow[2]  = 'REKENING';
        $headerRow[3]  = 'BANK';
        $headerRow[4]  = 'CABANG';
        $headerRow[5]  = trim($this->groupLabel . ' ' . $this->year);
        $headerRow[10] = 'PEMBULATAN';
        $headerRow[11] = 'TOTAL TERIMA';
        $rows[] = $headerRow;

        // ── Baris data ──
        $no = 1;
        $totalGajiPokok = 0.0;
        $totalNominal   = 0.0;
        $totalPembulatan = 0;
        $totalTerima    = 0.0;

        foreach ($this->contracts as $contract) {
            $employee = $contract->employee;
            $calc     = self::computeRow($contract, $this->year, $this->month);

            $rows[] = [
                $no++,
                $employee?->name ?? '-',
                $employee?->bank_account_number ?? '-',
                $employee?->bank_name ?? '-',
                $employee?->bank_cabang ?? '-',
                $contract->start_date ? Carbon::parse($contract->start_date)->format('d-m-Y') : '-',
                $contract->end_date ? Carbon::parse($contract->end_date)->format('d-m-Y') : '-',
                $calc['gaji_pokok'],
                $calc['duration_months'],
                $calc['nominal'],
                $calc['pembulatan'],
                $calc['total_terima'],
            ];

            $totalGajiPokok  += $calc['gaji_pokok'];
            $totalNominal    += $calc['nominal'];
            $totalPembulatan += $calc['pembulatan'];
            $totalTerima     += $calc['total_terima'];
        }

        // ── Baris kosong pemisah sebelum TOTAL (mengikuti sample) ──
        $rows[] = array_fill(0, self::COL_COUNT, null);

        // ── Baris TOTAL ──
        $totalRow = array_fill(0, self::COL_COUNT, null);
        $totalRow[0]  = 'TOTAL';
        $totalRow[7]  = $totalGajiPokok;
        $totalRow[9]  = $totalNominal;
        $totalRow[10] = $totalPembulatan;
        $totalRow[11] = $totalTerima;
        $rows[] = $totalRow;

        $this->totalTerima = $totalTerima;

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 28, 'C' => 16, 'D' => 10, 'E' => 14,
            'F' => 12, 'G' => 12, 'H' => 14, 'I' => 8, 'J' => 14,
            'K' => 12, 'L' => 14,
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
                $sheet   = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;
                $lastRow = $sheet->getHighestRow();
                $totalRow = $lastRow;

                $border = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ];

                // ── Judul ──
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);

                // ── Header kolom: merge F2:J2 untuk nama group ──
                $sheet->mergeCells("F2:J2");
                $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E2F3'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ] + $border);
                $sheet->getRowDimension(2)->setRowHeight(22);

                // ── Baris data: border + alignment + angka ──
                for ($r = 3; $r <= $totalRow - 1; $r++) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray($border + [
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }

                if ($totalRow > 3) {
                    $firstData = 3;
                    $lastData  = $totalRow - 1;

                    $sheet->getStyle("A{$firstData}:A{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$firstData}:B{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$firstData}:E{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("F{$firstData}:G{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$firstData}:H{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("I{$firstData}:I{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("J{$firstData}:{$lastCol}{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // REKENING sebagai teks supaya digit panjang tidak jadi notasi ilmiah
                    $sheet->getStyle("C{$firstData}:C{$lastData}")->getNumberFormat()->setFormatCode('@');

                    foreach (['H', 'I', 'J', 'K', 'L'] as $col) {
                        $sheet->getStyle("{$col}{$firstData}:{$col}{$lastData}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // ── Baris TOTAL ──
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10],
                    'fill'      => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFF2CC'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ] + $border);

                foreach (['H', 'I', 'J', 'K', 'L'] as $col) {
                    $sheet->getStyle("{$col}{$totalRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }
                $sheet->getStyle("J{$totalRow}:{$lastCol}{$totalRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getRowDimension($totalRow)->setRowHeight(22);

                // ── Print setup: landscape F4 ──
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);
            },
        ];
    }
}
