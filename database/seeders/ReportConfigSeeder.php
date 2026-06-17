<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Settings\Models\ReportConfig;

class ReportConfigSeeder extends Seeder
{
    public function run(): void
    {
        ReportConfig::updateOrCreate(
            ['report_type' => 'lembur_uang_makan'],
            [
                'employee_groups' => ['GRP-JKT', 'GRP-PS1', 'GRP-GD', 'GRP-SS', 'GRP-SPR', 'GRP-ALLIN', 'GRP-SPC'],
                'config' => [
                    'KABAG'   => [
                        'weekday'      => 15000,
                        'sabtu_dua'    => 55000,
                        'sabtu_full'   => 110000,
                        'minggu_half'  => 110000,
                        'minggu_full'  => 220000,
                    ],
                    'KASHIFT' => [
                        'weekday'      => 15000,
                        'sabtu_dua'    => 52522,
                        'sabtu_full'   => 105000,
                        'minggu_half'  => 105000,
                        'minggu_full'  => 210000,
                    ],
                    'ALL IN'  => [
                        'weekday'      => 15000,
                        'sabtu_dua'    => 50000,
                        'sabtu_full'   => 100000,
                        'minggu_half'  => 100000,
                        'minggu_full'  => 200000,
                    ],
                ],
            ]
        );

        ReportConfig::updateOrCreate(
            ['report_type' => 'absensi'],
            [
                'employee_groups' => ['GRP-JKT', 'GRP-ALLIN', 'GRP-GD', 'GRP-SPR', 'GRP-PS1', 'GRP-SS'],
                'config'          => [],
            ]
        );

        ReportConfig::updateOrCreate(
            ['report_type' => 'bpjs'],
            [
                'employee_groups' => ['BPJS-PROD', 'BPJS-2', 'BPJS-1'],
                'config'          => [],
            ]
        );

        ReportConfig::updateOrCreate(
            ['report_type' => 'pph'],
            [
                // PPh berlaku untuk semua group karyawan
                'employee_groups' => ['GRP-JKT', 'GRP-ALLIN', 'GRP-GD', 'GRP-SPR', 'GRP-PS1', 'GRP-SS'],
                'config'          => [
                    'note' => 'Tarif PPh dikelola di menu Payroll → Pengelolaan PPh 21',
                ],
            ]
        );

        // Clear cache biar ReportConfigService ambil data fresh
        \Illuminate\Support\Facades\Cache::forget('report_config:lembur_uang_makan');
        \Illuminate\Support\Facades\Cache::forget('report_config:absensi');
        \Illuminate\Support\Facades\Cache::forget('report_config:bpjs');
        \Illuminate\Support\Facades\Cache::forget('report_config:pph');
    }
}
