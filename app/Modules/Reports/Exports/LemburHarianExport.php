<?php

namespace App\Modules\Reports\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class LemburHarianExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithCustomStartCell
{
    protected $data;
    protected $dates;
    protected $label;
    protected $rowNumber = 0;

    public function __construct($data, $dates, $label)
    {
        $this->data = $data;
        $this->dates = $dates;
        $this->label = $label;
    }

    public function array(): array
    {
        return $this->data;
    }

    public function startCell(): string
    {
        return 'A1';
    }

    public function map($row): array
    {
        $this->rowNumber++;
        $gender = $row['gender'] === 'male' ? 'L' : ($row['gender'] === 'female' ? 'P' : ($row['gender'] ?? ''));
        
        $mapped = [
            $this->rowNumber,
            $row['name'] ?? '',
            $row['jabatan'] ?? '',
            $gender,
            $row['tj_mk'] ?? 0,
            $row['tunjangan'] ?? 0,
            $row['upah_lembur_per_jam'] ?? 0,
        ];

        foreach ($this->dates as $dateStr) {
            $d = $row['days'][$dateStr] ?? null;
            if ($d) {
                $mapped[] = $d['kode'] ?: '';
                $mapped[] = $d['ha'] ?: '-';
                $mapped[] = $d['upah_per_hari'] ?? 0;
                $mapped[] = $d['lm'] ?: '';
                $mapped[] = $d['lembur'] ?: '';
                $mapped[] = $d['nominal'] ?? 0;
            } else {
                $mapped[] = '';
                $mapped[] = '-';
                $mapped[] = 0;
                $mapped[] = '';
                $mapped[] = '';
                $mapped[] = 0;
            }
        }

        return $mapped;
    }

    public function headings(): array
    {
        Carbon::setLocale('id');

        $titleRow = ['LAPORAN LEMBUR HARIAN — ' . strtoupper($this->label)];
        
        // Baris kosong
        $emptyRow = [''];

        $headerRow1 = [
            'No', 'Nama', 'Bagian / Jabatan', 'L/P',
            'Tj. Masa Kerja', 'Tunjangan', 'Upah Lembur Per Jam'
        ];
        $headerRow2 = ['', '', '', '', '', '', ''];

        foreach ($this->dates as $dateStr) {
            $formatted = Carbon::parse($dateStr)->translatedFormat('D, d M');
            $headerRow1[] = strtoupper($formatted);
            $headerRow1[] = ''; // Untuk merge cell
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow1[] = '';
            $headerRow1[] = '';

            $headerRow2[] = 'Kode';
            $headerRow2[] = 'H/A';
            $headerRow2[] = 'Upah Per Hari';
            $headerRow2[] = 'L/M';
            $headerRow2[] = 'Lembur';
            $headerRow2[] = 'Nominal';
        }

        return [
            $titleRow,
            $emptyRow,
            $headerRow1,
            $headerRow2,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 4;
        $numCols = 7 + (count($this->dates) * 6);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($numCols);

        // Title
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Header merging for fixed columns
        $sheet->mergeCells('A3:A4');
        $sheet->mergeCells('B3:B4');
        $sheet->mergeCells('C3:C4');
        $sheet->mergeCells('D3:D4');
        $sheet->mergeCells('E3:E4');
        $sheet->mergeCells('F3:F4');
        $sheet->mergeCells('G3:G4');

        // Header merging for dynamic date columns
        $colIndex = 8; // Start after fixed columns
        foreach ($this->dates as $date) {
            $startColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $endColStr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 5);
            $sheet->mergeCells("{$startColStr}3:{$endColStr}3");
            $colIndex += 6;
        }

        // Header row styles
        $sheet->getStyle("A3:{$lastCol}4")->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A3:{$lastCol}4")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EAED');
        $sheet->getStyle("A3:{$lastCol}4")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Borders
        $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Number format
        $sheet->getStyle("E5:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        $colIdx = 8;
        foreach ($this->dates as $date) {
            $upahCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 2);
            $nomCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 5);
            $sheet->getStyle("{$upahCol}5:{$upahCol}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$nomCol}5:{$nomCol}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            // Center align for some columns
            $kodeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $haCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $lmCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 3);
            $lbrCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 4);
            $sheet->getStyle("{$kodeCol}5:{$haCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$lmCol}5:{$lbrCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $colIdx += 6;
        }

        // Center align fixed
        $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D3:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('C5');

        // Column Widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(5);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(16);
        $sheet->getColumnDimension('G')->setWidth(16);

        $cIdx = 8;
        foreach ($this->dates as $date) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx))->setWidth(8); // Kode
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx+1))->setWidth(6); // H/A
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx+2))->setWidth(12); // Upah
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx+3))->setWidth(8); // LM
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx+4))->setWidth(8); // Lembur
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($cIdx+5))->setWidth(12); // Nominal
            $cIdx += 6;
        }

        return [];
    }
}

