<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'CT',
                'name' => 'Cuti Tahunan',
                'category' => 'leave',
                'balance_type' => 'decrement',
                'is_paid' => true,
                'max_days' => 12,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CB',
                'name' => 'Cuti Bersama',
                'category' => 'leave',
                'balance_type' => 'decrement',
                'is_paid' => true,
                'max_days' => null,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SKT',
                'name' => 'Sakit',
                'category' => 'sick',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => null,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => true,
                'is_active' => true,
            ],
            [
                'code' => 'IZN',
                'name' => 'Izin',
                'category' => 'permit',
                'balance_type' => 'none',
                'is_paid' => false,
                'max_days' => null,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'is_active' => true,
            ],
        ];

        foreach ($types as $data) {
            if (!DB::table('leave_types')->where('code', $data['code'])->exists()) {
                DB::table('leave_types')->insert(array_merge($data, [
                    'uuid' => Str::uuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
}
