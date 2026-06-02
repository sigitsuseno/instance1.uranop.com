<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Schedule\Models\WorkPatternType;

class WorkPatternTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'FIXED', 'name' => 'FIXED'],
            ['code' => 'FLEX-SHIFT', 'name' => 'FLEX-SHIFT'],
            ['code' => 'SHIFT', 'name' => 'SHIFT'],
            ['code' => 'LONGSHIFT', 'name' => 'LONGSHIFT'],
            ['code' => 'SPLIT', 'name' => 'SPLIT'],
            ['code' => 'FLEXI', 'name' => 'FLEXI'],
            ['code' => 'HOURLY', 'name' => 'HOURLY'],
            ['code' => 'ON_CAL', 'name' => 'ON_CAL'],
            ['code' => 'SEASONAL', 'name' => 'SEASONAL'],
        ];

        foreach ($types as $type) {
            WorkPatternType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $type['name'],
                    'label' => $type['name'], // default label
                    'keterangan' => 'Tipe pola kerja ' . $type['name'],
                ]
            );
        }
    }
}
