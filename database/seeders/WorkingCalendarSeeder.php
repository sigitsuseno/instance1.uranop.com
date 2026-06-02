<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Schedule\Models\WorkingCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkingCalendarSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        $creatorId = $user ? $user->id : null;

        $exists = WorkingCalendar::where('year', 2026)->exists();

        if (! $exists) {
            WorkingCalendar::create([
                'uuid' => Str::uuid()->toString(),
                'name' => 'Kalender Kerja 2026',
                'year' => 2026,
                'description' => 'Kalender default tahun 2026 dengan libur nasional dan cuti bersama',
                'created_by' => $creatorId,
                'is_active' => true,
                'synced_at' => now(),
            ]);

            $this->command->info('✅ Working Calendar 2026 berhasil dibuat');
        } else {
            $this->command->warn('⚠️ Working Calendar 2026 sudah ada, skip...');
        }
    }
}
