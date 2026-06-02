<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\WorkingCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        $creatorId = $user ? $user->id : null;

        // Truncate tables to ensure a fresh start
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Holiday::truncate();
        WorkingCalendar::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create 2025 Calendar
        $calendar2025 = WorkingCalendar::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Kalender Kerja 2025',
            'year' => 2025,
            'description' => 'Kalender default tahun 2025',
            'is_active' => true,
            'created_by' => $creatorId,
        ]);

        // Create 2026 Calendar
        $calendar2026 = WorkingCalendar::create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Kalender Kerja 2026',
            'year' => 2026,
            'description' => 'Kalender default tahun 2026',
            'is_active' => true,
            'created_by' => $creatorId,
        ]);

        // Holidays Data
        $holidays2025 = [
            ['name' => 'Hari Raya Natal', 'date' => '2025-12-25', 'description' => 'Kelahiran Yesus Kristus'],
        ];

        // 2026 Holidays (Estimates for religious holidays based on general Gregorian calendar)
        $holidays2026 = [
            ['name' => 'Tahun Baru Masehi', 'date' => '2026-01-01', 'description' => 'Tahun Baru 2026'],
            ['name' => 'Isra Mikraj', 'date' => '2026-02-18', 'description' => 'Isra Mikraj Nabi Muhammad SAW'],
            ['name' => 'Tahun Baru Imlek', 'date' => '2026-02-20', 'description' => 'Tahun Baru Imlek 2577'],
            ['name' => 'Hari Raya Nyepi', 'date' => '2026-03-22', 'description' => 'Tahun Baru Saka 1948'],
            ['name' => 'Wafat Yesus Kristus', 'date' => '2026-04-03', 'description' => 'Jumat Agung'],
            ['name' => 'Hari Paskah', 'date' => '2026-04-05', 'description' => 'Kebangkitan Yesus Kristus'],
            ['name' => 'Hari Raya Idul Fitri', 'date' => '2026-04-10', 'description' => 'Idul Fitri 1447 H'],
            ['name' => 'Hari Raya Idul Fitri', 'date' => '2026-04-11', 'description' => 'Idul Fitri 1447 H'],
            ['name' => 'Hari Buruh Internasional', 'date' => '2026-05-01', 'description' => 'May Day'],
            ['name' => 'Kenaikan Yesus Kristus', 'date' => '2026-05-14', 'description' => 'Kenaikan Isa Al Masih'],
            ['name' => 'Hari Raya Waisak', 'date' => '2026-05-28', 'description' => 'Hari Raya Waisak 2570 BE'],
            ['name' => 'Hari Lahir Pancasila', 'date' => '2026-06-01', 'description' => 'Hari Lahir Pancasila'],
            ['name' => 'Hari Raya Idul Adha', 'date' => '2026-06-17', 'description' => 'Idul Adha 1447 H'],
            ['name' => 'Tahun Baru Islam', 'date' => '2026-07-07', 'description' => '1 Muharram 1448 H'],
            ['name' => 'Hari Kemerdekaan RI', 'date' => '2026-08-17', 'description' => 'HUT Kemerdekaan RI'],
            ['name' => 'Maulid Nabi Muhammad SAW', 'date' => '2026-09-16', 'description' => 'Maulid Nabi 1448 H'],
            ['name' => 'Hari Raya Natal', 'date' => '2026-12-25', 'description' => 'Kelahiran Yesus Kristus'],
        ];

        $inserted = 0;

        foreach ($holidays2025 as $holiday) {
            Holiday::create([
                'uuid' => Str::uuid()->toString(),
                'working_calendar_id' => $calendar2025->id,
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'type' => 'Nasional',
                'is_national_holiday' => true,
                'description' => $holiday['description'],
                'created_by' => $creatorId,
                'synced_at' => now(),
            ]);
            $inserted++;
        }

        foreach ($holidays2026 as $holiday) {
            Holiday::create([
                'uuid' => Str::uuid()->toString(),
                'working_calendar_id' => $calendar2026->id,
                'date' => $holiday['date'],
                'name' => $holiday['name'],
                'type' => 'Nasional',
                'is_national_holiday' => true,
                'description' => $holiday['description'],
                'created_by' => $creatorId,
                'synced_at' => now(),
            ]);
            $inserted++;
        }

        $this->command->info("✅ Holiday seeder selesai: {$inserted} holidays inserted for 2025 and 2026.");
    }
}
