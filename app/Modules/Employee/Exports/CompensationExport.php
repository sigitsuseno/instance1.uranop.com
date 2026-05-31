<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\EmployeeContract;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompensationExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
            // If exception, just return empty collection
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
            'Nomor Kontrak',
            'Tipe Kontrak',
            'Gaji Pokok',
            'Tanggal Mulai',
            'Tanggal Berakhir',
            'Durasi (Bulan)',
            'Status Pembayaran',
            'Tanggal Pembayaran'
        ];
    }

    public function map($contract): array
    {
        $employee = current($contract->employee()->getModels());
        $baseSalary = $employee && method_exists($employee, 'baseSalary') ? $employee->baseSalary() : 0;
        
        return [
            $employee ? $employee->employee_code : '-',
            $employee ? $employee->name : '-',
            $employee && $employee->department ? $employee->department->name : '-',
            $contract->contract_number,
            $this->formatContractType($contract->contract_type),
            'Rp ' . number_format($baseSalary, 0, ',', '.'),
            Carbon::parse($contract->start_date)->format('d-m-Y'),
            Carbon::parse($contract->end_date)->format('d-m-Y'),
            $contract->duration_months,
            $contract->is_compensation_paid ? 'Sudah Dibayar' : 'Belum Dibayar',
            $contract->compensation_paid_at ? Carbon::parse($contract->compensation_paid_at)->format('d-m-Y H:i') : '-'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2B3A4C']]],
        ];
    }

    private function formatContractType($type)
    {
        $types = [
            'pkwt' => 'PKWT',
            'pkwtt' => 'PKWTT',
            'outsourcing' => 'Outsourcing',
            'freelance' => 'Freelance',
        ];
        return $types[$type] ?? strtoupper($type);
    }
}
