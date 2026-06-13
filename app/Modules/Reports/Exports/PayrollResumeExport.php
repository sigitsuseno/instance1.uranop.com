<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PayrollResumeExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $periodName;
    protected $rowNumber = 0;

    public function __construct($data, $periodName)
    {
        $this->data = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->periodName = $periodName;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row['bagian'] ?? '-',
            $row['jml_karyawan_l'] ?? 0,
            $row['jml_karyawan_p'] ?? 0,
            $row['jml_karyawan_total'] ?? 0,
            $row['gaji'] ?? 0,
            $row['lembur'] ?? 0,
            $row['revisi'] ?? 0,
            $row['tj_masa_kerja'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['premi_hadir'] ?? 0,
            $row['pblt'] ?? 0,
            $row['total'] ?? 0,
            $row['bpjs_tk'] ?? 0,
            $row['bpjs_ks'] ?? 0,
            $row['bpjs_pen'] ?? 0,
            $row['cashbon'] ?? 0,
            $row['revisi_pph'] ?? 0,
            $row['total_terima'] ?? 0,
        ];
    }

    public function headings(): array
    {
        return [
            ['PT. KEMILAU UNGARAN SUKSES'],
            ['FEBRUARI 2026'], // TODO dynamic period
            [''],
            ['RESUME GAJI'],
            ['I. KARYAWAN'],
            [
                'No', 'BAGIAN', 'JML KARYAWAN', '', '', 'GAJI', 'LEMBUR', 'REVISI', 'TJ. MASA KERJA', 'TUNJANGAN', 'PREMI HADIR', 'PBLT', 'TOTAL', 'BPJS TENAGA KERJA', 'BPJS KESEHATAN', 'BPJS PENSIUN', 'CASHBON', 'REVISI PPH', 'TOTAL TERIMA'
            ],
            [
                '', '', 'L', 'P', 'Total', '', '', '', '', '', '', '', '', '', '', '', '', '', ''
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 20, 'C' => 5, 'D' => 5, 'E' => 6,
            'F' => 15, 'G' => 12, 'H' => 10, 'I' => 12, 'J' => 12,
            'K' => 12, 'L' => 10, 'M' => 15, 'N' => 15, 'O' => 15,
            'P' => 15, 'Q' => 12, 'R' => 12, 'S' => 16,
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
                
                // Merge headers
                $sheet->mergeCells('C6:E6');
                
                // Vertical merges for headers
                $colsToMerge = ['A', 'B', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S'];
                foreach ($colsToMerge as $col) {
                    $sheet->mergeCells("{$col}6:{$col}7");
                }

                $sheet->setCellValue('A2', strtoupper($this->periodName));

                $lastRow = $this->rowNumber + 7; // data starts at row 8

                // Header styles
                $sheet->getStyle('A6:S7')->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle('A6:S7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // Borders
                if ($this->rowNumber > 0) {
                    $sheet->getStyle("A6:S{$lastRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Number format
                $sheet->getStyle("F8:S{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Alignments
                $sheet->getStyle("A8:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
