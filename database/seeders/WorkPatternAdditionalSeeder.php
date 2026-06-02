<?php

namespace Database\Seeders;

use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkPatternAdditionalSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // ========== WORK PATTERNS ==========
            $patterns = [
                [
                    'code' => 'GD',
                    'name' => 'STAFF GUDANG',
                    'description' => 'Pola kerja untuk staff gudang',
                    'employee_type' => 'FIXED',
                    'cut_off_date' => 25,
                    'sun_overtime' => false,
                    'work_day' => 6,
                    'sat_type' => 'half',
                    'is_half_day_all' => false,
                    'work_day_hours' => 480,
                    'half_day_hours' => 360,
                    'wd_rest_hours' => 60,
                    'hd_rest_hours' => 60,
                    'is_active' => true,
                ],
                [
                    'code' => 'OPS',
                    'name' => 'OFFICE PROD STAFF',
                    'description' => 'Pola kerja untuk office produksi staff',
                    'employee_type' => 'FIXED',
                    'cut_off_date' => 25,
                    'sun_overtime' => false,
                    'work_day' => 6,
                    'sat_type' => 'half',
                    'is_half_day_all' => false,
                    'work_day_hours' => 480,
                    'half_day_hours' => 360,
                    'wd_rest_hours' => 60,
                    'hd_rest_hours' => 60,
                    'is_active' => true,
                ],
            ];

            foreach ($patterns as $data) {
                if (!WorkPattern::where('code', $data['code'])->exists()) {
                    WorkPattern::create(array_merge($data, [
                        'uuid' => Str::uuid()->toString(),
                        'created_by' => 1,
                        'synced_at' => now(),
                    ]));
                    $this->command->info("  ✅ Work Pattern {$data['code']} created");
                } else {
                    $this->command->info("  ⏭️  Work Pattern {$data['code']} already exists");
                }
            }

            // ========== SHIFTS ==========
            $gd = WorkPattern::where('code', 'GD')->first();
            $ops = WorkPattern::where('code', 'OPS')->first();

            $shifts = [
                // GD — STAFF GUDANG
                [
                    'code' => 'P-GD',
                    'work_pattern_id' => $gd?->id,
                    'name' => 'Pagi Gudang',
                    'external_code' => 'P',
                    'work_hour_start' => '08:00',
                    'work_hour_end' => '16:00',
                    'check_in_start' => '06:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '15:00',
                    'check_out_end' => '17:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'overtime_multiplier' => 1.5,
                    'is_overnight' => false,
                    'is_dayoff' => false,
                ],
                [
                    'code' => 'P-SGD',
                    'work_pattern_id' => $gd?->id,
                    'name' => 'Sabtu Gudang',
                    'external_code' => 'P',
                    'work_hour_start' => '08:00',
                    'work_hour_end' => '14:00',
                    'check_in_start' => '06:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '13:00',
                    'check_out_end' => '15:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 6,
                    'has_overtime' => true,
                    'overtime_multiplier' => 1.5,
                    'is_overnight' => false,
                    'is_weekend' => true,
                    'is_dayoff' => false,
                ],

                // OPS — OFFICE PROD STAFF
                [
                    'code' => 'P-PS',
                    'work_pattern_id' => $ops?->id,
                    'name' => 'Pagi Prod Staff',
                    'external_code' => 'P',
                    'work_hour_start' => '08:00',
                    'work_hour_end' => '16:00',
                    'check_in_start' => '06:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '15:00',
                    'check_out_end' => '17:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'overtime_multiplier' => 1.5,
                    'is_overnight' => false,
                    'is_dayoff' => false,
                ],
                [
                    'code' => 'P-PSS',
                    'work_pattern_id' => $ops?->id,
                    'name' => 'Sabtu Prod Staff',
                    'external_code' => 'P',
                    'work_hour_start' => '08:00',
                    'work_hour_end' => '14:00',
                    'check_in_start' => '06:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '13:00',
                    'check_out_end' => '15:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 6,
                    'has_overtime' => true,
                    'overtime_multiplier' => 1.5,
                    'is_overnight' => false,
                    'is_weekend' => true,
                    'is_dayoff' => false,
                ],
            ];

            foreach ($shifts as $data) {
                if (!Shift::where('code', $data['code'])->exists()) {
                    Shift::create(array_merge($data, [
                        'uuid' => Str::uuid()->toString(),
                        'created_by' => 1,
                        'synced_at' => now(),
                    ]));
                    $this->command->info("  ✅ Shift {$data['code']} created");
                } else {
                    $this->command->info("  ⏭️  Shift {$data['code']} already exists");
                }
            }

            $this->command->info("✅ WorkPatternAdditionalSeeder selesai!");
        });
    }
}
