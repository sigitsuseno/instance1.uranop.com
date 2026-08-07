<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\EmployeeContract;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeContractExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = EmployeeContract::with(['employee']);

        $filters = $this->filters;

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('employee_code', 'like', "%{$search}%");
                  });
            });
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (! empty($filters['contract_type'])) {
            $query->where('contract_type', $filters['contract_type']);
        }

        if (! empty($filters['status'])) {
            $status = $filters['status'];

            // Sama dengan logika filter di halaman kontrak: status Aktif / Segera Berakhir / Expired
            // dihitung dari end_date, sedangkan Terminated / Draft dari kolom status.
            $today       = now()->startOfDay();
            $activeStart = now()->addDays(15)->startOfDay();

            match ($status) {
                'active' => $query->where(function ($q) use ($activeStart) {
                    $q->whereNull('end_date')
                        ->orWhereDate('end_date', '>=', $activeStart);
                }),
                'expiring_soon' => $query->whereNotNull('end_date')
                    ->whereDate('end_date', '>=', $today)
                    ->whereDate('end_date', '<', $activeStart),
                'expired' => $query->whereNotNull('end_date')
                    ->whereDate('end_date', '<', $today),
                'terminated' => $query->whereIn('status', ['terminated', 'resign', 'phk', 'mangkir']),
                default => $query->where('status', $status),
            };
        }

        return $query->orderBy('employee_id')->orderBy('start_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'NIP',
            'Nama Karyawan',
            'No. Kontrak',
            'Tipe Kontrak',
            'Tgl Mulai',
            'Tgl Akhir',
            'Durasi (Bulan)',
            'Status',
            'Version',
            'Latest',
            'Kompensasi Dibayar',
        ];
    }

    public function map($contract): array
    {
        $typeLabel = match ($contract->contract_type) {
            'pkwt'        => 'PKWT',
            'pkwtt'       => 'PKWTT',
            'outsourcing' => 'Outsourcing',
            'freelance'   => 'Freelance',
            default       => $contract->contract_type,
        };

        $statusLabel = match ($contract->status) {
            'draft'      => 'Draft',
            'active'     => 'Aktif',
            'expired'    => 'Kadaluarsa',
            'terminated' => 'Dihentikan',
            default      => $contract->status ?? '-',
        };

        return [
            $contract->employee->nip ?? '-',
            $contract->employee->name ?? '-',
            $contract->contract_number ?? '-',
            $typeLabel,
            $contract->start_date ? \Carbon\Carbon::parse($contract->start_date)->format('Y-m-d') : '-',
            $contract->end_date ? \Carbon\Carbon::parse($contract->end_date)->format('Y-m-d') : '-',
            $contract->duration_months ?? 0,
            $statusLabel,
            $contract->version ?? 1,
            $contract->is_latest ? 'Ya' : 'Tidak',
            $contract->compensation_paid_at ? \Carbon\Carbon::parse($contract->compensation_paid_at)->format('Y-m-d') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF3B82F6',
                    ],
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }
}
