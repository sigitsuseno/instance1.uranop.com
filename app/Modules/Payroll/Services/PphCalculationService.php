<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Models\EmployeePph;
use App\Modules\Payroll\Models\PayPeriod;
use Illuminate\Support\Facades\Log;

/**
 * PphCalculationService — Menghitung PPh 21 menggunakan metode TER (PP 58/2023).
 *
 * Strategi:
 * 1. Cek dulu apakah sudah ada record di tabel `employee_pph` untuk employee + periode.
 *    Jika ada, gunakan nilai `pph_deducted` dari record tersebut.
 * 2. Jika belum ada, hitung menggunakan TER (Tarif Efektif Rata-Rata)
 *    berdasarkan PTKP status dan penghasilan bruto.
 */
class PphCalculationService
{
    /**
     * Hitung PPh 21 untuk seorang karyawan dalam satu periode penggajian.
     *
     * @param Employee  $employee
     * @param PayPeriod $period
     * @param float     $gajiPokok   Gaji pokok periode ini
     * @param float     $tunjangan   Tunjangan tetap
     * @param float     $upahLembur  Upah lembur
     * @param float     $premiHadir  Premi hadir
     * @param float     $gaji        Gaji proporsional (sudah dipotong absen)
     * @param bool      $isPart1     Apakah ini bagian 1 (seg-A)
     * @return float   Nilai PPh yang harus dipotong (0 untuk Part 1)
     */
    public function calculate(
        Employee $employee,
        PayPeriod $period,
        float $gajiPokok = 0,
        float $tunjangan = 0,
        float $upahLembur = 0,
        float $premiHadir = 0,
        float $gaji = 0,
        bool $isPart1 = false
    ): float {
        // Part 1 (seg-A) tidak ada potongan PPh — 0
        if ($isPart1) {
            return 0;
        }

        // 1. Cek apakah sudah ada record PPh dari pengelolaan PPH
        $existingPph = EmployeePph::where('employee_id', $employee->id)
            ->where('pay_period_id', $period->id)
            ->first();

        if ($existingPph && $existingPph->pph_deducted > 0) {
            return (float) $existingPph->pph_deducted;
        }

        // 2. Jika tidak ada, hitung manual menggunakan TER
        return $this->calculateTerMethod($employee, $period, $gajiPokok, $tunjangan, $upahLembur, $premiHadir, $gaji);
    }

    /**
     * Hitung PPh 21 dengan metode TER (PP 58/2023).
     *
     * Rumus:
     *   PPh 21 per bulan = Penghasilan Bruto × Tarif Efektif Rata-Rata
     *
     * Untuk split payroll (Part 1 / Part 2):
     *   PPh dihitung dari total penghasilan bruto sebulan penuh,
     *   lalu dibagi 2 untuk masing-masing bagian.
     */
    private function calculateTerMethod(
        Employee $employee,
        PayPeriod $period,
        float $gajiPokok,
        float $tunjangan,
        float $upahLembur,
        float $premiHadir,
        float $gaji
    ): float {
        // Penghasilan bruto sebulan
        $grossIncome = $gajiPokok + $tunjangan + $upahLembur + $premiHadir;

        if ($grossIncome <= 0) {
            return 0;
        }

        // PTKP status — default TK/0 jika tidak diisi
        $ptkp = $employee->ptkp ?? 'TK/0';
        $hasNpwp = $employee->has_npwp ?? false;

        // Tentukan kategori TER
        $terCategory = $this->getTerCategory($ptkp);
        $terRate = $this->getTerRate($terCategory, $grossIncome);

        // Hitung PPh bruto
        $pphMonthly = $grossIncome * ($terRate / 100);

        // Jika tidak punya NPWP, kalikan 1.2 (sesuai PPh 20)
        if (!$hasNpwp) {
            $pphMonthly *= 1.2;
        }

        // Untuk split payroll (A/B), PPh dibagi 2
        // Karena periode terbelah, PPh tahunan sudah didesain untuk split
        // Bagian 2 menanggung seluruh PPh (0 untuk bagian 1)
        if ($period->is_split) {
            return round($pphMonthly / 2, 2);
        }

        return round($pphMonthly, 2);
    }

    /**
     * Tentukan kategori TER berdasarkan PTKP status.
     *
     * PP 58/2023 — 3 kategori:
     * - A: TK/0, TK/1, K/0   (single / kawin tanpa tanggungan)
     * - B: TK/2, TK/3, K/1, K/2  (1-2 tanggungan)
     * - C: K/3  (kawin 3 tanggungan)
     */
    private function getTerCategory(?string $ptkp): string
    {
        return match ($ptkp) {
            'TK/0', 'TK/1', 'K/0'  => 'A',
            'TK/2', 'TK/3', 'K/1', 'K/2' => 'B',
            'K/3'                       => 'C',
            default                    => 'A', // default single
        };
    }

    /**
     * Ambil tarif TER berdasarkan kategori dan penghasilan bruto bulanan.
     * Tarif sesuai PP 58/2023 (berlaku sejak Jan 2024).
     */
    private function getTerRate(string $category, float $grossIncome): float
    {
        $rates = $this->getTerRateTable();

        foreach ($rates[$category] as $bracket) {
            if ($grossIncome <= $bracket['max']) {
                return $bracket['rate'];
            }
        }

        // Jika di atas bracket terakhir
        $lastBracket = end($rates[$category]);
        return $lastBracket['rate'];
    }

    /**
     * Tabel tarif TER berdasarkan PP 58/2023.
     */
    private function getTerRateTable(): array
    {
        return [
            'A' => [
                ['max' => 5_400_000,   'rate' => 0],
                ['max' => 5_650_000,   'rate' => 0.25],
                ['max' => 5_950_000,   'rate' => 0.50],
                ['max' => 6_300_000,   'rate' => 0.75],
                ['max' => 6_750_000,   'rate' => 1.00],
                ['max' => 7_300_000,   'rate' => 1.25],
                ['max' => 9_200_000,   'rate' => 1.50],
                ['max' => 10_750_000,  'rate' => 2.00],
                ['max' => 13_950_000,  'rate' => 2.50],
                ['max' => 22_000_000,  'rate' => 3.00],
                ['max' => 38_000_000,  'rate' => 4.00],
                ['max' => 50_000_000,  'rate' => 5.00],
                ['max' => 63_000_000,  'rate' => 6.00],
                ['max' => 75_000_000,  'rate' => 7.00],
                ['max' => 85_000_000,  'rate' => 8.00],
                ['max' => 95_000_000,  'rate' => 9.00],
                ['max' => 100_000_000, 'rate' => 14.00],
                ['max' => INF,          'rate' => 15.00],
            ],
            'B' => [
                ['max' => 5_400_000,   'rate' => 0],
                ['max' => 5_650_000,   'rate' => 0.25],
                ['max' => 5_950_000,   'rate' => 0.50],
                ['max' => 6_300_000,   'rate' => 0.75],
                ['max' => 6_750_000,   'rate' => 1.00],
                ['max' => 7_300_000,   'rate' => 1.25],
                ['max' => 9_200_000,   'rate' => 1.50],
                ['max' => 10_750_000,  'rate' => 2.00],
                ['max' => 13_950_000,  'rate' => 2.50],
                ['max' => 22_000_000,  'rate' => 3.00],
                ['max' => 38_000_000,  'rate' => 4.00],
                ['max' => 50_000_000,  'rate' => 5.00],
                ['max' => 63_000_000,  'rate' => 6.00],
                ['max' => 75_000_000,  'rate' => 7.00],
                ['max' => 85_000_000,  'rate' => 8.00],
                ['max' => 95_000_000,  'rate' => 9.00],
                ['max' => 100_000_000, 'rate' => 14.00],
                ['max' => INF,          'rate' => 15.00],
            ],
            'C' => [
                ['max' => 5_400_000,   'rate' => 0],
                ['max' => 5_650_000,   'rate' => 0],
                ['max' => 5_950_000,   'rate' => 0.25],
                ['max' => 6_300_000,   'rate' => 0.50],
                ['max' => 6_750_000,   'rate' => 0.75],
                ['max' => 7_300_000,   'rate' => 1.00],
                ['max' => 9_200_000,   'rate' => 1.00],
                ['max' => 10_750_000,  'rate' => 1.25],
                ['max' => 13_950_000,  'rate' => 1.75],
                ['max' => 22_000_000,  'rate' => 2.00],
                ['max' => 38_000_000,  'rate' => 3.00],
                ['max' => 50_000_000,  'rate' => 4.00],
                ['max' => 63_000_000,  'rate' => 5.00],
                ['max' => 75_000_000,  'rate' => 7.00],
                ['max' => 85_000_000,  'rate' => 8.00],
                ['max' => 95_000_000,  'rate' => 9.00],
                ['max' => 100_000_000, 'rate' => 14.00],
                ['max' => INF,          'rate' => 15.00],
            ],
        ];
    }
}
