<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\EmployeeContract;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Export daftar pekerja kontrak (format tabel laporan).
 *
 * Header bertingkat (multi-row):
 *   Baris 1 : NO | NAMA PEKERJA | NIK | ALAMAT | L/P | BAGIAN JABATAN | UPAH/BULAN |
 *             WAKTU (merge H1:I1) | MASA PKWT
 *             → kolom A..G dan J di-merge vertikal dengan baris 2
 *   Baris 2 : MULAI (H2) | AKHIR (I2) — payung dari WAKTU
 *   Baris 3 : penomoran kolom 1..10
 *   Baris 4+: data kontrak
 */
class EmployeeContractExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithTitle,
    WithColumnWidths,
    WithColumnFormatting,
    WithCustomValueBinder,
    WithEvents
{
    private const LAST_COL = 'J';
    private const COL_COUNT = 10;

    /**
     * NIK disimpan sebagai teks supaya Excel tidak mengubahnya menjadi angka
     * (presisi double Excel hanya 15 digit, sehingga digit terakhir bisa hilang).
     */
    private const TEXT_COLUMNS = ['C'];

    protected array $filters;

    /** Nomor urut baris data (kolom NO). */
    protected int $rowNumber = 0;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'DAFTAR PEKERJA';
    }

    /**
     * Paksa kolom NIK tetap bertipe string saat ditulis.
     */
    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), self::TEXT_COLUMNS, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            // Upah bulanan: 2940088 → tampil 2.940.088,00 pada Excel locale Indonesia.
            'G' => '#,##0.00',
            'H' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'I' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'J' => '0',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 30,
            'C' => 22,
            'D' => 46,
            'E' => 5,
            'F' => 15,
            'G' => 16,
            'H' => 12,
            'I' => 12,
            'J' => 10,
        ];
    }

    public function query()
    {
        $query = EmployeeContract::with(['employee.department', 'employee.position']);

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

        // Filter "Latest": hanya kontrak terbaru tiap karyawan.
        // Jika aktif, range tanggal end_date diabaikan.
        $isLatest = filter_var($filters['is_latest'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isLatest) {
            $query->where('is_latest', true);
        } else {
            // Range tanggal: seleksi kontrak berdasarkan end_date di rentang tersebut.
            if (! empty($filters['end_date_start'])) {
                $query->whereDate('end_date', '>=', $filters['end_date_start']);
            }

            if (! empty($filters['end_date_end'])) {
                $query->whereDate('end_date', '<=', $filters['end_date_end']);
            }
        }

        // Filter "Hanya karyawan aktif"
        if (filter_var($filters['only_active'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('employee', function ($eq) {
                $eq->where('is_active', true);
            });
        }

        return $query->orderBy('employee_id')->orderBy('start_date', 'desc');
    }

    public function headings(): array
    {
        return [
            'NO',
            "NAMA\nPEKERJA",
            'NIK',
            'ALAMAT',
            'L/P',
            "BAGIAN\nJABATAN",
            "UPAH/\nBULAN",
            'WAKTU',
            null,
            "MASA\nPKWT",
        ];
    }

    public function map($contract): array
    {
        $employee = $contract->employee;

        return [
            ++$this->rowNumber,
            $employee?->name ?? '-',
            $employee?->nik ?? '-',
            $employee?->address ?? '-',
            $employee?->gender ?? '-',
            // Bagian/jabatan: ambil nama jabatan (mis. FINISHING, SUBLIM, AUTOPRINT).
            $employee?->position?->name ?? $employee?->department?->name ?? '-',
            // Upah per bulan (gaji pokok aktif; fallback kolom employees.base_salary)
            $employee ? (float) $employee->gaji_pokok() : 0,
            $contract->start_date,
            $contract->end_date,
            (int) ($contract->duration_months ?? 0),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Sisipkan baris 2 (sub-judul WAKTU) dan baris 3 (penomoran kolom).
                $sheet->insertNewRowBefore(2, 2);
                $sheet->fromArray([null, null, null, null, null, null, null, 'MULAI', 'AKHIR', null], null, 'A2');
                $sheet->fromArray([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], null, 'A3');

                // Tanpa data, tabel hanya berisi 3 baris judul.
                $lastRow   = max($sheet->getHighestRow(), 3);
                $firstData = 4;

                // Judul kolom di-merge vertikal dengan baris 2,
                // sedangkan WAKTU menaungi MULAI & AKHIR secara horizontal.
                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'J'] as $column) {
                    $sheet->mergeCells("{$column}1:{$column}2");
                }

                $sheet->mergeCells('H1:I1');

                $sheet->getStyle('A1:' . self::LAST_COL . '3')->applyFromArray([
                    'font' => [
                        'size' => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(20);
                $sheet->getRowDimension(2)->setRowHeight(16);
                $sheet->getRowDimension(3)->setRowHeight(16);

                // Border tipis untuk seluruh tabel.
                $sheet->getStyle('A1:' . self::LAST_COL . $lastRow)->applyFromArray([
                    'font' => [
                        'size' => 10,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                if ($lastRow >= $firstData) {
                    $sheet->getStyle("A{$firstData}:" . self::LAST_COL . "{$lastRow}")
                        ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    $alignments = [
                        'A' => Alignment::HORIZONTAL_CENTER,
                        'B' => Alignment::HORIZONTAL_LEFT,
                        'C' => Alignment::HORIZONTAL_LEFT,
                        'D' => Alignment::HORIZONTAL_LEFT,
                        'E' => Alignment::HORIZONTAL_CENTER,
                        'F' => Alignment::HORIZONTAL_CENTER,
                        'G' => Alignment::HORIZONTAL_RIGHT,
                        'H' => Alignment::HORIZONTAL_CENTER,
                        'I' => Alignment::HORIZONTAL_CENTER,
                        'J' => Alignment::HORIZONTAL_CENTER,
                    ];

                    foreach ($alignments as $column => $horizontal) {
                        $sheet->getStyle("{$column}{$firstData}:{$column}{$lastRow}")
                            ->getAlignment()->setHorizontal($horizontal);
                    }

                    // Alamat panjang dibiarkan wrap agar tinggi baris menyesuaikan.
                    $sheet->getStyle("D{$firstData}:D{$lastRow}")->getAlignment()->setWrapText(true);
                }

                // Siap cetak: landscape folio, judul kolom berulang di tiap halaman.
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_FOLIO)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);

                // Judul kolom (baris 1-3) diulang di setiap halaman saat dicetak.
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 3);
                $sheet->freezePane('A4');
            },
        ];
    }
}
