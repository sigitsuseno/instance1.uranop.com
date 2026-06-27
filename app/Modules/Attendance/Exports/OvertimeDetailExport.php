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
use Carbon\Carbon;

class OvertimeDetailExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithEvents
{
    protected $data;
    protected $employeeName;
    protected $periodLabel;

    public function __construct($data, string $employeeName, string $periodLabel)
    {
        $this->data = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array) $data);
        $this->employeeName = $employeeName;
        $this->periodLabel = $periodLabel;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function map($row): array
    {
        $date = Carbon::parse($row['date']);
        $dayName = $this->getDayName($row['date']);
        $isHoliday = $row['status'] === 'libur' || $row['status'] === 'off' || $date->isSunday();
        
        $checkIn = $row['check_in'] ? substr(explode(' ', $row['check_in'])[1] ?? explode('T', $row['check_in'])[1] ?? $row['check_in'], 0, 5) : '--:--';
        $checkOut = $row['check_out'] ? substr(explode(' ', $row['check_out'])[1] ?? explode('T', $row['check_out'])[1] ?? $row['check_out'], 0, 5) : '--:--';

        return [
            $date->format('d M Y') . ' (' . $dayName . ')' . ($isHoliday ? ' [Libur/Off]' : ''),
            $checkIn,
            $checkOut,
            $row['lm'] ?? 0,
            $row['lm_count'] ?? 0,
            $row['overtime'] ?? 0,
            $row['overtime_count'] ?? 0,
            ($row['overtime_count'] ?? 0) + ($row['lm_count'] ?? 0),
        ];
    }

    private function getDayName($dateStr)
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $d = new \DateTime($dateStr);
        return $days[$d->format('w')];
    }

    public function headings(): array
    {
        $row1 = ['DETAIL LEMBUR — ' . strtoupper($this->employeeName)];
        $row2 = ['PERIODE: ' . strtoupper($this->periodLabel)];
        $row3 = [''];
        $row4 = [
            'Tanggal', 'In', 'Out', 
            'LM (Menit)', 'Total L/M (Menit)', 
            'Lembur HB (Menit)', 'Total LHB (Menit)', 'Total Lembur (Menit)'
        ];

        return [$row1, $row2, $row3, $row4];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,  // Tanggal
            'B' => 10,  // In
            'C' => 10,  // Out
            'D' => 12,  // LM
            'E' => 18,  // Total L/M
            'F' => 18,  // Lembur HB
            'G' => 18,  // Total LHB
            'H' => 20,  // Total Lembur
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = 'H';
        $dataStartRow = 5; // Row 1,2=title, 3=empty, 4=headers
        $lastRow = count($this->data) + $dataStartRow - 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dataStartRow, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                // Title row
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header row
                $sheet->getStyle("A4:{$lastCol}4")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A4:{$lastCol}4")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A4:{$lastCol}4")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // Borders
                if (count($this->data) > 0) {
                    $sheet->getStyle("A4:{$lastCol}{$lastRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Center-align columns
                $centerCols = ['B', 'C', 'D', 'E', 'F', 'G', 'H'];
                foreach ($centerCols as $col) {
                    if (count($this->data) > 0) {
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                }
                
                // Number format
                $numberCols = ['D', 'E', 'F', 'G', 'H'];
                foreach ($numberCols as $col) {
                    if (count($this->data) > 0) {
                        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                            ->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Totals Row
                if (count($this->data) > 0) {
                    $totalsRow = $lastRow + 1;
                    $sheet->setCellValue("A{$totalsRow}", 'TOTAL:');
                    $sheet->getStyle("A{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->getFont()->setBold(true);
                    
                    $sheet->setCellValue("D{$totalsRow}", "=SUM(D{$dataStartRow}:D{$lastRow})");
                    $sheet->setCellValue("E{$totalsRow}", "=SUM(E{$dataStartRow}:E{$lastRow})");
                    $sheet->setCellValue("F{$totalsRow}", "=SUM(F{$dataStartRow}:F{$lastRow})");
                    $sheet->setCellValue("G{$totalsRow}", "=SUM(G{$dataStartRow}:G{$lastRow})");
                    $sheet->setCellValue("H{$totalsRow}", "=SUM(H{$dataStartRow}:H{$lastRow})");
                    
                    foreach ($numberCols as $col) {
                        $sheet->getStyle("{$col}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0');
                        $sheet->getStyle("{$col}{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    }
                    
                    $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
                }

                $sheet->freezePane('D5');
            },
        ];
    }
}
