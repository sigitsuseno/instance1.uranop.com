<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OvertimeCalculatorConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('attendance_calculator_configs')->whereNull('work_pattern_id')->exists()) {
            return;
        }

        DB::table('attendance_calculator_configs')->insert([
            'uuid'                    => Str::uuid()->toString(),
            'name'                    => 'Default Global',
            'work_pattern_id'         => null,
            'normal_work_minutes'     => 480,
            'saturday_work_minutes'   => 360,
            'holiday_max_minutes'     => 480,
            'shift_saturday_flat'     => 120,
            'late_deducts_overtime'   => false,
            'late_tolerance'          => 0,
            'lm_rest_deduction'       => 60,
            'rounding_interval'       => 30,
            'rounding_threshold'      => 5,
            'hourly_divisor'          => 173,
            'is_active'               => true,
            'description'             => 'Konfigurasi default — dibuat otomatis oleh seeder.',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }
}
