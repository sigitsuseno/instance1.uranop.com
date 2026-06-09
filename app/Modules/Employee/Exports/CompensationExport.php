<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\EmployeeContract;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompensationExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles
{
    protected $month;
    protected $year;
    protected $periode;

    public function __construct($month, $year, $periode)
    {
        $this->month = $month;
        $this->year = $year;
        $this->periode = $periode;
    }

    public function collection()
    {
        $compensationService = new \App\Modules\Employee\Services\CompensationPeriodService();

        try {
            $dateInfo = $compensationService->calculateCompensationDates($this->year, $this->month, $this->periode);
        } catch (\Exception $e) {
            return collect([]);
        }

        $startDate = $dateInfo['start'];
        $endDate = $dateInfo['end'];

        return EmployeeContract::with(['employee'])
            ->whereBetween('end_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('end_date', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NIK',
            'Nama Karyawan',
            'Departemen',
            'No. Rekening',
            'Nomor Kontrak',
            'Tipe Kontrak',
            'Gaji Pokok',
            'Tunj. Masa Kerja',
            'Tanggal Mulai',
            'Tanggal Berakhir',
            'Durasi (Bulan)',
            'Monthly Rate',
            'Total Kompensasi',
            'Pembulatan',
            'Total Dibulatkan',
            'Status Pembayaran',
            'Tanggal Pembayaran',
        ];
    }

    public function map($contract): array
    {
        $employee = current($contract->employee()->getModels());

        // Derive period untuk lookup salary per bulan
        $period = sprintf('%d-%02d', $this->year, $this->month);

        $gajiPokok = $employee ? $employee->gaji_pokok($period) : 0;
        $tjMasaKerja = $employee ? $employee->tjMasaKerja($period) : 0;
        $durationMonths = (int) ($contract->duration_months ?? 0);
        $monthlyRate = $gajiPokok > 0 ? ($gajiPokok + $tjMasaKerja) / 12 : 0;
        $totalRaw = $durationMonths * $monthlyRate;
        $totalRounded = (float) (ceil($totalRaw / 100) * 100);
        $pembulatan = (int) floor($totalRounded - $totalRaw);

        return [
            $employee ? $employee->employee_code : '-',
            $employee ? $employee->name : '-',
            $employee && $employee->department ? $employee->department->name : '-',
            $employee ? ($employee->bank_account_number ?? '-') : '-',
            $contract->contract_number,
            $this->formatContractType($contract->contract_type),
            $gajiPokok,
            $tjMasaKerja,
            Carbon::parse($contract->start_date)->format('d-m-Y'),
            Carbon::parse($contract->end_date)->format('d-m-Y'),
            $durationMonths,
            round($monthlyRate, 2),
            round($totalRaw, 2),
            $pembulatan,
            $totalRounded,
            $contract->is_compensation_paid ? 'Sudah Dibayar' : 'Belum Dibayar',
            $contract->compensation_paid_at ? Carbon::parse($contract->compensation_paid_at)->format('d-m-Y H:i') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2B3A4C'],
                ],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // Gaji Pokok
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // Tunj. Masa Kerja
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // Monthly Rate
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // Total Kompensasi
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // Pembulatan
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,  // Total Dibulatkan
        ];
    }

    private function formatContractType($type)
    {
        $types = [
            'pkwt'        => 'PKWT',
            'pkwtt'       => 'PKWTT',
            'outsourcing' => 'Outsourcing',
            'freelance'   => 'Freelance',
        ];
        return $types[$type] ?? strtoupper($type);
    }
}
