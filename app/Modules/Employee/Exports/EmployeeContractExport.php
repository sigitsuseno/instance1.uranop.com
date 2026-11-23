<?php

namespace App\Modules\Employee\Exports;

use App\Modules\Employee\Models\EmployeeContract;
use DateTimeInterface;
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
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Export daftar kontrak kerja.
 *
 * Layout ringkas satu baris judul:
 *   Baris 1 : NIP | NAMA | TIPE KONTRAK | START DATE | END DATE | DURASI
 *   Baris 2+: data kontrak
 *
 * Dulu berkas ini berupa tabel laporan 10 kolom dengan header bertingkat
 * (NO/NAMA/NIK/ALAMAT/L-P/BAGIAN JABATAN/UPAH/WAKTU/MASA PKWT). Sejak
 * 9 Nov 2026 kolomnya disamakan dengan export di kusmajalengka2.
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
    private const LAST_COL = 'F';

    /**
     * NIP ditulis sebagai teks supaya Excel tidak mengubahnya menjadi angka
     * (nol di depan hilang dan presisi double hanya 15 digit).
     */
    private const TEXT_COLUMNS = ['A'];

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'DAFTAR PEKERJA';
    }

    /**
     * Paksa kolom NIP tetap bertipe string saat ditulis.
     *
     * Tanggal (start_date/end_date) ditulis sebagai serial number Excel, bukan
     * string "Y-m-d H:i:s" seperti perilaku DefaultValueBinder. Dengan begitu
     * format kolom D/E (DD/MM/YYYY) benar-benar dipakai Excel sehingga tanggal
     * tampil tanpa jam.
     */
    public function bindValue(Cell $cell, $value)
    {
        if (in_array($cell->getColumn(), self::TEXT_COLUMNS, true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        if ($value instanceof DateTimeInterface) {
            $cell->setValueExplicit(ExcelDate::PHPToExcel($value), DataType::TYPE_NUMERIC);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'E' => NumberFormat::FORMAT_DATE_DDMMYYYY,
            'F' => '0',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 30,
            'C' => 18,
            'D' => 14,
            'E' => 14,
            'F' => 12,
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
            'NIP',
            'NAMA',
            'TIPE KONTRAK',
            'START DATE',
            'END DATE',
            'DURASI',
        ];
    }

    public function map($contract): array
    {
        $employee = $contract->employee;

        return [
            $employee?->nip ?? '-',
            $employee?->name ?? '-',
            $contract->contract_type_label ?? '-',
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

                $lastRow = max($sheet->getHighestRow(), 1);

                $sheet->getStyle('A1:' . self::LAST_COL . '1')->applyFromArray([
                    'font' => [
                        'size'     => 10,
                        'bold'     => true,
                        'color'    => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(20);

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

                if ($lastRow >= 2) {
                    $sheet->getStyle('A2:' . self::LAST_COL . $lastRow)
                        ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

                    $alignments = [
                        'A' => Alignment::HORIZONTAL_LEFT,
                        'B' => Alignment::HORIZONTAL_LEFT,
                        'C' => Alignment::HORIZONTAL_CENTER,
                        'D' => Alignment::HORIZONTAL_CENTER,
                        'E' => Alignment::HORIZONTAL_CENTER,
                        'F' => Alignment::HORIZONTAL_CENTER,
                    ];

                    foreach ($alignments as $column => $horizontal) {
                        $sheet->getStyle("{$column}2:{$column}{$lastRow}")
                            ->getAlignment()->setHorizontal($horizontal);
                    }
                }

                // Siap cetak: landscape folio, judul kolom berulang di tiap halaman.
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_FOLIO)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);

                // Judul kolom (baris 1) diulang di setiap halaman saat dicetak.
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
                $sheet->freezePane('A2');
            },
        ];
    }
}
