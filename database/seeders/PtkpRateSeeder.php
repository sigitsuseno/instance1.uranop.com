<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Modules\Settings\Models\PphConfig;
use App\Modules\Settings\Models\PtkpRate;

/**
 * Seeder khusus untuk tabel PTKP (Penghasilan Tidak Kena Pajak).
 *
 * Sesuai UU HPP — Pasal 7:
 *   - WP sendiri               : Rp 54.000.000
 *   - Tambahan kawin           : Rp  4.500.000
 *   - Tambahan tanggungan      : Rp  4.500.000/orang (maks 3)
 *
 * Digunakan oleh halaman /admin/payroll/pph → tab "Tarif PTKP".
 *
 * Jalankan:
 *   php artisan db:seed --class=PtkpRateSeeder
 */
class PtkpRateSeeder extends Seeder
{
    /**
     * Tabel PTKP resmi UU HPP.
     * Format: [status_code, status_name, value]
     *
     * Rumus:
     *   TK/0 = 54.000.000
     *   +Kawin  = +4.500.000
     *   +Tanggungan = +4.500.000 per orang (maks 3)
     */
    private const PTKP_DATA = [
        ['TK/0', 'Tidak Kawin 0 Tanggungan',  54_000_000],
        ['TK/1', 'Tidak Kawin 1 Tanggungan',  58_500_000],
        ['K/0',  'Kawin 0 Tanggungan',        58_500_000],
        ['TK/2', 'Tidak Kawin 2 Tanggungan',  63_000_000],
        ['K/1',  'Kawin 1 Tanggungan',        63_000_000],
        ['TK/3', 'Tidak Kawin 3 Tanggungan',  67_500_000],
        ['K/2',  'Kawin 2 Tanggungan',        67_500_000],
        ['K/3',  'Kawin 3 Tanggungan',        72_000_000],
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
                'description'          => 'Konfigurasi PPh 21 Standar Indonesia (PP 58/2023 TER & UU HPP) — auto-generated oleh PtkpRateSeeder',
                'created_by'           => self::DEFAULT_USER_ID,
            ]);

            $this->command->info("ℹ️  pph_configs (id={$config->id}) dibuat otomatis.");
        }

        $configId = $config->id;

        // ── 2. Hapus PTKP existing untuk konfigurasi ini (idempoten) ──
        $deleted = PtkpRate::where('pph_config_id', $configId)->delete();
        if ($deleted > 0) {
            $this->command->info("🧹 {$deleted} PTKP rate(s) lama dihapus.");
        }

        // ── 3. Insert PTKP rates UU HPP ──
        $sortOrder = 0;
        foreach (self::PTKP_DATA as $d) {
            PtkpRate::create([
                'pph_config_id' => $configId,
                'status_code'   => $d[0],
                'status_name'   => $d[1],
                'value'         => $d[2],
                'sort_order'    => ++$sortOrder,
                'is_active'     => true,
                'created_by'    => self::DEFAULT_USER_ID,
            ]);
        }

        $this->command->info('✅ PTKP Rate seeder selesai — ' . count(self::PTKP_DATA) . " status untuk pph_config_id={$configId}");

        // Tampilkan tabel ringkasan
        $this->command->info('');
        $this->command->info('┌────────┬──────────────────────────────────┬─────────────────────┐');
        $this->command->info('│ Status │ Keterangan                       │ Nilai (Rp)          │');
        $this->command->info('├────────┼──────────────────────────────────┼─────────────────────┤');
        foreach (self::PTKP_DATA as $d) {
            $this->command->info(sprintf(
                '│ %-6s │ %-32s │ %19s │',
                $d[0],
                $d[1],
                number_format($d[2], 0, ',', '.')
            ));
        }
        $this->command->info('└────────┴──────────────────────────────────┴─────────────────────┘');
        $this->command->info('');
        $this->command->info('📋 Halaman: /admin/payroll/pph → tab "Tarif PTKP"');
    }
}
