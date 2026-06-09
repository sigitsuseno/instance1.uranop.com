<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class UangMakanRekapLemburSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, ShouldAutoSize
{
    protected $data;
    protected $period;

    public function __construct($data, $period)
    {
        $this->data = $data;
        $this->period = $period;
    }

    public function title(): string
    {
        return 'Rekap Lembur';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  // No
            'B' => 28, // Nama
        ];
    }

    public function headings(): array
    {
        $start = Carbon::parse($this->period['start']);
        $end = Carbon::parse($this->period['end']);
        $dates = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dates[] = $current->format('d');
            $dates[] = 'L';
            $current->addDay();
        }

        return array_merge(['No', 'Nama'], $dates);
    }

    public function array(): array
    {
        $start = Carbon::parse($this->period['start']);
        $end = Carbon::parse($this->period['end']);

        $rows = [];
        foreach ($this->data['data'] as $i => $emp) {
            $row = [
                $i + 1,
                $emp['employee_name'],
            ];

            $current = $start->copy();
            while ($current <= $end) {
                $dateStr = $current->toDateString();
                $day = $emp['days'][$dateStr] ?? null;
                $row[] = $day['status'] ?? '-';
                $lembur = $day['lembur'] ?? '-';
                $row[] = $lembur > 0 ? $lembur : '-';
                $current->addDay();
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $start = Carbon::parse($this->period['start']);
        $end = Carbon::parse($this->period['end']);
        $totalDays = $start->diffInDays($end) + 1;
        $lastCol = $this->getColumnLetter(2 + $totalDays * 2);
        $lastRow = count($this->data['data']) + 1;

        // Header styling
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Data borders
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Right-align lembur columns
        // Color coding for weekends (Sunday = col starting from 3rd)
        // Freeze panes
        $sheet->freezePane('C2');

        return [];
    }

    private function getColumnLetter($col)
    {
        $letter = '';
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intval($col / 26);
        }
        return $letter;
    }
}
