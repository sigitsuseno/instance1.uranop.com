<?php

namespace App\Modules\Attendance\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Pembaca file absen lama (matrix) — "absen_core".
 *
 * Layout (terverifikasi pada des25_absen_core.xlsx):
 *   baris 1        : tanggal (serial Excel) pada kolom ganjil
 *   baris 2        : nama hari pada kolom ganjil
 *   baris 3..n     : data; kolom 1 = NIP, kolom 2 = NAMA
 *   kolom 3,5,7..  : kode status untuk hari pada baris 1 kolom yang sama
 *   kolom 4,6,8..  : jam lembur untuk hari yang sama (pasangan kolom di kirinya)
 *
 * Kelas ini hanya membaca — tidak menyentuh database.
 */
class LegacyAttendanceMatrixImport implements SkipsOnError, ToCollection, WithCalculatedFormulas, WithMultipleSheets
{
    use Importable;

    /** Hanya sheet pertama yang dibaca; sheet lain bisa berisi layout berbeda. */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /** Kolom (index 0-based) => tanggal Y-m-d. */
    protected array $dates = [];

    /** Kolom jam lembur (index 0-based) => index kolom kode pasangannya. */
    protected array $hoursColumnOf = [];

    /** @var list<array{row:int, nip:string, nama:string, cells:array<int,array{code:?string,hours:?float}>}> */
    protected array $rows = [];

    protected array $errors = [];

    /** Baris data pertama (1-based) mengikuti header 2 baris. */
    public const FIRST_DATA_ROW = 3;

    public function collection(Collection $rows): void
    {
        $header = $rows->get(0);
        if (! $header) {
            $this->errors[] = 'File tidak memiliki baris header.';

            return;
        }

        $header = $header->toArray();
        // Header bisa lebih lebar dari data; batasi pada kolom yang benar-benar bertanggal.
        for ($c = 2; $c < count($header); $c += 2) {
            $date = $this->toDate($header[$c] ?? null);
            if ($date === null) {
                continue;
            }
            $this->dates[$c] = $date;
            $this->hoursColumnOf[$c + 1] = $c;
        }

        if ($this->dates === []) {
            $this->errors[] = 'Tidak ada kolom tanggal yang terbaca pada baris pertama.';

            return;
        }

        foreach ($rows as $index => $row) {
            if ($index < self::FIRST_DATA_ROW - 1) {
                continue;
            }

            $values = $row->toArray();

            $nip = $this->clean($values[0] ?? null);
            $nama = $this->clean($values[1] ?? null);

            // Baris kosong / baris catatan di bawah tabel diabaikan.
            if ($nip === '' && $nama === '') {
                continue;
            }

            $cells = [];
            foreach ($this->dates as $c => $date) {
                $cells[$c] = [
                    'code'  => $this->clean($values[$c] ?? null),
                    'hours' => $this->toHours($values[$c + 1] ?? null),
                ];
            }

            $this->rows[] = [
                'row'   => $index + 1,
                'nip'   => $nip,
                'nama'  => $nama,
                'cells' => $cells,
            ];
        }
    }

    /** @return array<int,string> kolom index => Y-m-d */
    public function dates(): array
    {
        return $this->dates;
    }

    /** @return list<array{row:int, nip:string, nama:string, cells:array<int,array{code:?string,hours:?float}>}> */
    public function rows(): array
    {
        return $this->rows;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Rentang tanggal yang tercakup file, atau null bila kosong.
     *
     * @return array{start:string,end:string}|null
     */
    public function period(): ?array
    {
        if ($this->dates === []) {
            return null;
        }

        return [
            'start' => reset($this->dates),
            'end'   => end($this->dates),
        ];
    }

    public function onError(Throwable $e): void
    {
        $this->errors[] = $e->getMessage();
    }

    /**
     * Sel dibaca apa adanya; sel error Excel (#N/A, #REF!, dll) dianggap kosong
     * supaya bisa dilaporkan sebagai baris bermasalah, bukan menggagalkan import.
     */
    protected function clean(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    protected function toHours(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    protected function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
