<?php

namespace App\Modules\Supervisor\Payroll\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayrollConfig;
use App\Modules\Supervisor\Payroll\Models\SupervisorBreakdown;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Import breakdown gaji supervisor dari berkas Excel "LAPORAN SALARY BREAKDOWN"
 * (format yang dihasilkan tombol Export Excel di halaman /supervisor/payroll).
 *
 * Tata letak berkas:
 *   baris 1 : nama perusahaan (merged)
 *   baris 2 : "LAPORAN SALARY BREAKDOWN — <periode>" (merged)
 *   baris 3 : kosong
 *   baris 4 : header kolom
 *   baris 5+: data, diakhiri baris "GRAND TOTAL"
 *
 * Import bersifat MURNI DATA: nilai diambil apa adanya dari berkas tanpa
 * menghitung ulang kolom turunan (gaji_kotor, pblt, gaji_bersih). Satu-satunya
 * penyesuaian satuan adalah L/M, yang di berkas tersimpan dalam HARI sementara
 * kolom `lm` dan `lm_count` di database dalam satuan turunannya — persis
 * kebalikan dari yang dilakukan export().
 *
 * Pencocokan karyawan memakai kolom "ID No" pada berkas terhadap NIP karyawan.
 */
class SupervisorBreakdownImportService
{
    /** Jumlah baris awal yang dipindai untuk mencari baris header. */
    private const HEADER_SCAN_ROWS = 15;

    /** Batas baris pratinjau yang dikirim ke frontend. */
    private const PREVIEW_ROW_LIMIT = 500;

    /**
     * Kolom berkas (0-indexed) → kolom supervisor_breakdowns.
     *
     * Nilai adalah nama kolom tujuan; L/M ditangani terpisah karena satu kolom
     * berkas mengisi dua kolom database.
     */
    private const COLUMN_MAP = [
        8  => 'gaji_pokok',
        9  => 'premi',
        10 => 'tj_masa_kerja',
        11 => 'hari_kerja',
        13 => 'lembur_count',
        14 => 'upah_lembur',
        15 => 'gaji',
        16 => 'tunjangan',
        17 => 'premi_hadir',
        18 => 'bpjs_tk',
        19 => 'bpjs_ks',
        20 => 'bpjs_pen',
        21 => 'pph',
        22 => 'cashbon',
        23 => 'pblt',
        24 => 'gaji_bersih',
    ];

    private const COL_ID_NO = 1;
    private const COL_LM    = 12;

    /** Pengali L/M (hari) → `lm` (jam) dan → `lm_count`, kebalikan dari export(). */
    private const LM_TO_HOURS = 8;
    private const LM_TO_COUNT = 14;

    /**
     * Susun rencana import tanpa menulis apa pun.
     *
     * @return array{plan: array, summary: array, issues: array}
     */
    public function build(string $absolutePath, PayPeriod $period, ?string $segment): array
    {
        $rows = $this->readRows($absolutePath);
        $headerIndex = $this->findHeaderRow($rows);

        $issues = [];
        $plan = [];
        $unmatched = [];
        $seenNip = [];
        $rowsRead = 0;
        $skippedNonData = 0;

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $lineNo = $i + 1; // nomor baris 1-indexed seperti di Excel

            $nip = $this->cellString($row[self::COL_ID_NO] ?? null);

            // Baris "GRAND TOTAL", baris kosong, dan baris keterangan tidak punya ID No.
            if ($nip === '' || ! preg_match('/^\d+$/', $nip)) {
                $skippedNonData++;
                continue;
            }

            $rowsRead++;

            if (isset($seenNip[$nip])) {
                $issues[] = "ID No {$nip} muncul lebih dari sekali (baris {$lineNo} dan {$seenNip[$nip]}); baris terakhir yang dipakai.";
            }
            $seenNip[$nip] = $lineNo;

            $employee = $this->findEmployee($nip);

            if (! $employee) {
                $unmatched[] = [
                    'row'  => $lineNo,
                    'nip'  => $nip,
                    'nama' => $this->cellString($row[2] ?? null),
                ];
                continue;
            }

            $payload = $this->payloadFromRow($row);

            $exists = $this->forSegment(
                SupervisorBreakdown::withTrashed()
                    ->where('pay_period_id', $period->id)
                    ->where('employee_id', $employee->id),
                $segment
            )->exists();

            $plan[] = [
                'employee_id' => $employee->id,
                'nip'         => $nip,
                'nama'        => $employee->name,
                'row'         => $lineNo,
                'action'      => $exists ? 'update' : 'create',
                'data'        => $payload,
                'employee'    => $employee,
            ];
        }

        if (! $plan) {
            throw new RuntimeException(
                'Tidak ada baris data yang bisa diproses. Pastikan berkas memakai format '
                .'"LAPORAN SALARY BREAKDOWN" dengan header di baris 4 dan data mulai baris 5.'
            );
        }

        if ($unmatched) {
            $issues[] = count($unmatched).' baris dilewati karena NIP-nya tidak ada di data karyawan.';
        }

        $creates = count(array_filter($plan, fn ($p) => $p['action'] === 'create'));
        $updates = count($plan) - $creates;

        return [
            'plan'    => $plan,
            'summary' => [
                'file' => [
                    'rows_read'       => $rowsRead,
                    'skipped_non_data' => $skippedNonData,
                    'header_row'      => $headerIndex + 1,
                ],
                'matched'     => count($plan),
                'will_create' => $creates,
                'will_update' => $updates,
                'unmatched'   => $unmatched,
                'preview'     => array_map(fn ($p) => [
                    'row'         => $p['row'],
                    'nip'         => $p['nip'],
                    'nama'        => $p['nama'],
                    'action'      => $p['action'],
                    'hari_kerja'  => $p['data']['hari_kerja'],
                    'gaji_bersih' => $p['data']['gaji_bersih'],
                ], array_slice($plan, 0, self::PREVIEW_ROW_LIMIT)),
                'preview_truncated' => count($plan) > self::PREVIEW_ROW_LIMIT,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Tuliskan rencana ke supervisor_breakdowns — update record yang sudah ada,
     * buat baru kalau belum ada.
     *
     * @return array{created: int, updated: int}
     */
    public function commit(array $plan, PayPeriod $period, ?string $segment, ?int $userId): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($plan, $period, $segment, $userId, &$created, &$updated) {
            foreach ($plan as $item) {
                /** @var Employee $employee */
                $employee = $item['employee'];

                $breakdown = $this->forSegment(
                    SupervisorBreakdown::withTrashed()
                        ->where('pay_period_id', $period->id)
                        ->where('employee_id', $employee->id),
                    $segment
                )->first();

                if ($breakdown) {
                    if ($breakdown->trashed()) {
                        $breakdown->restore();
                    }

                    $breakdown->update($item['data'] + [
                        'status'     => 'imported',
                        'synced_at'  => now(),
                        'updated_by' => $userId,
                    ]);

                    $updated++;
                    continue;
                }

                SupervisorBreakdown::create($item['data'] + $this->denormalizedEmployee($employee) + [
                    'pay_period_id' => $period->id,
                    'employee_id'   => $employee->id,
                    'segment'       => $segment,
                    'status'        => 'imported',
                    'synced_at'     => now(),
                    'created_by'    => $userId,
                    'updated_by'    => $userId,
                ]);

                $created++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    // ═══════════════════════════════════════════════════════════
    // PEMBACAAN BERKAS
    // ═══════════════════════════════════════════════════════════

    /**
     * Baca sheet pertama sebagai array mentah.
     *
     * $formatData sengaja false: nilai berformat akan kembali sebagai teks
     * ("2,940,088") dan merusak pembacaan angka.
     */
    private function readRows(string $absolutePath): array
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (\Throwable $e) {
            throw new RuntimeException('Berkas tidak bisa dibaca sebagai Excel: '.$e->getMessage());
        }

        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
    }

    /**
     * Cari baris header dengan mengenali sel bertuliskan "ID No".
     *
     * Dicari, bukan dipatok di baris 4, supaya berkas yang judulnya berbeda
     * jumlah barisnya tetap terbaca.
     */
    private function findHeaderRow(array $rows): int
    {
        $limit = min(self::HEADER_SCAN_ROWS, count($rows));

        for ($i = 0; $i < $limit; $i++) {
            foreach ((array) $rows[$i] as $cell) {
                if (strcasecmp($this->cellString($cell), 'ID No') === 0) {
                    return $i;
                }
            }
        }

        throw new RuntimeException(
            'Header "ID No" tidak ditemukan pada '.self::HEADER_SCAN_ROWS.' baris pertama. '
            .'Pastikan berkas memakai format "LAPORAN SALARY BREAKDOWN".'
        );
    }

    /**
     * Ambil nilai satu baris menjadi kolom-kolom supervisor_breakdowns.
     *
     * Tanpa perhitungan: setiap kolom disalin apa adanya. Hanya L/M yang
     * dikonversi satuannya karena berkas menyimpannya dalam hari.
     */
    private function payloadFromRow(array $row): array
    {
        $data = [];

        foreach (self::COLUMN_MAP as $index => $column) {
            $data[$column] = $this->toNumber($row[$index] ?? null);
        }

        $data['hari_kerja'] = (int) $data['hari_kerja'];

        $lmDays = $this->toNumber($row[self::COL_LM] ?? null);
        $data['lm'] = $lmDays * self::LM_TO_HOURS;
        $data['lm_count'] = $lmDays * self::LM_TO_COUNT;

        return $data;
    }

    /**
     * Batasi query ke satu segmen.
     *
     * `where('segment', null)` menghasilkan `segment = NULL` yang tidak pernah
     * cocok di SQL, jadi periode non-split harus memakai whereNull().
     */
    private function forSegment($query, ?string $segment)
    {
        return $segment === null
            ? $query->whereNull('segment')
            : $query->where('segment', $segment);
    }

    private function findEmployee(string $nip): ?Employee
    {
        return Employee::with(['groups', 'department', 'position'])
            ->where('nip', $nip)
            ->orWhere('employee_code', $nip)
            ->first();
    }

    /**
     * Data karyawan untuk record yang baru dibuat — diambil dari tabel
     * employees, bukan dari berkas, supaya kolom denormalisasi tetap terisi.
     */
    private function denormalizedEmployee(Employee $employee): array
    {
        $groupCodes = $employee->groups->pluck('reference_code')->toArray();

        return [
            'section'             => $this->resolveSection($groupCodes),
            'group_codes'         => $groupCodes,
            'employee_code'       => $employee->employee_code ?? $employee->nip,
            'employee_name'       => $employee->name,
            'gender'              => $employee->gender,
            'department_name'     => $employee->department?->name,
            'position_name'       => $employee->position?->name,
            'join_date'           => $employee->join_date,
            'bank_name'           => $employee->bank_name,
            'bank_account_number' => $employee->bank_account_number,
            'bank_account_name'   => $employee->bank_account_name,
        ];
    }

    /** Pemetaan group karyawan → section A/B, sama seperti calculate(). */
    private function resolveSection(array $groupCodes): ?string
    {
        $config = PayrollConfig::getConfig('gaji_karyawan');

        if (array_intersect($groupCodes, $config['sections']['A'] ?? [])) {
            return 'A';
        }

        if (array_intersect($groupCodes, $config['sections']['B'] ?? [])) {
            return 'B';
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // PEMBACAAN NILAI
    // ═══════════════════════════════════════════════════════════

    private function cellString($value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Ubah isi sel menjadi angka.
     *
     * Sel hasil export berupa angka asli, tetapi berkas yang sudah diedit
     * lewat Excel bisa berisi teks berpemisah ribuan — baik gaya Indonesia
     * ("1.234,56") maupun Inggris ("1,234.56").
     */
    private function toNumber($value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $str = trim((string) $value);

        if ($str === '') {
            return 0.0;
        }

        if (is_numeric($str)) {
            return (float) $str;
        }

        $str = str_replace(['Rp', 'rp', ' ', "\u{00A0}"], '', $str);

        $negative = false;
        if (str_starts_with($str, '(') && str_ends_with($str, ')')) {
            $negative = true;
            $str = substr($str, 1, -1);
        }

        if (str_starts_with($str, '-')) {
            $negative = ! $negative;
            $str = substr($str, 1);
        }

        $hasDot = str_contains($str, '.');
        $hasComma = str_contains($str, ',');

        if ($hasDot && $hasComma) {
            // Pemisah yang paling belakang adalah pemisah desimal.
            if (strrpos($str, '.') > strrpos($str, ',')) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', str_replace('.', '', $str));
            }
        } elseif ($hasComma) {
            $str = $this->resolveSingleSeparator($str, ',');
        } elseif ($hasDot) {
            $str = $this->resolveSingleSeparator($str, '.');
        }

        $result = (float) $str;

        return $negative ? -$result : $result;
    }

    /**
     * Satu jenis pemisah saja: desimal kalau hanya sekali dan diikuti 1-2 digit
     * ("1,5" → 1.5), selain itu pemisah ribuan ("2,940,088" → 2940088).
     */
    private function resolveSingleSeparator(string $str, string $separator): string
    {
        $quoted = preg_quote($separator, '/');
        $isDecimal = substr_count($str, $separator) === 1
            && preg_match('/'.$quoted.'\d{1,2}$/', $str) === 1;

        if ($isDecimal) {
            return $separator === ',' ? str_replace(',', '.', $str) : $str;
        }

        return str_replace($separator, '', $str);
    }
}
