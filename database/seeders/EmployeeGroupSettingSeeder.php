<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Settings\Models\EmployeeGroupSetting;

class EmployeeGroupSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'tab_id' => 'group_es',
                'tab_name' => 'Otoritas Manajemen',
                'group_label' => 'Otoritas Manajemen',
                'icon' => 'bx-shield-quarter',
                'filters' => null, // No specific filter
                'sort_order' => 10,
            ],
            [
                'tab_id' => 'meal_allowance',
                'tab_name' => 'Uang Makan',
                'group_label' => 'UM GROUP',
                'icon' => 'bx-restaurant',
                'filters' => ['employment_status' => ['permanent']],
                'sort_order' => 20,
            ]
        ];

        foreach ($settings as $setting) {
            EmployeeGroupSetting::updateOrCreate(
                ['tab_id' => $setting['tab_id']],
                $setting
            );
        }
    }
}
