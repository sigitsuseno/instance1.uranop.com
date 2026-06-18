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

class GajiKaryawanExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
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
            $row['employee_code'] ?? '-',
            $row['name'] ?? '-',
            $row['gender'] ?? '-',
            $row['department'] ?? '-',
            $row['position'] ?? '-',
            $row['join_year'] ?? '-',
            $row['premi'] ?? 0,
            $row['gaji_pokok'] ?? 0,
            $row['tj_masa_kerja'] ?? 0,
            $row['hari_kerja'] ?? 0,
            $row['lm'] ?? 0,
            $row['lembur_count'] ?? 0,
            $row['gaji'] ?? 0,
            $row['upah_lembur'] ?? 0,
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
            $row['gaji_bersih'] ?? 0,
        ];
    }

    public function headings(): array
    {
        return [
            ['LAPORAN GAJI KARYAWAN', '', '', '', '', '', '', '', '', '', '', '', strtoupper($this->periodName)],
            [
                'No', 'ID No', 'NAMA KARYAWAN', 'L/P', 'BAGIAN', 'JABATAN',
                'THN MSK', 'PREMI', 'GAJI POKOK', 'TJ. MK', 'HK', 'L/M', 'LBR JAM', 'GAJI',
                'LEMBUR', 'REVISI', 'TUNJANGAN', 'PR. HADIR', 'PBLT', 'TOTAL',
                'BPJS TK', 'BPJS KES', 'BPJS PEN', 'CASH BON', 'PPH', 'TRIMA'
            ]
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 10, 'C' => 25, 'D' => 5,  'E' => 15, 'F' => 15,
            'G' => 10, 'H' => 12, 'I' => 12, 'J' => 12, 'K' => 5,  'L' => 5,  'M' => 5,  'N' => 12,
            'O' => 12, 'P' => 12, 'Q' => 12, 'R' => 12, 'S' => 10, 'T' => 15,
            'U' => 12, 'V' => 12, 'W' => 12, 'X' => 12, 'Y' => 12, 'Z' => 15,
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
                
                $lastRow = $this->rowNumber + 2;

                // Header styles
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->mergeCells('A1:L1');
                
                $sheet->getStyle('M1')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('M1:Z1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->mergeCells('M1:Z1');

                $sheet->getStyle('A2:Z2')->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle('A2:Z2')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // Borders
                if ($this->rowNumber > 0) {
                    $sheet->getStyle("A2:Z{$lastRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Number format
                $sheet->getStyle("H3:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("N3:Z{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                // Freeze Pane
                $sheet->freezePane('D3');
            },
        ];
    }
}
