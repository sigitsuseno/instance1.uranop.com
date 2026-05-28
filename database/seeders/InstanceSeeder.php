<?php

namespace Database\Seeders;

use App\Modules\Instance\Models\Instance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InstanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Instance::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'INS-001',
            'name' => 'Main Instance',
            'slug' => 'main-instance',
            'domain' => 'instance1.uranop.com',
            'database_name' => 'instance1',
            'is_active' => true,
        ]);
    }
}
