<?php

namespace Database\Seeders;

use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Board of Directors
        $bod = Department::create([
            'uuid' => (string) Str::uuid(),
            'code' => 'BOD',
            'name' => 'Board of Directors',
            'level' => 1,
            'is_active' => true,
        ]);
        
        Position::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $bod->id,
            'code' => 'DIR',
            'name' => 'Director',
            'is_managerial' => true,
            'is_active' => true,
        ]);

        // HR Department
        $hr = Department::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $bod->id,
            'code' => 'HRD',
            'name' => 'Human Resources',
            'level' => 2,
            'is_active' => true,
        ]);

        Position::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $hr->id,
            'code' => 'HR-MGR',
            'name' => 'HR Manager',
            'is_managerial' => true,
            'is_active' => true,
        ]);

        Position::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $hr->id,
            'code' => 'HR-STF',
            'name' => 'HR Staff',
            'is_managerial' => false,
            'is_active' => true,
        ]);

        // IT Department
        $it = Department::create([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $bod->id,
            'code' => 'ITD',
            'name' => 'Information Technology',
            'level' => 2,
            'is_active' => true,
        ]);

        Position::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $it->id,
            'code' => 'IT-MGR',
            'name' => 'IT Manager',
            'is_managerial' => true,
            'is_active' => true,
        ]);

        Position::create([
            'uuid' => (string) Str::uuid(),
            'department_id' => $it->id,
            'code' => 'IT-DEV',
            'name' => 'Software Developer',
            'is_managerial' => false,
            'is_active' => true,
        ]);
    }
}
