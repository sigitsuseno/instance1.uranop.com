<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\Employee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Employee::with(['department', 'position']);
        
        $filters = $this->filters;

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['position_id'])) {
            $query->where('position_id', $filters['position_id']);
        }

        if (! empty($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        }

        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            $query->whereHas('shiftRosters', function ($q) use ($filters) {
                $q->whereBetween('date', [$filters['period_start'], $filters['period_end']]);
            });
        } elseif (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }
        // Jika tidak ada filter is_active spesifik, kita ambil SEMUA karyawan (Aktif & Non-Aktif)
        // untuk kebutuhan data master.

        $query->orderBy('name', 'asc');

        return $query;
    }

    public function headings(): array
    {
        return [
            'NIP',
            'NIK',
            'Kode Karyawan',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Agama',
            'Gol. Darah',
            'Email',
            'No. HP',
            'Alamat',
            'Kode Pos',
            'Departemen',
            'Jabatan',
            'Status Kepegawaian',
            'Status Aktif',
            'Tanggal Bergabung',
            'Tanggal Berakhir',
            'Tanggal Pengangkatan',
            'Tanggal Resign',
            'Nama Bank',
            'No. Rekening',
            'Nama Rekening',
            'Cabang Bank',
            'NPWP',
            'BPJS Ketenagakerjaan',
            'BPJS Kesehatan',
            'PTKP',
        ];
    }

    public function map($employee): array
    {
        $employmentStatusMap = [
            'permanent'  => 'Tetap',
            'contract'   => 'Kontrak',
            'probation'  => 'Probation',
            'outsource'  => 'Outsource',
            'freelance'  => 'Freelance',
            'resigned'   => 'Resign',
            'terminated' => 'Terminated',
        ];

        return [
            $employee->nip ?? '-',
            $employee->nik ?? '-',
            $employee->employee_code ?? '-',
            $employee->name ?? '-',
            $employee->gender === 'L' ? 'Laki-laki' : ($employee->gender === 'P' ? 'Perempuan' : '-'),
            $employee->place_of_birth ?? '-',
            $employee->date_of_birth ? \Carbon\Carbon::parse($employee->date_of_birth)->format('Y-m-d') : '-',
            $employee->religion ?? '-',
            $employee->blood_type ?? '-',
            $employee->email ?? '-',
            $employee->phone ?? '-',
            $employee->address ?? '-',
            $employee->postal_code ?? '-',
            $employee->department?->name ?? '-',
            $employee->position?->name ?? '-',
            $employmentStatusMap[$employee->employment_status] ?? $employee->employment_status,
            $employee->is_active ? 'Aktif' : 'Non-Aktif',
            $employee->join_date ? \Carbon\Carbon::parse($employee->join_date)->format('Y-m-d') : '-',
            $employee->end_date ? \Carbon\Carbon::parse($employee->end_date)->format('Y-m-d') : '-',
            $employee->permanent_date ? \Carbon\Carbon::parse($employee->permanent_date)->format('Y-m-d') : '-',
            $employee->resign_date ? \Carbon\Carbon::parse($employee->resign_date)->format('Y-m-d') : '-',
            $employee->bank_name ?? '-',
            $employee->bank_account_number ?? '-',
            $employee->bank_account_name ?? '-',
            $employee->bank_cabang ?? '-',
            $employee->npwp ?? '-',
            $employee->bpjs_ketenagakerjaan ?? '-',
            $employee->bpjs_kesehatan ?? '-',
            $employee->ptkp ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style header row (baris 1) menjadi bold dengan background abu-abu
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FF3B82F6', // Warna biru untuk header profesional
                    ]
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ]
                ]
            ],
        ];
    }
}
