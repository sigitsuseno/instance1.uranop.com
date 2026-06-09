<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class UangMakanResumeSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    protected $data;
    protected $period;

    public function __construct($data, $period)
    {
        $this->data = $data;
        $this->period = $period;
    }

    public function title(): string
    {
        return 'Resume';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  'B' => 30,
            'C' => 16, 'D' => 16, 'E' => 16,
            'F' => 14, 'G' => 12, 'H' => 12, 'I' => 16,
        ];
    }

    public function headings(): array
    {
        return [
            ['LAPORAN UANG MAKAN — RESUME ALL IN'],
            ['Periode: ' . ($this->period['start'] ?? '') . ' s/d ' . ($this->period['end'] ?? '')],
            [],
            ['No', 'Bagian', 'Uang Makan', 'Lembur Sabtu', 'Lembur Minggu',
             'Insentif', 'PBLT', 'Revisi', 'Total'],
        ];
    }

    public function array(): array
    {
        $resumeData = $this->data['resumeData'] ?? [];
        $rows = [];

        foreach ($resumeData as $i => $item) {
            $rows[] = [
                $i + 1,
                $item['bagian'] ?? '-',
                $item['uang_makan'] ?? 0,
                $item['lembur_sabtu'] ?? 0,
                $item['lembur_minggu'] ?? 0,
                $item['insentif'] ?? 0,
                $item['pblt'] ?? 0,
                $item['revisi'] ?? 0,
                $item['total'] ?? 0,
            ];
        }

        // Total row
        $totalUm = array_sum(array_column($resumeData, 'uang_makan'));
        $totalSabtu = array_sum(array_column($resumeData, 'lembur_sabtu'));
        $totalMinggu = array_sum(array_column($resumeData, 'lembur_minggu'));
        $totalInsentif = array_sum(array_column($resumeData, 'insentif'));
        $totalPblt = array_sum(array_column($resumeData, 'pblt'));
        $totalRevisi = array_sum(array_column($resumeData, 'revisi'));
        $grandTotal = array_sum(array_column($resumeData, 'total'));

        $rows[] = [
            '', 'TOTAL',
            $totalUm, $totalSabtu, $totalMinggu,
            $totalInsentif, $totalPblt, $totalRevisi, $grandTotal,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data['resumeData'] ?? []) + 4;
        $totalRow = $lastRow + 1;

        // Title
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A2:I2');
        $sheet->getStyle('A2')->getFont()->setSize(10);

        // Header
        $sheet->getStyle('A4:I4')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A4:I4')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
        $sheet->getStyle('A4:I4')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Data borders
        $sheet->getStyle("A4:I{$totalRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Total row
        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');

        // Number format for currency columns
        $sheet->getStyle("C5:I{$totalRow}")->getNumberFormat()
            ->setFormatCode('#,##0');

        $sheet->freezePane('C5');

        return [];
    }
}
