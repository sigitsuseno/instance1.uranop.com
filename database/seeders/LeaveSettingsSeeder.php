<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePeriod;
use Illuminate\Support\Facades\DB;

class LeaveSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Untuk menghindari foreign key constraint error jika mau di reset
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        LeaveType::truncate();
        LeavePolicy::truncate();
        LeavePeriod::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. Leave Types
        $annualLeave = LeaveType::create([
            'code' => 'AL',
            'name' => 'Cuti Tahunan',
            'category' => 'leave',
            'balance_type' => 'decrement',
            'is_paid' => true,
            'max_days' => 12,
        ]);

        $sickLeave = LeaveType::create([
            'code' => 'SK',
            'name' => 'Sakit',
            'category' => 'sick',
            'balance_type' => 'increment',
            'is_paid' => true,
            'max_days' => null, // Tidak ada maksimal strict karena bisa lama dengan surat dokter
        ]);

        $permitLeave = LeaveType::create([
            'code' => 'PM',
            'name' => 'Izin Kepentingan Pribadi',
            'category' => 'permit',
            'balance_type' => 'decrement', // Misalnya dapat jatah izin 3 hari setahun tanpa potong cuti
            'is_paid' => false, // Potong gaji jika izin melebihi batas atau secara default
            'max_days' => 3,
        ]);

        $maternityLeave = LeaveType::create([
            'code' => 'ML',
            'name' => 'Cuti Melahirkan',
            'category' => 'special',
            'balance_type' => 'none',
            'is_paid' => true,
            'max_days' => 90,
        ]);

        // 2. Leave Policies
        LeavePolicy::create([
            'leave_type_id' => $annualLeave->id,
            'name' => 'Kebijakan Cuti Tahunan Standar',
            'description' => 'Berlaku untuk semua karyawan yang telah mencapai 1 tahun masa kerja.',
            'requires_one_year_service' => true,
            'can_carry_forward' => true,
            'max_carry_forward_days' => 6,
            'entitlement_days' => 12,
        ]);

        LeavePolicy::create([
            'leave_type_id' => $sickLeave->id,
            'name' => 'Kebijakan Cuti Sakit',
            'description' => 'Sakit dengan atau tanpa surat dokter.',
            'requires_one_year_service' => false,
            'can_carry_forward' => false,
            'max_carry_forward_days' => 0,
            'entitlement_days' => 0, // Tidak dicatat sebagai kuota awal (karena balance_type = increment)
        ]);

        LeavePolicy::create([
            'leave_type_id' => $permitLeave->id,
            'name' => 'Kebijakan Izin Pribadi',
            'description' => 'Izin khusus keperluan pribadi dengan jatah 3 hari setahun.',
            'requires_one_year_service' => false,
            'can_carry_forward' => false,
            'max_carry_forward_days' => 0,
            'entitlement_days' => 3,
        ]);

        LeavePolicy::create([
            'leave_type_id' => $maternityLeave->id,
            'name' => 'Kebijakan Cuti Melahirkan',
            'description' => 'Cuti selama 3 bulan untuk melahirkan.',
            'requires_one_year_service' => false,
            'can_carry_forward' => false,
            'max_carry_forward_days' => 0,
            'entitlement_days' => 90, // Sebagai patokan
        ]);

        // 3. Leave Periods (Berbasis Idul Fitri)
        // Misal Idul Fitri 2025: 31 Maret 2025
        LeavePeriod::create([
            'name' => 'Periode 2025-2026 (Pasca Lebaran)',
            'start_date' => '2025-04-10',
            'end_date' => '2026-03-18', // Sebelum Lebaran 2026 (sekitar 19 Maret 2026)
            'status' => 'active',
        ]);

        // Misal Idul Fitri 2026: 19 Maret 2026
        LeavePeriod::create([
            'name' => 'Periode 2026-2027 (Pasca Lebaran)',
            'start_date' => '2026-03-25',
            'end_date' => '2027-03-08',
            'status' => 'active',
        ]);
    }
}
