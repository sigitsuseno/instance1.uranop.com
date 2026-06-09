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

class UangMakanPerhitunganSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
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
        return 'Perhitungan Uang Makan';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  'B' => 30, 'C' => 14, 'D' => 8,
            'E' => 8,  'F' => 8,  'G' => 8,  'H' => 8,
            'I' => 16, 'J' => 16, 'K' => 16,
            'L' => 14, 'M' => 12, 'N' => 12, 'O' => 16,
        ];
    }

    public function headings(): array
    {
        return [
            ['LAPORAN UANG MAKAN — PERHITUNGAN'],
            ['Periode: ' . ($this->period['start'] ?? '') . ' s/d ' . ($this->period['end'] ?? '')],
            [],
            ['No', 'Nama', 'Grup UM', 'UM',
             'Sabtu Dua', 'Sabtu Full',
             'Minggu 1/2 HK', 'Minggu L',
             'Uang Makan', 'Lembur Sabtu', 'Lembur Minggu',
             'Insentif', 'PBLT', 'Revisi', 'Total'],
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['data'] as $i => $emp) {
            $rows[] = [
                $i + 1,
                $emp['employee_name'],
                $emp['title'] ?? '-',
                $emp['count_um'] > 0 ? $emp['count_um'] : '-',
                $emp['count_sabtu_dua'] > 0 ? $emp['count_sabtu_dua'] : '-',
                $emp['count_sabtu_full'] > 0 ? $emp['count_sabtu_full'] : '-',
                $emp['count_minggu_setengah'] > 0 ? $emp['count_minggu_setengah'] : '-',
                $emp['count_minggu_full'] > 0 ? $emp['count_minggu_full'] : '-',
                $emp['nominal_um'] ?? 0,
                $emp['nominal_sabtu'] ?? 0,
                $emp['nominal_minggu'] ?? 0,
                $emp['nominal_insentif'] ?? 0,
                $emp['nominal_pblt'] ?? 0,
                $emp['nominal_revisi'] ?? 0,
                $emp['total'] ?? 0,
            ];
        }

        // Total row
        $totalUm = array_sum(array_column($this->data['data'], 'nominal_um'));
        $totalSabtu = array_sum(array_column($this->data['data'], 'nominal_sabtu'));
        $totalMinggu = array_sum(array_column($this->data['data'], 'nominal_minggu'));
        $totalInsentif = array_sum(array_column($this->data['data'], 'nominal_insentif'));
        $totalPblt = array_sum(array_column($this->data['data'], 'nominal_pblt'));
        $totalRevisi = array_sum(array_column($this->data['data'], 'nominal_revisi'));
        $grandTotal = array_sum(array_column($this->data['data'], 'total'));

        $rows[] = [
            '', 'TOTAL', '', '', '', '', '', '',
            $totalUm, $totalSabtu, $totalMinggu,
            $totalInsentif, $totalPblt, $totalRevisi, $grandTotal,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data['data']) + 4; // +4 for headers
        $totalRow = $lastRow + 1;

        // Title
        $sheet->mergeCells('A1:O1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->mergeCells('A2:O2');
        $sheet->getStyle('A2')->getFont()->setSize(10);

        // Header
        $sheet->getStyle('A4:O4')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A4:O4')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
        $sheet->getStyle('A4:O4')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Data borders
        $sheet->getStyle("A4:O{$totalRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Total row
        $sheet->getStyle("A{$totalRow}:O{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:O{$totalRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');

        // Number format for currency columns (I-O)
        $sheet->getStyle("I5:O{$totalRow}")->getNumberFormat()
            ->setFormatCode('#,##0');

        // Center align count columns
        $sheet->getStyle("C5:H{$lastRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('C5');

        return [];
    }
}
