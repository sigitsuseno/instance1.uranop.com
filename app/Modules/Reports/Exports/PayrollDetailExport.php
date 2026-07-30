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

class PayrollDetailExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
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
            $row['no_id'] ?? '-',
            $row['name'] ?? '-',
            $row['bagian'] ?? '-',
            $row['bagian'] ?? '-', // Bagian / Jabatan
            $row['gender'] ?? '-',
            $row['join_date'] ?? '-',
            $row['masa_kerja'] ?? '-',
            $row['status'] ?? '-',
            $row['jml_anak'] ?? 0,
            $row['account_no'] ?? '-',
            $row['premi'] ?? 0,
            $row['gaji_pokok'] ?? 0,
            $row['tj_masa_kerja'] ?? 0,
            $row['hk'] ?? 0,
            $row['lm'] ?? 0,
            $row['lbr_jam'] ?? 0,
            $row['gaji'] ?? 0,
            $row['lembur'] ?? 0,
            $row['revisi'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['premi_hadir'] ?? 0,
            $row['pblt'] ?? 0,
            $row['total'] ?? 0,
            $row['bpjs_tk'] ?? 0,
            $row['bpjs_ks'] ?? 0,
            $row['bpjs_pen'] ?? 0,
            $row['cashbon'] ?? 0,
            $row['pph'] ?? 0,
            $row['total_terima'] ?? 0,
        ];
    }

    public function headings(): array
    {
        return [
            ['PT KEMILAU UNGARAN SUKSES'],
            ['PRINTING KARANGJATI'],
            [''],
            ['A. KARYAWAN ALL IN', '', '', '', '', '', '', '', '', '', '', '', strtoupper($this->periodName ?? '')],
            [
                'No', 'ID No', 'NAMA', 'BAGIAN / JABATAN', 'BAGIAN / JABATAN', 'L/P',
                'THN MASUK KARYAWAN', 'MASA KERJA', 'STATUS (K/TK)', 'JML ANAK', 'ACCOUNT NO',
                'PREMI', 'GAJI POKOK', 'TJ. MASA KERJA', 'HK', 'L/M', 'LBR JAM', 'GAJI',
                'LEMBUR', 'REVISI', 'TUNJANGAN', 'PREMI HADIR', 'PBLT', 'TOTAL',
                'BPJS TENAGA KERJA', 'BPJS KESEHATAN', 'BPJS PENSIUN', 'CASHBON', 'PPH', 'TOTAL TERIMA'
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 10, 'C' => 25, 'D' => 20, 'E' => 20, 'F' => 5,
            'G' => 12, 'H' => 8,  'I' => 8,  'J' => 8,  'K' => 15,
            'L' => 12, 'M' => 12, 'N' => 12, 'O' => 5,  'P' => 5,  'Q' => 5,  'R' => 12,
            'S' => 12, 'T' => 12, 'U' => 12, 'V' => 12, 'W' => 8,  'X' => 15,
            'Y' => 15, 'Z' => 15, 'AA' => 15, 'AB' => 12, 'AC' => 12, 'AD' => 15,
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
                
                $sheet->setCellValue('M4', strtoupper($this->periodName));
                
                $lastRow = $this->rowNumber + 5;

                // Header styles
                $sheet->getStyle('A5:AD5')->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle('A5:AD5')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // Borders
                if ($this->rowNumber > 0) {
                    $sheet->getStyle("A5:AD{$lastRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Number format
                $sheet->getStyle("L6:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("R6:AD{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Freeze Pane
                $sheet->freezePane('D6');
            },
        ];
    }
}
