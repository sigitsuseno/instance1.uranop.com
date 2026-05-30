<?php

namespace Database\Seeders;

use App\Modules\Payroll\Models\PayPeriod;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PayPeriodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = 2026; // Tahun default

        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        foreach ($months as $monthNumber => $monthName) {
            $startDate = Carbon::create($year, $monthNumber, 1);
            $endDate = $startDate->copy()->endOfMonth();

            PayPeriod::updateOrCreate(
                [
                    'period_year' => $year,
                    'period_month' => $monthNumber,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => "{$monthName} {$year}",
                    'is_split' => false,
                    'system_setting_id' => null,
                    'status' => 'draft',
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ]
            );
        }
    }
}
