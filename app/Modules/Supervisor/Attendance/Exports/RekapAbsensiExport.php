<?php

namespace App\Modules\Supervisor\Attendance\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RekapAbsensiExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    protected $employees;
    protected string $startDate;
    protected string $endDate;
    protected string $companyName;
    protected array $dates;
    protected array $data;

    public function __construct($employees, string $startDate, string $endDate, string $companyName = '')
    {
        $this->employees = $employees;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->companyName = $companyName;

        // Build date range
        $this->dates = [];
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        while ($current <= $end) {
            $this->dates[] = $current->copy();
            $current->addDay();
        }

        // Build data
        $this->buildData();
    }

    protected function buildData(): void
    {
        $this->data = [];

        foreach ($this->employees as $i => $employee) {
            $logs = $employee->autologs->keyBy(fn ($l) => $l->date->toDateString());

            $row = [
                'no' => $i + 1,
                'nip' => $employee->employee_code,
                'nama' => $employee->name,
                'departemen' => $employee->department?->name ?? '-',
                'jabatan' => $employee->position?->name ?? '-',
            ];

            // Summary counters
            $hadir = 0;
            $lemburTotal = 0;
            $cuti = 0;
            $izin = 0;
            $sakit = 0;
            $absen = 0;

            foreach ($this->dates as $date) {
                $dateStr = $date->toDateString();
                $log = $logs->get($dateStr);

                if ($log) {
                    $status = $this->getStatusText($log);
                    $lemburJam = round((($log->lembur ?? 0) + ($log->lm ?? 0)) / 60, 1);

                    switch ($status) {
                        case 'H': $hadir++; break;
                        case 'C': $cuti++; break;
                        case 'I': $izin++; break;
                        case 'S': $sakit++; break;
                        default: if ($status === '-' || $status === '') $absen++; break;
                    }
                    if ($lemburJam > 0) $lemburTotal += $lemburJam;
                } else {
                    $absen++;
                }
            }

            $row['hadir'] = $hadir;
            $row['lembur'] = round($lemburTotal, 1);
            $row['cuti'] = $cuti;
            $row['izin'] = $izin;
            $row['sakit'] = $sakit;
            $row['absen'] = $absen;

            $this->data[] = $row;
        }
    }

    protected function getStatusText($log): string
    {
        if (! $log) return '';

        if ($log->sakit_duration > 0) return 'S';
        if ($log->izin_duration > 0) return 'I';

        return match ($log->status) {
            'present' => 'H',
            'leave' => 'C',
            'absent' => '-',
            'holiday' => 'L',
            'off' => 'O',
            default => '-',
        };
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    public function array(): array
    {
        return array_map(fn ($row) => [
            $row['no'],
            $row['nip'],
            $row['nama'],
            $row['departemen'],
            $row['jabatan'],
            $row['hadir'],
            $row['lembur'],
            $row['cuti'],
            $row['izin'],
            $row['sakit'],
            $row['absen'],
        ], $this->data);
    }

    public function headings(): array
    {
        return [
            [$this->companyName],
            ['LAPORAN REKAP ABSENSI KARYAWAN'],
            ['Periode: ' . $this->startDate . ' s/d ' . $this->endDate],
            [''],
            ['No', 'NIP', 'Nama', 'Departemen', 'Jabatan', 'Hadir', 'Lembur (Jam)', 'Cuti', 'Izin', 'Sakit', 'Absen'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 5;
        $lastCol = 'K';

        // Merge title rows
        $sheet->mergeCells('A1:' . $lastCol . '1');
        $sheet->mergeCells('A2:' . $lastCol . '2');
        $sheet->mergeCells('A3:' . $lastCol . '3');

        // Borders
        $sheet->getStyle('A1:' . $lastCol . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']],
            ],
            'font' => ['size' => 9],
        ]);

        // Title
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['color' => ['rgb' => '6B7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Header row
        $sheet->getStyle('A5:' . $lastCol . '5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(22);

        // Alternating rows
        for ($row = 6; $row <= $lastRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':' . $lastCol . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('F9FAFB'));
            }
        }

        // Center align
        $sheet->getStyle('A6:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B6:B' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F6:' . $lastCol . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->freezePane('A6');

        return [];
    }
}
