<?php

namespace Database\Seeders;

use App\Modules\Settings\Models\BpjsConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BpjsConfigSeeder extends Seeder
{
    /**
     * Seed default BPJS config dengan rate standar pemerintah.
     * Menggunakan firstOrCreate agar aman dijalankan ulang.
     */
    public function run(): void
    {
        BpjsConfig::firstOrCreate(
            ['effective_date' => '2024-01-01'],
            [
                'uuid'                => (string) Str::uuid(),
                'is_active'           => true,
                // JHT
                'jht_employer'        => 3.70,
                'jht_employee'        => 2.00,
                // JKK (employer only)
                'jkk'                 => 0.24,
                // JKM (employer only)
                'jkm'                 => 0.30,
                // JP
                'jp_employer'         => 2.00,
                'jp_employee'         => 1.00,
                // Kesehatan
                'kesehatan_employer'  => 4.00,
                'kesehatan_employee'  => 1.00,
                // Batas maks upah
                'max_wage_cap'        => 12_000_000,
                'description'         => 'Default BPJS config — rate standar pemerintah',
                'created_by'          => 1,
            ]
        );
    }
}
