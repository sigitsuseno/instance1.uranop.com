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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Bersihkan tipe lama yang sudah tidak dipakai (kode AL, SK, PM, ML)
        LeaveType::whereIn('code', ['AL', 'SK', 'PM', 'ML'])->delete();

        // --- 1. Leave Types (12 tipe lengkap) ---
        $types = [
            [
                'code' => 'CT',
                'name' => 'Cuti Tahunan',
                'category' => 'leave',
                'balance_type' => 'decrement',
                'is_paid' => true,
                'max_days' => 12,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#10b981',
            ],
            [
                'code' => 'CM',
                'name' => 'Cuti Menikah',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 3,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#f59e0b',
            ],
            [
                'code' => 'CKM',
                'name' => 'Cuti Keluarga Meninggal',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 3,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#6366f1',
            ],
            [
                'code' => 'CH',
                'name' => 'Cuti Hajatan',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 3,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#ec4899',
            ],
            [
                'code' => 'CTM',
                'name' => 'Cuti Melahirkan',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 90,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => true,
                'color_hex' => '#8b5cf6',
            ],
            [
                'code' => 'CTK',
                'name' => 'Cuti Keguguran',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 45,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => true,
                'color_hex' => '#ef4444',
            ],
            [
                'code' => 'SKT',
                'name' => 'Sakit',
                'category' => 'sick',
                'balance_type' => 'increment',
                'is_paid' => true,
                'max_days' => null,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => true,
                'color_hex' => '#f87171',
            ],
            [
                'code' => 'CTH',
                'name' => 'Cuti Haid',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 2,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#fb7185',
            ],
            [
                'code' => 'CTI',
                'name' => 'Cuti Ibadah',
                'category' => 'special',
                'balance_type' => 'none',
                'is_paid' => true,
                'max_days' => 7,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#3b82f6',
            ],
            [
                'code' => 'ITM',
                'name' => 'Izin Tidak Masuk',
                'category' => 'permit',
                'balance_type' => 'decrement',
                'is_paid' => false,
                'max_days' => 3,
                'affects_daily_worker' => true,
                'affects_monthly_worker' => true,
                'requires_medical_doc' => false,
                'color_hex' => '#6b7280',
            ],
            [
                'code' => 'IMT',
                'name' => 'Izin Masuk Terlambat',
                'category' => 'permit',
                'balance_type' => 'none',
                'is_paid' => false,
                'max_days' => null,
                'affects_daily_worker' => false,
                'affects_monthly_worker' => false,
                'requires_medical_doc' => false,
                'color_hex' => '#94a3b8',
            ],
            [
                'code' => 'IPA',
                'name' => 'Izin Pulang Awal',
                'category' => 'permit',
                'balance_type' => 'none',
                'is_paid' => false,
                'max_days' => null,
                'affects_daily_worker' => false,
                'affects_monthly_worker' => false,
                'requires_medical_doc' => false,
                'color_hex' => '#cbd5e1',
            ],
        ];

        // Upsert leave types — jaga agar ID existing tidak berubah jika code sama
        foreach ($types as $type) {
            LeaveType::updateOrCreate(
                ['code' => $type['code']],
                array_merge($type, ['is_active' => true])
            );
        }

        // Ambil reference ke tipe yang digunakan di policies
        $annualLeave = LeaveType::where('code', 'CT')->first();
        $sickLeave = LeaveType::where('code', 'SKT')->first();
        $permitLeave = LeaveType::where('code', 'ITM')->first();
        $maternityLeave = LeaveType::where('code', 'CTM')->first();

        // --- 2. Leave Policies ---
        // Hapus policies lama yang referencenya mungkin berubah
        LeavePolicy::truncate();

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
            'entitlement_days' => 0,
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
            'entitlement_days' => 90,
        ]);

        // --- 3. Leave Periods ---
        // Jangan truncate periods — bisa jadi sudah ada data generate
        if (LeavePeriod::count() === 0) {
            LeavePeriod::create([
                'name' => 'Periode 2025-2026 (Pasca Lebaran)',
                'start_date' => '2025-04-10',
                'end_date' => '2026-03-18',
                'status' => 'active',
            ]);

            LeavePeriod::create([
                'name' => 'Periode 2026-2027 (Pasca Lebaran)',
                'start_date' => '2026-03-19',
                'end_date' => '2027-03-08',
                'status' => 'active',
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
