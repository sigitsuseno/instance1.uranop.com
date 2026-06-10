<?php

namespace Database\Seeders;

use App\Modules\Settings\Models\OvertimeRule;
use App\Modules\Settings\Models\OvertimeRuleDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OvertimeRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Default overtime rules:
     *   - OVT-REG: Lembur hari biasa (is_holiday=0)
     *     Jam ke-1 = 1.5x, Jam ke-2+ = 2.0x
     *   - OVT-LM:  Lembur Mingguan / Hari Libur (is_holiday=1)
     *     Formula khusus: (total_jam - 1) × 2 → ditangani di kode
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // ── Hapus rules existing ──────────────────────────────
        OvertimeRuleDetail::truncate();
        OvertimeRule::truncate();

        // ════════════════════════════════════════════════════════
        // RULE 1: Overtime Reguler (Hari Biasa)
        // ════════════════════════════════════════════════════════
        $regularRule = OvertimeRule::create([
            'uuid'             => Str::uuid()->toString(),
            'code'             => 'OVT-REG',
            'name'             => 'Lembur Hari Biasa',
            'is_active'        => true,
            'is_holiday'       => false,
            'is_saturday'      => false,
            'work_pattern_id'  => null,
            'description'      => 'Jam ke-1: 1.5x | Jam ke-2+: 2.0x',
        ]);

        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $regularRule->id,
            'hour'              => 1,
            'multiplier'        => 1.5,
        ]);

        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $regularRule->id,
            'hour'              => 2,    // jam ke-2 dan seterusnya
            'multiplier'        => 2.0,
        ]);

        // ════════════════════════════════════════════════════════
        // RULE 2: Lembur Mingguan / Hari Libur (LM)
        // ════════════════════════════════════════════════════════
        $holidayRule = OvertimeRule::create([
            'uuid'             => Str::uuid()->toString(),
            'code'             => 'OVT-LM',
            'name'             => 'Lembur Mingguan / Hari Libur',
            'is_active'        => true,
            'is_holiday'       => true,
            'is_saturday'      => false,
            'work_pattern_id'  => null,
            'description'      => 'Jam ke-1 s/d 7: 2.0x',
        ]);

        // LM: semua jam ×2.0 (potongan istirahat 1 jam ditangani di rumus lm_count)
        foreach ([1, 2, 3, 4, 5, 6, 7] as $h) {
            OvertimeRuleDetail::create([
                'uuid'              => Str::uuid()->toString(),
                'overtime_rule_id'  => $holidayRule->id,
                'hour'              => $h,
                'multiplier'        => 2.0,
            ]);
        }

        // ════════════════════════════════════════════════════════
        // RULE 3: Lembur Mingguan / Hari Libur — Sabtu
        // ════════════════════════════════════════════════════════
        $saturdayRule = OvertimeRule::create([
            'uuid'             => Str::uuid()->toString(),
            'code'             => 'OVT-LM-S',
            'name'             => 'OVERTIME LM SABTU',
            'is_active'        => true,
            'is_holiday'       => true,
            'is_saturday'      => true,
            'work_pattern_id'  => null,
            'description'      => 'Jam 1-5: 2.0x | Jam 6: 3.0x | Jam 7: 4.0x',
        ]);

        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $saturdayRule->id,
            'hour'              => 1,
            'multiplier'        => 2.0,
        ]);
        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $saturdayRule->id,
            'hour'              => 2,
            'multiplier'        => 2.0,
        ]);
        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $saturdayRule->id,
            'hour'              => 5,
            'multiplier'        => 2.0,
        ]);
        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $saturdayRule->id,
            'hour'              => 6,
            'multiplier'        => 3.0,
        ]);
        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $saturdayRule->id,
            'hour'              => 7,
            'multiplier'        => 4.0,
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
