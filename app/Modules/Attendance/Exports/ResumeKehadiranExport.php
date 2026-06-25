<?php

namespace App\Modules\Attendance\Exports;

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

class ResumeKehadiranExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $periodLabel;
    protected $rowNumber = 0;

    protected const TOTAL_COLS = 15; // No, NIK, Nama, Departemen, HK, Deduct, Cuti, Izin, Sakit, Absen, Late, LM, LM Count, Lembur, OT Count

    public function __construct($data, string $periodLabel)
    {
        $this->data = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array) $data);
        $this->periodLabel = $periodLabel;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $this->rowNumber++;

        $employee = $row['employee'] ?? [];

        return [
            $this->rowNumber,
            $employee['employee_code'] ?? '',
            $employee['name'] ?? '',
            $employee['department']['name'] ?? '',
            $row['hari_kerja'] ?? 0,
            $row['deduct_day'] ?? 0,
            $row['cuti'] ?? 0,
            $row['izin'] ?? 0,
            $row['sakit'] ?? 0,
            $row['absen'] ?? 0,
            ($row['late_minutes'] ?? 0) > 0 ? $row['late_minutes'] : 0,
            $row['lm'] ?? 0,
            $row['lm_count'] ?? 0,
            $row['lembur'] ?? 0,
            $row['lembur_count'] ?? 0,
        ];
    }

    public function headings(): array
    {
        $row1 = ['RESUME KEHADIRAN — ' . strtoupper($this->periodLabel)];
        $row2 = [''];
        $row3 = [
            'No', 'NIK', 'Nama', 'Departemen',
            'Hari Kerja', 'Deduct', 'Cuti', 'Izin', 'Sakit', 'Absen',
            'Terlambat (mnt)', 'LM', 'LM Count', 'Lembur', 'OT Count',
        ];

        return [$row1, $row2, $row3];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 10,  // NIK
            'C' => 26,  // Nama
            'D' => 20,  // Departemen
            'E' => 10,  // Hari Kerja
            'F' => 9,   // Deduct
            'G' => 8,   // Cuti
            'H' => 8,   // Izin
            'I' => 8,   // Sakit
            'J' => 8,   // Absen
            'K' => 13,  // Terlambat
            'L' => 7,   // LM
            'M' => 9,   // LM Count
            'N' => 8,   // Lembur
            'O' => 9,   // OT Count
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = 'O';
        $dataStartRow = 4; // Row 1=title, 2=empty, 3=headers
        $lastRow = count($this->data) + $dataStartRow - 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dataStartRow, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                // Title row — merge + bold + center
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header row (3) — bold, fill, center
                $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A3:{$lastCol}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A3:{$lastCol}3")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Borders — data area only
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
                    ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // Center-align: No, NIK, HK, Deduct, Cuti, Izin, Sakit, Absen, Late
                $centerCols = ['A', 'B', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
                foreach ($centerCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // LM, LM Count, Lembur, OT Count — format 1 desimal
                $decimalCols = ['L', 'M', 'N', 'O'];
                foreach ($decimalCols as $col) {
                    $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                        ->getNumberFormat()->setFormatCode('#,##0.0');
                }

                // Negative values in red (Deduct)
                $sheet->getStyle("F{$dataStartRow}:F{$lastRow}")
                    ->getFont()->getColor()->setARGB('FFDC2626');

                // Freeze pane
                $sheet->freezePane('E4');
            },
        ];
    }
}
