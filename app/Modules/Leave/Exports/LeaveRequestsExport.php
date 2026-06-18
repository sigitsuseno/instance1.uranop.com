<?php

namespace App\Modules\Leave\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LeaveRequestsExport implements FromArray, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    protected $data;
    protected $periodName;
    protected $rowNumber = 0;

    public function __construct($data, $periodName = '')
    {
        $this->data = array_values($data instanceof \Illuminate\Support\Collection ? $data->toArray() : (array)$data);
        $this->periodName = $periodName;
    }

    public function title(): string
    {
        return 'Pengajuan Cuti';
    }

    public function array(): array
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            ['REKAP PENGAJUAN CUTI'],
            $this->periodName ? ['Periode: ' . $this->periodName] : [''],
            [''],
            ['No', 'NIP', 'Nama Karyawan', 'Departemen', 'Tipe Cuti', 'Tgl Mulai', 'Tgl Selesai', 'Durasi (Hari)', 'Status', 'Alasan'],
        ];
    }

    public function map($row): array
    {
        $this->rowNumber++;
        return [
            $this->rowNumber,
            $row['employee']['nip'] ?? $row['nip'] ?? '-',
            $row['employee']['name'] ?? $row['employee_name'] ?? '-',
            $row['employee']['department']['name'] ?? $row['department'] ?? '-',
            $row['leave_type']['name'] ?? $row['leave_type'] ?? '-',
            $row['start_date'] ?? '-',
            $row['end_date'] ?? '-',
            $row['days_requested'] ?? 0,
            $this->statusLabel($row['status'] ?? '-'),
            $row['reason'] ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 14,
            'C' => 28,
            'D' => 18,
            'E' => 18,
            'F' => 14,
            'G' => 14,
            'H' => 12,
            'I' => 14,
            'J' => 30,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->data) + 4;

        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($this->periodName) {
            $sheet->mergeCells('A2:J2');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Header row styling
        $headerRow = $this->periodName ? 4 : 3;
        $sheet->getStyle("A{$headerRow}:J{$headerRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data styling
        $sheet->getStyle("A{$headerRow}:J{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    private function statusLabel($status)
    {
        $map = [
            'pending' => 'Pending',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
        ];
        return $map[$status] ?? $status;
    }
}
