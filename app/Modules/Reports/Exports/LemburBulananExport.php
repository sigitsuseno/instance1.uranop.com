<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LemburBulananExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $data;
    protected $year;
    protected $rowNumber = 0;

    public function __construct($data, $year)
    {
        $this->data = $data;
        $this->year = $year;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $this->rowNumber++;
        $monthsData = [];

        for ($m = 1; $m <= 12; $m++) {
            $md = $row['months'][$m] ?? null;
            if ($md) {
                $monthsData[] = "Upah/jam: " . number_format($md['hourlyRate'], 0, ',', '.') . "\n"
                              . "Lembur: " . ($md['lm'] ?? 0) . " / " . ($md['calculated'] ?? 0) . "\n"
                              . "Uang Lbr: " . number_format($md['overtimePay'], 0, ',', '.');
            } else {
                $monthsData[] = '-';
            }
        }

        return array_merge([
            $this->rowNumber,
            $row['name'] ?? '',
            $row['gaji_pokok'] ?? 0,
            $row['premi'] ?? 0,
            $row['tj_mk'] ?? 0,
            $row['tunjangan'] ?? 0,
        ], $monthsData);
    }

    public function headings(): array
    {
        return [
            ['LAPORAN LEMBUR BULANAN — TAHUN ' . $this->year],
            [''],
            [
                'No', 'Nama', 'Gaji Pokok', 'Premi', 'Tj. MK', 'Tunjangan',
                'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,  'B' => 30, 'C' => 18, 'D' => 15, 'E' => 15, 'F' => 15,
            'G' => 22, 'H' => 22, 'I' => 22, 'J' => 22, 'K' => 22, 'L' => 22,
            'M' => 22, 'N' => 22, 'O' => 22, 'P' => 22, 'Q' => 22, 'R' => 22,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 3;

        // Title
        $sheet->mergeCells('A1:R1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header row
        $sheet->getStyle('A3:R3')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A3:R3')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
        $sheet->getStyle('A3:R3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Borders
        $sheet->getStyle("A3:R{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Number format
        $sheet->getStyle("D4:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');

        // Wrap text for month columns
        $sheet->getStyle("G4:R{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("G4:R{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // Center align
        $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('D4');

        return [];
    }
}
