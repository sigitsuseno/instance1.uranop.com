<?php

namespace Database\Seeders;

/**
 * Seeder untuk patch attendance dari SEMUA file Excel di folder "mei 2026".
 *
 * Cara pakai:
 *   php artisan db:seed --class="Database\Seeders\PatchAttendanceMei2026Seeder"
 */
class PatchAttendanceMei2026Seeder extends PatchAttendanceFromExcelSeeder
{
    protected string $scanFolder = __DIR__.'/mei 2026';
}
