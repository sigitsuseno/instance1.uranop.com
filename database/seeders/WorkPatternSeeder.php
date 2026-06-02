<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkPatternSeeder extends Seeder
{
    public function run()
    {
        $user = User::first();
        $creatorId = $user ? $user->id : null;

        DB::transaction(function () use ($creatorId) {
            $patterns = [
                [
                    'code' => 'OS',
                    'name' => 'OFFICE STAFF',
                    'description' => 'Pola kerja untuk karyawan office staff',
                    'employee_type' => 'FIXED',
                    'cut_off_date' => 25,
                    'sun_overtime' => false,
                    'work_day' => 6,
                    'sat_type' => 'half',
                    'is_half_day_all' => false,
                    'work_day_hours' => 420,
                    'half_day_hours' => 300,
                    'wd_rest_hours' => 60,
                    'hd_rest_hours' => 60,
                    'is_active' => true,
                ],
                [
                    'code' => 'OP',
                    'name' => 'OFFICE PRODUKSI',
                    'description' => 'Pola kerja untuk karyawan office staff',
                    'employee_type' => 'FIXED',
                    'cut_off_date' => 25,
                    'sun_overtime' => false,
                    'work_day' => 6,
                    'sat_type' => 'half',
                    'is_half_day_all' => false,
                    'work_day_hours' => 420,
                    'half_day_hours' => 300,
                    'wd_rest_hours' => 60,
                    'hd_rest_hours' => 60,
                    'is_active' => true,
                ],
                [
                    'code' => 'PL',
                    'name' => 'KARYAWAN PRODUKSI',
                    'description' => 'Pola kerja untuk karyawan produksi dengan rotasi shift',
                    'employee_type' => 'FLEX-SHIFT',
                    'cut_off_date' => 25,
                    'sun_overtime' => false,
                    'work_day' => 6,
                    'sat_type' => 'half',
                    'is_half_day_all' => false,
                    'work_day_hours' => 420,
                    'half_day_hours' => 300,
                    'wd_rest_hours' => 60,
                    'hd_rest_hours' => 60,
                    'is_active' => true,
                ],
                [
                    'code' => 'SC',
                    'name' => 'SECURITY',
                    'description' => 'Pola kerja untuk security dengan rotasi 6-1',
                    'employee_type' => 'SHIFT',
                    'cut_off_date' => 25,
                    'sun_overtime' => true,
                    'work_day' => 6,
                    'sat_type' => 'full',
                    'is_half_day_all' => false,
                    'work_day_hours' => 420,
                    'half_day_hours' => 300,
                    'wd_rest_hours' => 60,
                    'hd_rest_hours' => 60,
                    'is_active' => true,
                ],
            ];

            foreach ($patterns as $patternData) {
                if (!WorkPattern::where('code', $patternData['code'])->exists()) {
                    WorkPattern::create(array_merge($patternData, [
                        'uuid' => Str::uuid()->toString(),
                        'created_by' => $creatorId,
                        'synced_at' => now(),
                    ]));
                }
            }

            $officeProduksi = WorkPattern::where('code', 'OP')->first();
            $officePattern = WorkPattern::where('code', 'OS')->first();
            $productionPattern = WorkPattern::where('code', 'PL')->first();
            $securityPattern = WorkPattern::where('code', 'SC')->first();

            $shifts = [
                // ========== REGULER ==========
                [
                    'code' => 'P-R',
                    'work_pattern_id' => $officePattern?->id,
                    'name' => 'Reguler',
                    'external_code' => 'P',
                    'work_hour_start' => '08:00',
                    'work_hour_end' => '16:00',
                    'check_in_start' => '05:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '15:00',
                    'check_out_end' => '21:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'overtime_multiplier' => 0,
                    'is_overnight' => false,
                    'is_dayoff' => false,
                    'is_weekend' => false,
                ],
                [
                    'code' => 'S-R',
                    'work_pattern_id' => $officePattern?->id,
                    'name' => 'Sabtu Reguler',
                    'external_code' => 'P',
                    'work_hour_start' => '08:00',
                    'work_hour_end' => '14:00',
                    'check_in_start' => '05:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '12:00',
                    'check_out_end' => '21:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'overtime_multiplier' => 0,
                    'is_overnight' => false,
                    'is_weekend' => true,
                    'is_dayoff' => false,
                ],
                [
                    'code' => 'P-F',
                    'work_pattern_id' => $officeProduksi?->id,
                    'name' => 'STAFF',
                    'external_code' => 'P',
                    'work_hour_start' => '06:50',
                    'work_hour_end' => '14:50',
                    'check_in_start' => '05:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '15:00',
                    'check_out_end' => '21:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'overtime_multiplier' => 0,
                    'is_overnight' => false,
                    'is_dayoff' => false,
                ],
                [
                    'code' => 'S-F',
                    'work_pattern_id' => $officeProduksi?->id,
                    'name' => 'Sabtu STAFF',
                    'external_code' => 'P',
                    'work_hour_start' => '06:50',
                    'work_hour_end' => '12:50',
                    'check_in_start' => '05:00',
                    'check_in_end' => '09:00',
                    'check_out_start' => '12:00',
                    'check_out_end' => '21:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'overtime_multiplier' => 0,
                    'is_overnight' => false,
                    'is_weekend' => true,
                    'is_dayoff' => false,
                ],

                // ========== SHIFT PABRIK ==========
                [
                    'code' => 'P-S',
                    'work_pattern_id' => $productionPattern?->id,
                    'name' => 'Pagi Shift',
                    'external_code' => 'P',
                    'work_hour_start' => '06:50',
                    'work_hour_end' => '14:50',
                    'check_in_start' => '05:00',
                    'check_in_end' => '08:00',
                    'check_out_start' => '13:30',
                    'check_out_end' => '21:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 11,
                    'has_overtime' => true,
                    'overtime_multiplier' => 1.5,
                    'is_overnight' => false,
                ],
                [
                    'code' => 'SP-S',
                    'work_pattern_id' => $productionPattern?->id,
                    'name' => 'Pagi Sabtu Shift',
                    'external_code' => 'P',
                    'work_hour_start' => '06:50',
                    'work_hour_end' => '12:50',
                    'check_in_start' => '05:00',
                    'check_in_end' => '08:00',
                    'check_out_start' => '11:00',
                    'check_out_end' => '21:00',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 11,
                    'has_overtime' => true,
                    'overtime_multiplier' => 1.5,
                    'is_overnight' => false,
                    'is_weekend' => true,
                ],
                [
                    'code' => 'S-S',
                    'work_pattern_id' => $productionPattern?->id,
                    'name' => 'Sore Shift',
                    'external_code' => 'S',
                    'work_hour_start' => '14:50',
                    'work_hour_end' => '22:50',
                    'check_in_start' => '11:00',
                    'check_in_end' => '20:00',
                    'check_out_start' => '19:00',
                    'check_out_end' => '23:55',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'is_overnight' => true,
                    'check_out_overnight_start' => '21:00',
                    'check_out_overnight_end' => '06:00',
                ],
                [
                    'code' => 'SS-S',
                    'work_pattern_id' => $productionPattern?->id,
                    'name' => 'Sore Sabtu Shift',
                    'external_code' => 'S',
                    'work_hour_start' => '12:50',
                    'work_hour_end' => '18:50',
                    'check_in_start' => '11:00',
                    'check_in_end' => '20:00',
                    'check_out_start' => '17:00',
                    'check_out_end' => '23:55',
                    'tolerance_minutes' => 30,
                    'min_work_hours' => 8,
                    'has_overtime' => true,
                    'is_weekend' => true,
                    'is_overnight' => true,
                    'check_out_overnight_start' => '21:00',
                    'check_out_overnight_end' => '06:00',
                ],

                // ========== SATPAM ==========
                [
                    'code' => 'P-SC',
                    'work_pattern_id' => $securityPattern?->id,
                    'name' => 'Pagi Satpam',
                    'external_code' => 'P',
                    'work_hour_start' => '06:00',
                    'work_hour_end' => '14:00',
                    'check_in_start' => '05:00',
                    'check_in_end' => '07:00',
                    'check_out_start' => '13:00',
                    'check_out_end' => '15:00',
                    'tolerance_minutes' => 15,
                    'min_work_hours' => 7,
                    'has_overtime' => false,
                ],
                [
                    'code' => 'S-SC',
                    'work_pattern_id' => $securityPattern?->id,
                    'name' => 'Siang Satpam',
                    'external_code' => 'S',
                    'work_hour_start' => '14:00',
                    'work_hour_end' => '22:00',
                    'check_in_start' => '13:30',
                    'check_in_end' => '15:00',
                    'check_out_start' => '21:00',
                    'check_out_end' => '23:00',
                    'tolerance_minutes' => 15,
                    'min_work_hours' => 7,
                    'has_overtime' => false,
                ],
                [
                    'code' => 'ML-SC',
                    'work_pattern_id' => $securityPattern?->id,
                    'name' => 'Malam Satpam',
                    'external_code' => 'ML',
                    'work_hour_start' => '22:00',
                    'work_hour_end' => '06:00',
                    'check_in_start' => '21:00',
                    'check_in_end' => '23:00',
                    'is_overnight' => true,
                    'check_out_overnight_start' => '05:00',
                    'check_out_overnight_end' => '07:00',
                    'tolerance_minutes' => 15,
                    'min_work_hours' => 7,
                    'has_overtime' => true,
                ],

                // ========== LIBUR ==========
                [
                    'code' => 'L',
                    'work_pattern_id' => null,
                    'name' => 'Libur',
                    'external_code' => 'L',
                    'is_overnight' => false,
                    'is_dayoff' => true,
                ],
                [
                    'code' => 'M',
                    'work_pattern_id' => null,
                    'name' => 'Minggu',
                    'external_code' => 'M',
                    'is_overnight' => false,
                    'is_dayoff' => false,
                ],
            ];

            foreach ($shifts as $shiftData) {
                if (!Shift::where('code', $shiftData['code'])->exists()) {
                    Shift::create(array_merge($shiftData, [
                        'uuid' => Str::uuid()->toString(),
                        'created_by' => $creatorId,
                        'synced_at' => now(),
                    ]));
                }
            }
            
            $this->command->info("✅ WorkPattern and Shift seeder selesai");
        });
    }
}
