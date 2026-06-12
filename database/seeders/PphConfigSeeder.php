<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Settings\Models\PphConfig;
use App\Modules\Settings\Models\PtkpRate;
use App\Modules\Settings\Models\TerRate;
use App\Modules\Settings\Models\ProgressiveRate;

class PphConfigSeeder extends Seeder
{
    /**
     * Seed TER rates, PTKP rates, and Progressive rates
     * based on PP 58/2023 (Tarif Efektif Rata-rata PPh 21) & UU HPP.
     */
    public function run(): void
    {
        $config = PphConfig::where('is_active', true)->first();

        if (! $config) {
            $config = PphConfig::create([
                'uuid' => (string) Str::uuid(),
                'effective_date' => '2024-01-01',
                'is_active' => true,
                'calculation_method' => 'ter',
                'pph_method' => 'gross',
                'non_npwp_penalty' => true,
                'non_npwp_multiplier' => 1.20,
                'description' => 'Konfigurasi PPh 21 Standar Indonesia (PP 58/2023 TER & UU HPP)',
                'created_by' => 1,
            ]);
        }

        $configId = $config->id;
        $userId = 1;

        // ════════════════════════════════════════════════════
        // 1. PTKP Rates
        // ════════════════════════════════════════════════════
        PtkpRate::where('pph_config_id', $configId)->delete();

        $ptkpData = [
            ['status_code' => 'TK/0', 'status_name' => 'Tidak Kawin 0 Tanggungan', 'value' => 54000000, 'sort_order' => 1],
            ['status_code' => 'TK/1', 'status_name' => 'Tidak Kawin 1 Tanggungan', 'value' => 58500000, 'sort_order' => 2],
            ['status_code' => 'K/0',  'status_name' => 'Kawin 0 Tanggungan',       'value' => 58500000, 'sort_order' => 3],
            ['status_code' => 'TK/2', 'status_name' => 'Tidak Kawin 2 Tanggungan', 'value' => 63000000, 'sort_order' => 4],
            ['status_code' => 'K/1',  'status_name' => 'Kawin 1 Tanggungan',       'value' => 63000000, 'sort_order' => 5],
            ['status_code' => 'TK/3', 'status_name' => 'Tidak Kawin 3 Tanggungan', 'value' => 67500000, 'sort_order' => 6],
            ['status_code' => 'K/2',  'status_name' => 'Kawin 2 Tanggungan',       'value' => 67500000, 'sort_order' => 7],
            ['status_code' => 'K/3',  'status_name' => 'Kawin 3 Tanggungan',       'value' => 72000000, 'sort_order' => 8],
        ];

        foreach ($ptkpData as $ptkp) {
            PtkpRate::create([
                'pph_config_id' => $configId,
                'status_code' => $ptkp['status_code'],
                'status_name' => $ptkp['status_name'],
                'value' => $ptkp['value'],
                'sort_order' => $ptkp['sort_order'],
                'is_active' => true,
                'created_by' => $userId,
            ]);
        }

        $this->command->info("✅ Seeded {$configId}: ".count($ptkpData).' PTKP rates');

        // ════════════════════════════════════════════════════
        // 2. TER Rates
        // ════════════════════════════════════════════════════
        TerRate::where('pph_config_id', $configId)->delete();

        // TER A — TK/0, TK/1, K/0
        $terA = [
            [0, 5400000, 0.00],
            [5400001, 5650000, 0.25],
            [5650001, 5950000, 0.50],
            [5950001, 6300000, 0.75],
            [6300001, 6750000, 1.00],
            [6750001, 7500000, 1.25],
            [7500001, 8550000, 1.50],
            [8550001, 9650000, 1.75],
            [9650001, 10050000, 2.00],
            [10050001, 10350000, 2.25],
            [10350001, 10700000, 2.50],
            [10700001, 11050000, 3.00],
            [11050001, 11600000, 3.50],
            [11600001, 12500000, 4.00],
            [12500001, 13750000, 5.00],
            [13750001, 15100000, 6.00],
            [15100001, 16950000, 7.00],
            [16950001, 19750000, 8.00],
            [19750001, 24150000, 9.00],
            [24150001, 26450000, 10.00],
            [26450001, 28000000, 11.00],
            [28000001, 30050000, 12.00],
            [30050001, 32400000, 13.00],
            [32400001, 35400000, 14.00],
            [35400001, 39100000, 15.00],
            [39100001, 43850000, 16.00],
            [43850001, 47800000, 17.00],
            [47800001, 51400000, 18.00],
            [51400001, 56300000, 19.00],
            [56300001, 62200000, 20.00],
            [62200001, 68600000, 21.00],
            [68600001, 77500000, 22.00],
            [77500001, 89000000, 23.00],
            [89000001, 103000000, 24.00],
            [103000001, 125000000, 25.00],
            [125000001, 157000000, 26.00],
            [157000001, 206000000, 27.00],
            [206000001, 337000000, 28.00],
            [337000001, 454000000, 29.00],
            [454000001, 550000000, 30.00],
            [550000001, 695000000, 31.00],
            [695000001, 910000000, 32.00],
            [910000001, 1400000000, 33.00],
            [1400000001, null, 34.00],
        ];

        // TER B — TK/2, TK/3, K/1, K/2
        $terB = [
            [0, 6200000, 0.00],
            [6200001, 6500000, 0.25],
            [6500001, 6850000, 0.50],
            [6850001, 7300000, 0.75],
            [7300001, 9200000, 1.00],
            [9200001, 10750000, 1.50],
            [10750001, 11250000, 2.00],
            [11250001, 11600000, 2.50],
            [11600001, 12600000, 3.00],
            [12600001, 13600000, 4.00],
            [13600001, 14950000, 5.00],
            [14950001, 16400000, 6.00],
            [16400001, 18450000, 7.00],
            [18450001, 21850000, 8.00],
            [21850001, 26000000, 9.00],
            [26000001, 27700000, 10.00],
            [27700001, 29350000, 11.00],
            [29350001, 31450000, 12.00],
            [31450001, 33950000, 13.00],
            [33950001, 37100000, 14.00],
            [37100001, 41100000, 15.00],
            [41100001, 45800000, 16.00],
            [45800001, 49500000, 17.00],
            [49500001, 53800000, 18.00],
            [53800001, 58500000, 19.00],
            [58500001, 64000000, 20.00],
            [64000001, 71000000, 21.00],
            [71000001, 80000000, 22.00],
            [80000001, 93000000, 23.00],
            [93000001, 109000000, 24.00],
            [109000001, 129000000, 25.00],
            [129000001, 163000000, 26.00],
            [163000001, 211000000, 27.00],
            [211000001, 374000000, 28.00],
            [374000001, 459000000, 29.00],
            [459000001, 555000000, 30.00],
            [555000001, 704000000, 31.00],
            [704000001, 957000000, 32.00],
            [957000001, 1405000000, 33.00],
            [1405000001, null, 34.00],
        ];

        // TER C — K/3
        $terC = [
            [0, 6600000, 0.00],
            [6600001, 6950000, 0.25],
            [6950001, 7350000, 0.50],
            [7350001, 7800000, 0.75],
            [7800001, 8850000, 1.00],
            [8850001, 9800000, 1.25],
            [9800001, 10950000, 1.50],
            [10950001, 11200000, 1.75],
            [11200001, 12050000, 2.00],
            [12050001, 12950000, 3.00],
            [12950001, 14150000, 4.00],
            [14150001, 15550000, 5.00],
            [15550001, 17050000, 6.00],
            [17050001, 19500000, 7.00],
            [19500001, 22700000, 8.00],
            [22700001, 26600000, 9.00],
            [26600001, 28100000, 10.00],
            [28100001, 30100000, 11.00],
            [30100001, 32600000, 12.00],
            [32600001, 35400000, 13.00],
            [35400001, 38900000, 14.00],
            [38900001, 43000000, 15.00],
            [43000001, 47400000, 16.00],
            [47400001, 51200000, 17.00],
            [51200001, 55800000, 18.00],
            [55800001, 60400000, 19.00],
            [60400001, 66700000, 20.00],
            [66700001, 74500000, 21.00],
            [74500001, 83200000, 22.00],
            [83200001, 95600000, 23.00],
            [95600001, 110000000, 24.00],
            [110000001, 134000000, 25.00],
            [134000001, 169000000, 26.00],
            [169000001, 221000000, 27.00],
            [221000001, 390000000, 28.00],
            [390000001, 463000000, 29.00],
            [463000001, 561000000, 30.00],
            [561000001, 709000000, 31.00],
            [709000001, 965000000, 32.00],
            [965000001, 1419000000, 33.00],
            [1419000001, null, 34.00],
        ];

        $insertTer = function (array $rates, string $category, int $configId, int $userId) {
            foreach ($rates as $i => $r) {
                TerRate::create([
                    'pph_config_id' => $configId,
                    'category' => $category,
                    'min_income' => $r[0],
                    'max_income' => $r[1],
                    'rate' => $r[2],
                    'description' => null,
                    'sort_order' => $i + 1,
                    'created_by' => $userId,
                ]);
            }
        };

        $insertTer($terA, 'A', $configId, $userId);
        $insertTer($terB, 'B', $configId, $userId);
        $insertTer($terC, 'C', $configId, $userId);

        $totalTer = count($terA) + count($terB) + count($terC);
        $this->command->info("✅ Seeded {$configId}: {$totalTer} TER rates (A=".count($terA).', B='.count($terB).', C='.count($terC).')');

        // ════════════════════════════════════════════════════
        // 3. Progressive Rates (Tarif Pasal 17)
        // ════════════════════════════════════════════════════
        ProgressiveRate::where('pph_config_id', $configId)->delete();

        $progressiveData = [
            [0, 60000000, 5.00, 'Lapisan 1: 0 – 60 juta'],
            [60000001, 250000000, 15.00, 'Lapisan 2: 60 juta – 250 juta'],
            [250000001, 500000000, 25.00, 'Lapisan 3: 250 juta – 500 juta'],
            [500000001, 5000000000, 30.00, 'Lapisan 4: 500 juta – 5 miliar'],
            [5000000001, null, 35.00, 'Lapisan 5: di atas 5 miliar'],
        ];

        foreach ($progressiveData as $i => $p) {
            ProgressiveRate::create([
                'pph_config_id' => $configId,
                'min_income' => $p[0],
                'max_income' => $p[1],
                'rate' => $p[2],
                'description' => $p[3],
                'sort_order' => $i + 1,
                'created_by' => $userId,
            ]);
        }

        $this->command->info('✅ Seeded '.count($progressiveData).' Progressive rates (Pasal 17)');
        $this->command->info('🎉 PPh Config + TER + PTKP + Progressive seeder selesai!');
    }
}
