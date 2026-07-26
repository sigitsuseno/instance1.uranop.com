<?php

namespace Database\Seeders;

use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeBankCabangSeeder extends Seeder
{
    public function run(): void
    {
        Employee::query()->whereNull('bank_cabang')->orWhere('bank_cabang', '')->update([
            'bank_cabang' => 'SALATIGA',
        ]);

        $this->command->info('Semua employee bank_cabang diisi dengan SALATIGA.');
    }
}
