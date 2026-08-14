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

class AttendanceDetailExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    protected array $data;
    protected array $employee;
    protected string $periodStart;
    protected string $periodEnd;
    protected float $totalOvertime;
    protected float $totalCount;
    protected string $sheetTitle;

    public function __construct(array $data, array $employee, string $periodStart, string $periodEnd, float $totalOvertime = 0, float $totalCount = 0, string $sheetTitle = 'Detail Absensi')
    {
        $this->data = $data;
        $this->employee = $employee;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->totalOvertime = $totalOvertime;
        $this->totalCount = $totalCount;
        $this->sheetTitle = $sheetTitle;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        $emp = $this->employee;

        return [
            ['Detail Absensi Karyawan: ' . ($emp['code'] ?? '-') . ' - ' . ($emp['name'] ?? '-')],
            ['Periode: ' . $this->periodStart . ' - ' . $this->periodEnd],
            [''],
            ['NIP', 'Nama', 'Hari / Tanggal', 'Actual In', 'Actual Out', 'Lembur'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastDataRow = count($this->data) + 5;
        $lastCol = 'F';

        // --- Title row (row 1) ---
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->getRowDimension(1)->setRowHeight(24);

        // --- Period row (row 2) ---
        $sheet->mergeCells('A2:' . $lastCol . '2');

        // --- TOTAL row ---
        $totalRow = $lastDataRow;
        $sheet->mergeCells('A' . $totalRow . ':E' . $totalRow);
        $sheet->getRowDimension($totalRow)->setRowHeight(22);
        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->setCellValue('F' . $totalRow, $this->totalOvertime . ' jam');
        $sheet->setCellValue('G' . $totalRow, $this->totalCount . ' jam');

        // ── APPLY BORDER SETELAH SEMUA MERGE ──
        $fullRange = 'A1:' . $lastCol . $lastDataRow;
        $sheet->getStyle($fullRange)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']],
            ],
            'font' => ['size' => 10],
        ]);

        // ── STYLING PER ROW (setelah border, biar nggak ketimpa) ──
        // Title
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Period
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Header
        $sheet->getStyle('A4:' . $lastCol . '4')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // TOTAL
        $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Center-align
        $sheet->getStyle('A5:A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D5:G' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Freeze header
        $sheet->freezePane('A5');

        return [];
    }
}
