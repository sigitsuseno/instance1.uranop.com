<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Membersihkan kolom lembur & lembur_calc pada hari Minggu / holiday di
 * tabel attendance_autologs, untuk setiap periode (bulan).
 *
 * Aturan bisnis:
 *   Lembur hari Minggu (is_sun=1) atau hari libur (is_holiday=1) seharusnya
 *   tersimpan di kolom lm / lm_calc, BUKAN di lembur / lembur_calc.
 *   Jadi untuk hari tsb kolom lembur & lembur_calc harus dikosongkan.
 *
 * Jalankan:
 *   php artisan db:seed --class=ClearHolidayOvertimeSeeder
 */
class ClearHolidayOvertimeSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil daftar periode (YYYY-MM) yang punya data di tabel.
        $periods = DB::table('attendance_autologs')
            ->select(DB::raw("DATE_FORMAT(date, '%Y-%m') AS period"))
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        $totalUpdated = 0;
        $totalPeriods = $periods->count();

        $this->command?->warn("Ditemukan {$totalPeriods} periode yang akan diproses...");

        foreach ($periods as $period) {
            [$year, $month] = explode('-', $period);
            $startDate = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
            $endDate   = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

            $updated = DB::table('attendance_autologs')
                ->whereBetween('date', [$startDate, $endDate])
                // Hanya hari Minggu / holiday
                ->where(function ($q) {
                    $q->where('is_sun', 1)->orWhere('is_holiday', 1);
                })
                // Hanya baris yang memang masih terisi lembur / lembur_calc
                ->where(function ($q) {
                    $q->where('lembur', '>', 0)->orWhere('lembur_calc', '>', 0);
                })
                ->update([
                    'lembur'      => 0,          // non-nullable, default 0
                    'lembur_calc' => null,        // nullable, kosongkan isinya
                    'updated_at'  => now(),
                ]);

            $totalUpdated += $updated;
            $this->command?->info("  Periode {$period} ({$startDate} s/d {$endDate}): {$updated} baris dibersihkan");
        }

        $this->command?->info("Selesai. Total {$totalPeriods} periode, {$totalUpdated} baris lembur hari Minggu/holiday telah dibersihkan.");
    }
}