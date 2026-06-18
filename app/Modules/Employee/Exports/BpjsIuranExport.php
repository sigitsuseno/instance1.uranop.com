<?php

namespace App\Modules\Employee\Exports;

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

class BpjsIuranExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
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
            $row['employee']['name'] ?? ($row['name'] ?? '-'),
            $row['employee']['employee_code'] ?? ($row['employee_code'] ?? '-'),
            $row['gaji_pokok'] ?? 0,
            $row['tj_masa_kerja'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['bpjs_base_salary'] ?? 0,
            $row['employer_jht'] ?? 0,
            $row['employer_jkk'] ?? 0,
            $row['employer_jkm'] ?? 0,
            $row['employer_kesehatan'] ?? 0,
            $row['employer_jp'] ?? 0,
            $row['employee_jht'] ?? 0,
            $row['employee_kesehatan'] ?? 0,
            $row['employee_jp'] ?? 0,
        ];
    }

    public function headings(): array
    {
        $title = $this->periodName
            ? 'LAPORAN IURAN BPJS | PERIODE: ' . strtoupper($this->periodName)
            : 'LAPORAN IURAN BPJS';

        return [
            [$title],
            [
                'No',
                'NAMA KARYAWAN',
                'KODE',
                'GAJI POKOK',
                'TJ. MK',
                'TUNJANGAN',
                'DASAR BPJS',
                'JHT (Prsh)',
                'JKK (Prsh)',
                'JKM (Prsh)',
                'KES (Prsh)',
                'JP (Prsh)',
                'JHT (Kary)',
                'KES (Kary)',
                'JP (Kary)',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 28,  // Nama
            'C' => 12,  // Kode
            'D' => 14,  // Gaji Pokok
            'E' => 14,  // TJ MK
            'F' => 14,  // Tunjangan
            'G' => 14,  // Dasar BPJS
            'H' => 14,  // JHT (Prsh)
            'I' => 14,  // JKK (Prsh)
            'J' => 14,  // JKM (Prsh)
            'K' => 14,  // KES (Prsh)
            'L' => 14,  // JP (Prsh)
            'M' => 14,  // JHT (Kary)
            'N' => 14,  // KES (Kary)
            'O' => 14,  // JP (Kary)
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

                $lastRow = $this->rowNumber + 2; // +2 karena heading 2 baris
                if ($this->rowNumber === 0) {
                    $lastRow = 3;
                }

                // Title row
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->mergeCells('A1:O1');
                $sheet->getStyle('A1:O1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Heading row
                $sheet->getStyle('A2:O2')->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle('A2:O2')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);

                // Borders
                $sheet->getStyle("A2:O{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Number format (currency style)
                $sheet->getStyle("D3:O{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0');

                // Freeze pane (freeze header + 2 heading rows)
                $sheet->freezePane('B3');

                // Footer: total employer + total employee (if data exists)
                if ($this->rowNumber > 0) {
                    $footerRow = $lastRow + 1;

                    // Total Employer = H+I+J+K+L (JHT+JKK+JKM+KES+JP Prsh)
                    $sheet->setCellValue("A{$footerRow}", '');
                    $sheet->getStyle("A{$footerRow}:G{$footerRow}")->getFont()->setBold(true);
                    $sheet->setCellValue("G{$footerRow}", 'TOTAL BEBAN PERUSAHAAN');
                    $sheet->getStyle("G{$footerRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    // SUM formulas
                    $sheet->setCellValue("H{$footerRow}", "=SUM(H3:H{$lastRow})");
                    $sheet->setCellValue("I{$footerRow}", "=SUM(I3:I{$lastRow})");
                    $sheet->setCellValue("J{$footerRow}", "=SUM(J3:J{$lastRow})");
                    $sheet->setCellValue("K{$footerRow}", "=SUM(K3:K{$lastRow})");
                    $sheet->setCellValue("L{$footerRow}", "=SUM(L3:L{$lastRow})");
                    $sheet->getStyle("H{$footerRow}:L{$footerRow}")->getFont()->setBold(true);

                    // Total Employee = M+N+O
                    $sheet->setCellValue("M{$footerRow}", "=SUM(M3:M{$lastRow})");
                    $sheet->setCellValue("N{$footerRow}", "=SUM(N3:N{$lastRow})");
                    $sheet->setCellValue("O{$footerRow}", "=SUM(O3:O{$lastRow})");
                    $sheet->getStyle("M{$footerRow}:O{$footerRow}")->getFont()->setBold(true);

                    // Format footer
                    $sheet->getStyle("A{$footerRow}:O{$footerRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("D{$footerRow}:O{$footerRow}")
                        ->getNumberFormat()->setFormatCode('#,##0');
                }
            },
        ];
    }
}
