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
            'work_pattern_id'  => null, // berlaku untuk semua pattern
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
            'work_pattern_id'  => null,
            'description'      => '(total_jam - 1 jam istirahat) × 2, maksimal 8 jam',
        ]);

        // Detail LM: hour=1→0 (deduct istirahat), hour=2→2.0 (jam ke-2+)
        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $holidayRule->id,
            'hour'              => 1,
            'multiplier'        => 0.0,  // jam pertama → istirahat (tidak dihitung)
        ]);

        OvertimeRuleDetail::create([
            'uuid'              => Str::uuid()->toString(),
            'overtime_rule_id'  => $holidayRule->id,
            'hour'              => 2,
            'multiplier'        => 2.0,
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
