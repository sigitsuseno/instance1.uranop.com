<?php

namespace App\Modules\Supervisor\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AttendanceSingleSheetExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    protected array $employeesData;

    /**
     * Metadata posisi tiap blok karyawan di sheet: headerRow, firstDataRow, lastDataRow, totalRow.
     */
    protected array $blocks = [];

    /**
     * @param array $employeesData Sama seperti AttendanceMultipleDetailExport:
     *      [
     *          [
     *              'employee'     => ['name','code','department','position'],
     *              'rows'         => [ [NIP, Nama, Hari/Tanggal, Actual In, Actual Out, Lembur, Count], ... ],
     *              'totalOvertime'=> float,
     *              'totalCount'   => float, // sum kolom count (jam, kolom G tanpa header)
     *          ], ...
     *      ]
     */
    public function __construct(array $employeesData)
    {
        $this->employeesData = $employeesData;
    }

    public function title(): string
    {
        return 'Detail Absensi';
    }

    public function array(): array
    {
        $out = [];
        $row = 1;
        $total = count($this->employeesData);
        $i = 0;

        foreach ($this->employeesData as $data) {
            $i++;
            $headerRow = $row;
            $out[] = ['NIP', 'Nama', 'Hari / Tanggal', 'Actual In', 'Actual Out', 'Lembur'];
            $row++;

            $firstDataRow = $row;
            foreach (($data['rows'] ?? []) as $r) {
                $out[] = $r;
                $row++;
            }
            $lastDataRow = $row - 1;

            $totalRow = $row;
            $out[] = ['TOTAL', '', '', '', '', ($data['totalOvertime'] ?? 0) . ' jam', ($data['totalCount'] ?? 0) . ' jam'];
            $row++;

            // Jarak 2 baris kosong sebelum blok karyawan berikutnya
            if ($i < $total) {
                $out[] = ['', '', '', '', '', ''];
                $out[] = ['', '', '', '', '', ''];
                $row += 2;
            }

            $this->blocks[] = compact('headerRow', 'firstDataRow', 'lastDataRow', 'totalRow');
        }

        return $out;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'F';

        foreach ($this->blocks as $block) {
            $headerRow = $block['headerRow'];
            $firstDataRow = $block['firstDataRow'];
            $lastDataRow = $block['lastDataRow'];
            $totalRow = $block['totalRow'];

            // Border + font untuk seluruh blok
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $totalRow)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']],
                ],
                'font' => ['size' => 10],
            ]);

            // Header kolom
            $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($headerRow)->setRowHeight(20);

            // TOTAL row
            $sheet->mergeCells('A' . $totalRow . ':E' . $totalRow);
            $sheet->getRowDimension($totalRow)->setRowHeight(22);
            $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3F4F6']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Center data
            if ($lastDataRow >= $firstDataRow) {
                $sheet->getStyle('A' . $firstDataRow . ':A' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . $firstDataRow . ':G' . $lastDataRow)
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
        }

        return [];
    }
}
