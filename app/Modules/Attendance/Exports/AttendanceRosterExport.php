<?php

namespace App\Modules\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Carbon\Carbon;

class AttendanceRosterExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithEvents
{
    protected $rows;
    protected $dates;
    protected $periodLabel;

    public function __construct(array $rows, array $dates, string $periodLabel)
    {
        $this->rows = $rows;
        $this->dates = $dates;
        $this->periodLabel = $periodLabel;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        $days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        $row1 = ['ROSTER ABSENSI & LEMBUR'];
        $row2 = ['PERIODE: ' . strtoupper($this->periodLabel)];
        $row3 = [''];

        $headers = ['Nama Karyawan', 'NIP'];
        foreach ($this->dates as $date) {
            $d = Carbon::parse($date);
            $label = $d->format('d/m') . "\n" . $days[$d->format('w')];
            $headers[] = $label;
            $headers[] = $label . "\nOT";
        }

        return [$row1, $row2, $row3, $headers];
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 30, 'B' => 14];
        $lastCol = $this->lastColumn();
        if ($lastCol > 'B') {
            $col = 'C';
            $idx = 0;
            while (true) {
                $widths[$col] = ($idx % 2 === 0) ? 10 : 10;
                if ($col === $lastCol) break;
                $col++;
                $idx++;
            }
        }
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        $lastCol = $this->lastColumn();
        $dataStartRow = 5;
        $lastRow = count($this->rows) + $dataStartRow - 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $dataStartRow, $lastRow) {
                $sheet = $event->sheet->getDelegate();

                // Title
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Header row
                $sheet->getStyle("A4:{$lastCol}4")->getFont()->setBold(true)->setSize(9);
                $sheet->getStyle("A4:{$lastCol}4")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
                $sheet->getStyle("A4:{$lastCol}4")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(4)->setRowHeight(35);
                $sheet->getStyle("A4:{$lastCol}4")->getAlignment()->setWrapText(true);

                // Borders
                if (count($this->rows) > 0) {
                    $sheet->getStyle("A4:{$lastCol}{$lastRow}")->getBorders()
                        ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                }

                // Center-align all date + OT columns
                if ($lastCol > 'B') {
                    $col = 'C';
                    while (true) {
                        if (count($this->rows) > 0) {
                            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                        if ($col === $lastCol) break;
                        $col++;
                    }

                    // Number format for OT columns (every 2nd column: D, F, H, ...)
                    $otCol = 'D';
                    while ($otCol <= $lastCol) {
                        if (count($this->rows) > 0) {
                            $sheet->getStyle("{$otCol}{$dataStartRow}:{$otCol}{$lastRow}")
                                ->getNumberFormat()->setFormatCode('#,##0.0');
                        }
                        if ($otCol === $lastCol || (++$otCol && $otCol > $lastCol)) break;
                        $otCol++;
                    }
                }

                // Freeze pane at C5 (Nama + NIP tetap)
                $sheet->freezePane('C5');
            },
        ];
    }

    protected function lastColumn(): string
    {
        $totalCols = 2 + (count($this->dates) * 2); // A + B + (status + OT) per date
        return Coordinate::stringFromColumnIndex($totalCols);
    }

    /**
     * Shorten status to a single code.
     */
    public static function shortStatus(?string $status): string
    {
        return match ($status) {
            'hadir'  => 'H',
            'absent' => 'A',
            'libur'  => 'L',
            'off'    => 'OFF',
            'ct', 'cth', 'cti', 'ctm' => 'CT',
            'ckm'    => 'CKM',
            'imt'    => 'IMT',
            'ipa'    => 'IPA',
            'itm'    => 'ITM',
            'skt'    => 'SKT',
            default  => strtoupper(substr($status ?? 'absent', 0, 3)),
        };
    }
}