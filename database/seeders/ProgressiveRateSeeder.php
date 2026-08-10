<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Settings\Models\PphConfig;
use App\Modules\Settings\Models\ProgressiveRate;

/**
 * Seeder khusus untuk Tarif Pajak Progresif Tahunan (Pasal 17 UU HPP).
 *
 * Digunakan untuk rekonsiliasi PPh 21 di akhir tahun (Desember).
 * Rumus: PKP dikenakan tarif berlapis (layer 1 s/d 5).
 *
 * Digunakan oleh halaman /admin/payroll/pph → tab "Progresif Tahunan".
 *
 * Jalankan:
 *   php artisan db:seed --class=ProgressiveRateSeeder
 */
class ProgressiveRateSeeder extends Seeder
{
    /**
     * Tarif progresif Pasal 17 ayat (1) huruf a UU HPP.
     * Format: [min_income, max_income, rate, description]
     */
    private const PROGRESSIVE_DATA = [
        [0,             60_000_000,     5.00,  'Lapisan 1: 0 – 60 juta'],
        [60_000_001,    250_000_000,   15.00,  'Lapisan 2: 60 juta – 250 juta'],
        [250_000_001,   500_000_000,   25.00,  'Lapisan 3: 250 juta – 500 juta'],
        [500_000_001,   5_000_000_000, 30.00,  'Lapisan 4: 500 juta – 5 miliar'],
        [5_000_000_001, null,          35.00,  'Lapisan 5: di atas 5 miliar'],
    ];

    private const DEFAULT_USER_ID = 1;

    public function run(): void
    {
        // ── 1. Pastikan pph_config aktif ada ──
        $config = PphConfig::where('is_active', true)->first();

        if (! $config) {
            $config = PphConfig::create([
                'uuid'                 => (string) Str::uuid(),
                'effective_date'       => '2024-01-01',
                'is_active'            => true,
                'calculation_method'   => 'ter',
                'pph_method'           => 'gross',
                'non_npwp_penalty'     => true,
                'non_npwp_multiplier'  => 1.20,
                'description'          => 'Konfigurasi PPh 21 Standar Indonesia (PP 58/2023 TER & UU HPP) — auto-generated oleh ProgressiveRateSeeder',
                'created_by'           => self::DEFAULT_USER_ID,
            ]);

            $this->command->info("ℹ️  pph_configs (id={$config->id}) dibuat otomatis.");
        }

        $configId = $config->id;

        // ── 2. Hapus progressive rates existing (idempoten) ──
        $deleted = ProgressiveRate::where('pph_config_id', $configId)->delete();
        if ($deleted > 0) {
            $this->command->info("🧹 {$deleted} progressive rate(s) lama dihapus.");
        }

        // ── 3. Insert tarif progresif Pasal 17 UU HPP ──
        $sortOrder = 0;
        foreach (self::PROGRESSIVE_DATA as $d) {
            ProgressiveRate::create([
                'pph_config_id' => $configId,
                'min_income'    => $d[0],
                'max_income'    => $d[1],
                'rate'          => $d[2],
                'description'   => $d[3],
                'sort_order'    => ++$sortOrder,
                'created_by'    => self::DEFAULT_USER_ID,
            ]);
        }

        $this->command->info('✅ Progressive Rate seeder selesai — ' . count(self::PROGRESSIVE_DATA) . " lapisan untuk pph_config_id={$configId}");

        // Tampilkan tabel ringkasan
        $this->command->info('');
        $this->command->info('┌──────┬────────────────────────┬────────────────────────┬──────────────┐');
        $this->command->info('│ Lap. │ PKP Dari (Rp)          │ PKP Sampai (Rp)        │ Tarif        │');
        $this->command->info('├──────┼────────────────────────┼────────────────────────┼──────────────┤');
        foreach (self::PROGRESSIVE_DATA as $i => $d) {
            $sampai = $d[1] !== null ? number_format($d[1], 0, ',', '.') : 'Tak terhingga';
            $this->command->info(sprintf(
                '│ %-4d │ %22s │ %22s │ %10s%%  │',
                $i + 1,
                number_format($d[0], 0, ',', '.'),
                $sampai,
                number_format($d[2], 0, ',', '.')
            ));
        }
        $this->command->info('└──────┴────────────────────────┴────────────────────────┴──────────────┘');
        $this->command->info('');
        $this->command->info('📋 Halaman: /admin/payroll/pph → tab "Progresif Tahunan"');

        // Contoh perhitungan
        $this->command->info('');
        $this->command->info('💡 Contoh cara kerja tarif progresif:');
        $this->command->info('   PKP = Rp 300.000.000');
        $this->command->info('   • 0 – 60jt      : 5%  × 60.000.000  = Rp   3.000.000');
        $this->command->info('   • 60jt – 250jt  : 15% × 190.000.000 = Rp  28.500.000');
        $this->command->info('   • 250jt – 300jt : 25% × 50.000.000  = Rp  12.500.000');
        $this->command->info('   ─────────────────────────────────────────────────────');
        $this->command->info('   Total PPh setahun                    = Rp  44.000.000');
    }
}
