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
use Carbon\Carbon;

class LemburHarianExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $data;
    protected $date;
    protected $rowNumber = 0;

    public function __construct($data, $date)
    {
        $this->data = $data;
        $this->date = $date;
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
            $row['name'] ?? '',
            $row['jabatan'] ?? '',
            $row['gender'] === 'male' ? 'L' : ($row['gender'] === 'female' ? 'P' : ($row['gender'] ?? '')),
            $row['tj_mk'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['upah_per_hari'] ?? 0,
            $row['upah_lembur_per_jam'] ?? 0,
            $row['shift_kode'] ?? '',
            $row['status'] ?? '-',
            $row['lembur_minggu'] ?: '',
            $row['lembur'] ?: '',
            $row['nominal'] ?? 0,
        ];
    }

    public function headings(): array
    {
        Carbon::setLocale('id');
        $formattedDate = Carbon::parse($this->date)->translatedFormat('l, d F Y');

        return [
            ['LAPORAN LEMBUR HARIAN — ' . strtoupper($formattedDate)],
            [''],
            [
                'No', 'Nama', 'Bagian / Jabatan', 'L/P',
                'Tj. Masa Kerja', 'Tunjangan', 'Upah Per Hari', 'Upah Lembur Per Jam',
                'Kode', 'H/A', 'L/M', 'Lembur', 'Nominal',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  'B' => 30, 'C' => 22, 'D' => 5,
            'E' => 16, 'F' => 16, 'G' => 16, 'H' => 16,
            'I' => 8,  'J' => 8,  'K' => 10, 'L' => 10, 'M' => 16,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 3;

        // Title
        $sheet->mergeCells('A1:M1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header row
        $sheet->getStyle('A3:M3')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A3:M3')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
        $sheet->getStyle('A3:M3')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Borders
        $sheet->getStyle("A3:M{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Number format
        $sheet->getStyle("E5:M{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        // Center align
        $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D3:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("I3:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('C4');

        return [];
    }
}
