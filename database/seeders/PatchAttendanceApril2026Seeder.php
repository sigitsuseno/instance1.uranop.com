<?php

namespace Database\Seeders;

/**
 * Seeder untuk patch attendance dari SEMUA file Excel di folder "april 2026".
 *
 * Cara pakai:
 *   php artisan db:seed --class="Database\Seeders\PatchAttendanceApril2026Seeder"
 */
class PatchAttendanceApril2026Seeder extends PatchAttendanceFromExcelSeeder
{
    protected string $scanFolder = __DIR__.'/FINGER-JUNI';
}

