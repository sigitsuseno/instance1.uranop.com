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

class OvertimeSummaryExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $periodLabel;
    protected $rowNumber = 0;

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

        return [
            $this->rowNumber,
            $row['employee_code'] ?? '',
            $row['employee_name'] ?? '',
            $row['department'] ?? '',
            $row['total_hadir'] ?? 0,
            $row['lm'] ?? 0,
            $row['total_lm'] ?? 0,
            $row['lembur_hb'] ?? 0,
            $row['total_lhb'] ?? 0,
            $row['total_lembur'] ?? 0,
        ];
    }

    public function headings(): array
    {
        $row1 = ['REKAPITULASI LEMBUR — ' . strtoupper($this->periodLabel)];
        $row2 = [''];
        $row3 = [
            'No', 'NIK', 'Nama', 'Departemen',
            'Total Hadir', 'LM (Menit)', 'Total L/M (Menit)', 
            'Lembur HB (Menit)', 'Total LHB (Menit)', 'Total Lembur (Menit)'
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
            'E' => 12,  // Total Hadir
            'F' => 12,  // LM
            'G' => 15,  // Total L/M
            'H' => 17,  // Lembur HB
            'I' => 17,  // Total LHB
            'J' => 20,  // Total Lembur
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = 'J';
        $dataStartRow = 4; // Row 1=title, 2=empty, 3=headers
        $lastRow = count($this->data) + $dataStartRow - 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dataStartRow, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                // Title row
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header row
                $sheet->getStyle("A3:{$lastCol}3")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A3:{$lastCol}3")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A3:{$lastCol}3")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Borders
                if (count($this->data) > 0) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Center-align: No, NIK, Total Hadir
                $centerCols = ['A', 'B', 'E', 'F', 'G', 'H', 'I', 'J'];
                foreach ($centerCols as $col) {
                    if (count($this->data) > 0) {
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }

                // Number format
                $numberCols = ['E', 'F', 'G', 'H', 'I', 'J'];
                foreach ($numberCols as $col) {
                    if (count($this->data) > 0) {
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Freeze pane
                $sheet->freezePane('E4');
            },
        ];
    }
}
