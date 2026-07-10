<?php

namespace Database\Seeders;

/**
 * Seeder untuk patch attendance dari SEMUA file Excel di folder "maret 2026".
 *
 * Cara pakai:
 *   php artisan db:seed --class="Database\Seeders\PatchAttendanceMaret2026Seeder"
 */
class PatchAttendanceMaret2026Seeder extends PatchAttendanceFromExcelSeeder
{
    protected string $scanFolder = __DIR__.'/maret 2026';
}
