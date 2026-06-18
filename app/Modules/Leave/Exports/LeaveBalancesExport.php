<?php

namespace App\Modules\Leave\Exports;

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

class LeaveBalancesExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
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
        return 'Saldo Cuti';
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            ['REKAP SALDO CUTI KARYAWAN'],
            $this->periodName ? ['Periode: ' . $this->periodName] : [''],
            [''],
            ['No', 'NIP', 'Nama Karyawan', 'Departemen', 'Jenis Cuti', 'Jatah Kuota (Hari)', 'Terpakai (Hari)', 'Sisa Saldo (Hari)'],
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            $row['nip'] ?? '-',
            $row['employee_name'] ?? '-',
            $row['department_name'] ?? '-',
            $row['leave_type_name'] ?? '-',
            (float)($row['entitlement'] ?? 0),
            (float)($row['used'] ?? 0),
            (float)($row['balance'] ?? 0),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 14,
            'C' => 28,
            'D' => 18,
            'E' => 20,
            'F' => 16,
            'G' => 16,
            'H' => 16,
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

        $headerRow = $this->periodName ? 4 : 3;
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle("A{$headerRow}:H{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);

        // Conditional formatting for balances > 0
        $sheet->getStyle("H" . ($headerRow + 1) . ":H{$lastRow}")->applyFromArray([
            'font' => ['color' => ['rgb' => '006100']],
        ]);
    }
}
