<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\Employee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeeExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithCustomValueBinder, WithColumnFormatting
{
    /**
     * Kolom yang berisi nomor identifikasi panjang (NIK 16 digit, NPWP, No. Rekening, BPJS).
     * Harus disimpan sebagai teks agar Excel tidak mengubahnya menjadi angka dan
     * menghilangkan digit terakhir (batas presisi double Excel hanya 15 digit).
     */
    private const TEXT_COLUMNS = ['B', 'X', 'AA', 'AB', 'AC'];

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Paksa kolom identifikasi panjang tetap bertipe string saat ditulis.
     * Tanpa ini, PhpSpreadsheet akan menyimpan NIK/NPWP sebagai angka sehingga
     * Excel membulatkan digit terakhir menjadi nol.
     */
    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), self::TEXT_COLUMNS, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    /**
     * Terapkan format teks ('@') pada kolom identifikasi agar Excel tetap
     * memperlakukannya sebagai teks saat file dibuka.
     */
    public function columnFormats(): array
    {
        return array_fill_keys(self::TEXT_COLUMNS, NumberFormat::FORMAT_TEXT);
    }

    public function query()
    {
        $query = Employee::with(['department', 'position', 'groups.master']);
        
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
            'Cabang',
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

        // Cabang = group penggajian karyawan (GRP-*) yang ditetapkan saat import,
        // mis. GRP-JKT (Jakarta) / GRP-ALLIN. Tampilkan nama master-nya bila ada.
        $cabang = '-';
        foreach ($employee->groups as $group) {
            if (str_starts_with((string) $group->reference_code, 'GRP-')) {
                $cabang = $group->master?->name ?? $group->reference_code;
                break;
            }
        }

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
            $cabang,
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
