<?php

namespace App\Modules\Employee\Exports\Sheets;

use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Position;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompensationResumeSheet implements WithTitle, WithEvents, WithColumnWidths
{
    // 5 kolom: A=NO, B=POSISI, C=TOTAL KARYAWAN (L), D=TOTAL KARYAWAN (P), E=TOTAL KOMPENSASI
    private const LAST_COL = 'E';

    /** Tampilkan 0 sebagai "Rp -", angka sebagai "Rp 490.400" */
    private const RP_FORMAT = '"Rp " #,##0;"Rp "-#,##0;"Rp -"';

    protected Collection $contracts;
    protected int $year;
    protected int $month;
    protected string $companyName;
    protected string $monthLabel;
    protected array $resume = [];

    public function __construct(Collection $contracts, int $year, int $month)
    {
        $this->contracts = $contracts;
        $this->year = $year;
        $this->month = $month;
        $this->companyName = Company::first()?->name ?? '';
        $this->monthLabel = strtoupper(Carbon::createFromDate($year, $month, 1)->locale('id')->translatedFormat('F'));

        $this->buildResume();
    }

    public function title(): string
    {
        return 'RESUME';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 24,
            'C' => 18,
            'D' => 18,
            'E' => 24,
        ];
    }

    /**
     * Agregasi kontrak periode terpilih per posisi karyawan,
     * split gender (L/P), dan jumlah kompensasi per posisi.
     */
    protected function buildResume(): void
    {
        $period = sprintf('%d-%02d', $this->year, $this->month);

        $agg = [];
        foreach ($this->contracts as $contract) {
            $employee = $contract->employee;
            if (! $employee) {
                continue;
            }

            $gender = $employee->gender;
            if ($gender !== 'L' && $gender !== 'P') {
                continue;
            }

            $gajiPokok = $employee->gaji_pokok($period);
            $tjMasaKerja = $employee->tjMasaKerja($period);
            $durationMonths = (int) ($contract->duration_months ?? 0);
            $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
            $totalRounded = (float) (ceil($durationMonths * $monthlyRate / 100) * 100);

            $pid = $employee->position_id;
            $agg[$pid]['L'] = ($agg[$pid]['L'] ?? 0) + ($gender === 'L' ? 1 : 0);
            $agg[$pid]['P'] = ($agg[$pid]['P'] ?? 0) + ($gender === 'P' ? 1 : 0);
            $agg[$pid]['comp'] = ($agg[$pid]['comp'] ?? 0) + $totalRounded;
        }

        // Hanya tampilkan posisi yang muncul pada daftar karyawan yang diexport
        $positionIds = collect(array_keys($agg))
            ->filter(fn ($id) => $id !== null)
            ->values()
            ->all();
        $positions = Position::whereIn('id', $positionIds)
            ->orderBy('id')
            ->get(['id', 'name']);

        $no = 1;
        $this->resume = [];
        foreach ($positions as $position) {
            $data = $agg[$position->id] ?? ['L' => 0, 'P' => 0, 'comp' => 0.0];
            $this->resume[] = [
                'no'      => $no++,
                'position' => $position->name,
                'L'       => (int) $data['L'],
                'P'       => (int) $data['P'],
                'comp'    => (float) $data['comp'],
            ];
        }

        // Kontrak dengan karyawan yang tidak punya posisi
        if (isset($agg[null]) && array_sum($agg[null]) > 0) {
            $data = $agg[null];
            $this->resume[] = [
                'no'      => $no++,
                'position' => 'TANPA POSISI',
                'L'       => (int) $data['L'],
                'P'       => (int) $data['P'],
                'comp'    => (float) $data['comp'],
            ];
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;

                // ── Judul ──
                $sheet->setCellValue('A1', 'RESUME UANG KOMPENSASI');
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F4E79']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(26);

                $sheet->setCellValue('A2', $this->companyName);
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->setCellValue('A3', (string) $this->year);
                $sheet->mergeCells("A3:{$lastCol}3");
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => ['size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->setCellValue('A4', $this->monthLabel);
                $sheet->mergeCells("A4:{$lastCol}4");
                $sheet->getStyle('A4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // ── Header tabel ──
                $headerRow = 6;
                $headers = ['NO', 'POSISI', 'TOTAL KARYAWAN (L)', 'TOTAL KARYAWAN (P)', 'TOTAL KOMPENSASI'];
                foreach ($headers as $i => $header) {
                    $col = $this->colFromIndex($i);
                    $sheet->setCellValue("{$col}{$headerRow}", $header);
                    $sheet->getStyle("{$col}{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                    ]);
                }
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ── Data rows ──
                $row = $headerRow + 1;
                $totalL = 0;
                $totalP = 0;
                $totalComp = 0.0;

                foreach ($this->resume as $item) {
                    $sheet->setCellValue("A{$row}", $item['no']);
                    $sheet->setCellValue("B{$row}", $item['position']);
                    $sheet->setCellValue("C{$row}", $item['L']);
                    $sheet->setCellValue("D{$row}", $item['P']);
                    $sheet->setCellValue("E{$row}", $item['comp']);

                    foreach (['A', 'B', 'C', 'D', $lastCol] as $col) {
                        $sheet->getStyle("{$col}{$row}")->applyFromArray([
                            'font' => ['size' => 10],
                            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                    }
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("{$lastCol}{$row}")->getNumberFormat()->setFormatCode(self::RP_FORMAT);

                    $totalL += $item['L'];
                    $totalP += $item['P'];
                    $totalComp += $item['comp'];
                    $row++;
                }

                // ── Baris TOTAL ──
                $sheet->mergeCells("A{$row}:B{$row}");
                $sheet->setCellValue("A{$row}", 'TOTAL');
                $sheet->setCellValue("C{$row}", $totalL);
                $sheet->setCellValue("D{$row}", $totalP);
                $sheet->setCellValue("{$lastCol}{$row}", $totalComp);

                foreach (['A', 'B', 'C', 'D', $lastCol] as $col) {
                    $sheet->getStyle("{$col}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6E4F0']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B0B0']]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                }
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("{$lastCol}{$row}")->getNumberFormat()->setFormatCode(self::RP_FORMAT);
                $row++;

                // ── Ringkasan Pembayaran / Rekapitulasi ──
                $row += 2;
                $sheet->setCellValue("A{$row}", 'Ringkasan Pembayaran / Rekapitulasi:');
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
                $row++;

                $this->writeSummaryLine($sheet, $row++, 'Total Kompensasi', $totalComp);
                $this->writeSummaryLine($sheet, $row++, 'Pembayaran / Penyesuaian', $totalComp);
                $this->writeSummaryLine($sheet, $row++, 'Sisa', 0.0);
            },
        ];
    }

    protected function colFromIndex(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    }

    protected function writeSummaryLine(Worksheet $sheet, int $row, string $label, float $value): void
    {
        $sheet->setCellValue("A{$row}", $label . ':');
        $sheet->setCellValue(self::LAST_COL . $row, $value);

        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle(self::LAST_COL . $row)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle(self::LAST_COL . $row)->getNumberFormat()->setFormatCode(self::RP_FORMAT);
    }
}
