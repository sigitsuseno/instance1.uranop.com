<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            InstanceSeeder::class,
            PayPeriodSeeder::class,
            WorkingCalendarSeeder::class,
            HolidaySeeder::class,
            WorkPatternSeeder::class,
            LeaveSettingsSeeder::class,
        ]);
    }
}
