<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@uranop.com',
                'password' => Hash::make('PassTersulit2026'),
                'employee_number' => 'SA001',
                'is_active' => true,
                'user_type' => 'superadmin',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'HR Manager',
                'email' => 'hr@uranop.com',
                'password' => Hash::make('PassTersulit2026'),
                'employee_number' => 'HR001',
                'is_active' => true,
                'user_type' => 'hrmanager',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Admin Manager',
                'email' => 'adm@uranop.com',
                'password' => Hash::make('PassTersulit2026'),
                'employee_number' => 'AM001',
                'is_active' => true,
                'user_type' => 'adm_manager',
                'email_verified_at' => now(),
            ],
            [
                'name' => 'HR Branch',
                'email' => 'hrbranch@uranop.com',
                'password' => Hash::make('PassTersulit2026'),
                'employee_number' => 'HB001',
                'is_active' => true,
                'user_type' => 'hrbranch',
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            $user->assignRole($data['user_type']);
        }

        $this->command->info('UserSeeder completed! ' . count($users) . ' users created.');
    }
}
