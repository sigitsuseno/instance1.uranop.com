<?php

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Services\TimeParser;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Helper parsing sel untuk file scanlog mesin fingerprint.
 *
 * Dua format yang didukung:
 *
 *   RAW — 1 baris per scan (export mentah mesin):
 *     0=Tanggal scan(datetime) 1=Tanggal 2=Jam 3=PIN 4=NIP 5=Nama 6=Jabatan
 *     7=Departemen 8=Kantor 9=Verifikasi 10=I/O 11=Workcode 12=SN 13=Mesin
 *
 *   PIVOT — 1 baris per karyawan-hari:
 *     0=PIN 1=NIP 2=Nama 3=Jabatan 4=Departemen 5=Kantor 6=Tanggal 7..10=Scan 1..4
 *
 * Catatan: kolom I/O pada export nyata selalu berisi 0 (bahkan untuk scan pulang),
 * jadi in/out tidak boleh ditentukan dari kolom itu — pakai urutan jam.
 */
class ScanlogCellParser
{
    public const FORMAT_RAW = 'raw';

    public const FORMAT_PIVOT = 'pivot';

    // ── Index kolom format RAW ──
    public const RAW_SCAN_DATETIME = 0;

    public const RAW_DATE = 1;

    public const RAW_TIME = 2;

    public const RAW_PIN = 3;

    public const RAW_NAME = 5;

    public const RAW_IO = 10;

    // ── Index kolom format PIVOT ──
    public const PIVOT_PIN = 0;

    public const PIVOT_NAME = 2;

    public const PIVOT_DATE = 6;

    /** @var array<int, int> Scan 1-4 */
    public const PIVOT_SCAN_COLUMNS = [7, 8, 9, 10];

    /**
     * Tebak format dari baris header. Null kalau tidak dikenali.
     */
    public static function detectFormat(array $headerRow): ?string
    {
        $values = array_map(
            fn ($v) => strtoupper(trim((string) $v)),
            array_values($headerRow)
        );
        $joined = implode('|', $values);

        // RAW: "Tanggal scan" + ("I/O" atau "SN")
        if (str_contains($joined, 'TANGGAL SCAN')
            && (str_contains($joined, 'I/O') || str_contains($joined, 'SN'))) {
            return self::FORMAT_RAW;
        }

        // PIVOT: ada kolom "Scan n"
        if (in_array('PIN', $values, true) || in_array('NIP', $values, true)) {
            foreach ($values as $value) {
                if (preg_match('/^SCAN\s*\d+$/', $value)) {
                    return self::FORMAT_PIVOT;
                }
            }
        }

        return null;
    }

    /**
     * Baris pertama data (0-index) untuk format tertentu.
     * Baris header dilewati; kalau header tidak ketemu, dicari baris yang
     * bentuknya sudah seperti data.
     */
    public static function findDataStartRow(array $rows, string $format): int
    {
        foreach ($rows as $index => $row) {
            if (! self::hasAnyValue($row)) {
                continue;
            }

            if ($format === self::FORMAT_RAW) {
                $first = trim((string) ($row[self::RAW_SCAN_DATETIME] ?? ''));
                $io = trim((string) ($row[self::RAW_IO] ?? ''));

                if (stripos($first, 'tanggal scan') !== false) {
                    return $index + 1;
                }
                if (stripos($io, 'i/o') !== false) {
                    return $index + 1;
                }
            } else {
                $first = strtoupper(trim((string) ($row[self::PIVOT_PIN] ?? '')));
                $second = strtoupper(trim((string) ($row[1] ?? '')));

                if (in_array($first, ['PIN', 'NIP'], true) || in_array($second, ['PIN', 'NIP'], true)) {
                    return $index + 1;
                }
            }
        }

        foreach ($rows as $index => $row) {
            if ($format === self::FORMAT_RAW) {
                $scanDatetime = trim((string) ($row[self::RAW_SCAN_DATETIME] ?? ''));
                if (preg_match('/\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2}/', $scanDatetime)) {
                    return $index;
                }
            } else {
                $code = trim((string) ($row[self::PIVOT_PIN] ?? ''));
                $date = trim((string) ($row[self::PIVOT_DATE] ?? ''));
                if ($code !== '' && preg_match('/\d{2}-\d{2}-\d{4}/', $date)) {
                    return $index;
                }
            }
        }

        // Default: asumsi data mulai baris ke-3
        return 2;
    }

    /**
     * Ambil nilai sel, dibersihkan. Nilai numerik dibiarkan numerik supaya
     * jam/tanggal serial Excel tetap bisa diparse.
     */
    public static function cellValue(array $row, int $index, string $dateTimeFormat = 'Y-m-d H:i:s'): mixed
    {
        if (! isset($row[$index])) {
            return null;
        }

        $value = $row[$index];

        // Sel tanggal/jam asli dari Excel
        if ($value instanceof \DateTimeInterface) {
            return $value->format($dateTimeFormat);
        }

        if (is_numeric($value)) {
            return $value;
        }

        $cleaned = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', (string) $value);

        return trim($cleaned);
    }

    /**
     * Sel jam yang kosong. Excel bisa membacanya sebagai 0, dan TimeParser
     * menerjemahkan numerik 0 jadi 00:00:00 — jadi guard ini wajib dipakai
     * SEBELUM memanggil TimeParser.
     */
    public static function isEmptyScan(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_numeric($value) && $value == 0) {
            return true;
        }

        $stringValue = trim((string) $value);

        $emptyPatterns = ['', '0', '00:00', '00:00:00', '0:00:00', '0:00', 'null', 'NULL'];
        if (in_array($stringValue, $emptyPatterns, true)) {
            return true;
        }
        if (preg_match('/^0+$/', $stringValue)) {
            return true;
        }
        if (preg_match('/^(0{1,2}):(0{2}):(0{2})$/', $stringValue)) {
            return true;
        }

        return false;
    }

    /**
     * Parse tanggal (tanpa jam) ke Carbon. Mendukung serial numerik Excel.
     */
    public static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dateString = trim((string) $value);

        if (is_numeric($dateString)) {
            try {
                if (class_exists(Date::class)) {
                    return Carbon::instance(Date::excelToDateTimeObject((int) $dateString));
                }
            } catch (Throwable) {
                // lanjut ke format teks
            }
        }

        $formats = ['d-m-Y', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Y/m/d', 'd.m.Y', 'Y.m.d'];

        foreach ($formats as $format) {
            try {
                $result = Carbon::createFromFormat($format, $dateString);
                if ($result && $result->format('Y') !== '1970') {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        try {
            $result = Carbon::parse($dateString);
            if ($result && $result->format('Y') !== '1970') {
                return $result;
            }
        } catch (Throwable) {
            // menyerah
        }

        return null;
    }

    /**
     * Parse tanggal+jam (format RAW, kolom "Tanggal scan": DD-MM-YYYY HH:MM:SS).
     */
    public static function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = trim((string) $value);

        // Serial numerik Excel
        if (is_numeric($string) && $string > 1) {
            try {
                if (class_exists(Date::class)) {
                    return Carbon::instance(Date::excelToDateTimeObject((float) $string));
                }
            } catch (Throwable) {
                // lanjut ke format teks
            }
        }

        $formats = [
            'd-m-Y H:i:s',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'd-m-Y H:i',
            'd/m/Y H:i',
            'Y-m-d H:i',
        ];

        foreach ($formats as $format) {
            try {
                $result = Carbon::createFromFormat($format, $string);
                if ($result && $result->year > 2000) {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        try {
            $result = Carbon::parse($string);
            if ($result && $result->year > 2000) {
                return $result;
            }
        } catch (Throwable) {
            // menyerah
        }

        return null;
    }

    /**
     * Gabungkan tanggal + jam terpisah (format RAW, fallback kalau kolom
     * "Tanggal scan" tidak terbaca).
     */
    public static function parseDateAndTime(mixed $dateValue, mixed $timeValue): ?Carbon
    {
        $date = self::parseDate($dateValue);
        if (! $date) {
            return null;
        }

        if (self::isEmptyScan($timeValue)) {
            return $date->copy()->startOfDay();
        }

        return TimeParser::parse($timeValue, $date);
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    protected static function hasAnyValue(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
