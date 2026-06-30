<?php

namespace App\Modules\Supervisor\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AttendanceSummaryExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    protected array $data;
    protected string $periodStart;
    protected string $periodEnd;
    protected string $departmentName;

    public function __construct(array $data, string $periodStart, string $periodEnd, string $departmentName = 'Semua Departemen')
    {
        $this->data = $data;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->departmentName = $departmentName;
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            ['LAPORAN ABSENSI KARYAWAN'],
            ['Periode: ' . $this->periodStart . ' s/d ' . $this->periodEnd . ' | Departemen: ' . $this->departmentName],
            [''],
            ['No', 'NIP', 'Nama', 'Departemen', 'Jabatan', 'Hadir', 'Lembur (Jam)', 'Cuti', 'Izin', 'Sakit', 'Absen'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 4;
        $lastCol = 'K';

        // ── MERGE DULU ──
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── BORDER SETELAH MERGE ──
        $sheet->getStyle('A1:' . $lastCol . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']],
            ],
            'font' => ['size' => 9],
        ]);

        // ── STYLING ──
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getStyle('A4:' . $lastCol . '4')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(22);

        // Alternating row colors
        for ($row = 5; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F9FAFB'));
            }
        }

        // Center align
        $sheet->getStyle('A5:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B5:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F5:K' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('A5');

        return [];
    }
}
