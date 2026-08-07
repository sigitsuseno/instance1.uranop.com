<?php

namespace App\Modules\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ConsecutiveDayExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $periodName;
    protected $rowNumber = 0;

    public function __construct($data, $periodName = '')
    {
        $this->data = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->periodName = $periodName;
    }

    public function title(): string
    {
        return 'Consecutive Day';
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            ['REKAP CONSECUTIVE DAY'],
            $this->periodName ? ['Periode: ' . $this->periodName] : [''],
            [''],
            ['No', 'NIP', 'Nama Karyawan', 'Tipe', 'Dari', 'Sampai', 'Hari', 'Catatan'],
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;
        $emp = $row['employee'] ?? [];

        return [
            $this->rowNumber,
            $emp['employee_code'] ?? $emp['nip'] ?? $row['employee_code'] ?? '-',
            $emp['name'] ?? '-',
            ($row['type'] ?? '') === 'absent' ? 'Absen' : 'Hadir',
            $row['start_date'] ?? '-',
            $row['end_date'] ?? '-',
            $row['total_days'] ?? 0,
            $row['notes'] ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 14,
            'C' => 28,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 8,
            'H' => 30,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 4;

        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($this->periodName) {
            $sheet->mergeCells('A2:H2');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Header row styling
        $headerRow = $this->periodName ? 4 : 3;
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data styling
        $sheet->getStyle("A{$headerRow}:H{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }
}
