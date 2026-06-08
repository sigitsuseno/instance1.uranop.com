<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure employee_bpjs:
 * - Drop old *_setting enum columns → replace with 3 checkbox (has_bpjs_tk/ks/pen)
 * - Rename potongan_* → employee_*
 * - Rename tanggungan_* → employer_*
 * - Add tj_masa_kerja, tunjangan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_bpjs', function (Blueprint $table) {
            // 1. Drop old *_setting enum columns
            $columnsToDrop = ['jht_setting', 'jp_setting', 'jkk_setting', 'jkm_setting', 'kesehatan_setting'];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('employee_bpjs', $col)) {
                    $table->dropColumn($col);
                }
            }

            // 2. Add checkbox columns
            if (! Schema::hasColumn('employee_bpjs', 'has_bpjs_tk')) {
                $table->boolean('has_bpjs_tk')->default(false)->comment('Ikut BPJS Ketenagakerjaan');
            }
            if (! Schema::hasColumn('employee_bpjs', 'has_bpjs_ks')) {
                $table->boolean('has_bpjs_ks')->default(false)->comment('Ikut BPJS Kesehatan');
            }
            if (! Schema::hasColumn('employee_bpjs', 'has_bpjs_pen')) {
                $table->boolean('has_bpjs_pen')->default(false)->comment('Ikut BPJS Pensiun');
            }

            // 3. Add tj_masa_kerja & tunjangan
            if (! Schema::hasColumn('employee_bpjs', 'tj_masa_kerja')) {
                $table->decimal('tj_masa_kerja', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('employee_bpjs', 'tunjangan')) {
                $table->decimal('tunjangan', 15, 2)->default(0);
            }

            // 4. Rename potongan_* → employee_*
            $this->renameIfExists('employee_bpjs', 'potongan_jht', 'employee_jht');
            $this->renameIfExists('employee_bpjs', 'potongan_jp', 'employee_jp');
            $this->renameIfExists('employee_bpjs', 'potongan_kesehatan', 'employee_kesehatan');

            // 5. Rename tanggungan_* → employer_*
            $this->renameIfExists('employee_bpjs', 'tanggungan_jht', 'employer_jht');
            $this->renameIfExists('employee_bpjs', 'tanggungan_jkk', 'employer_jkk');
            $this->renameIfExists('employee_bpjs', 'tanggungan_jkm', 'employer_jkm');
            $this->renameIfExists('employee_bpjs', 'tanggungan_kesehatan', 'employer_kesehatan');
            $this->renameIfExists('employee_bpjs', 'tanggungan_jp', 'employer_jp');
        });
    }

    public function down(): void
    {
        Schema::table('employee_bpjs', function (Blueprint $table) {
            // Drop checkbox columns
            foreach (['has_bpjs_tk', 'has_bpjs_ks', 'has_bpjs_pen', 'tj_masa_kerja', 'tunjangan'] as $col) {
                if (Schema::hasColumn('employee_bpjs', $col)) {
                    $table->dropColumn($col);
                }
            }

            // Restore old setting enum columns
            $settings = [
                'jht_setting'        => "enum('ditanggung_perusahaan','potong_gaji','tidak_ikut')",
                'jp_setting'         => "enum('ditanggung_perusahaan','potong_gaji','tidak_ikut')",
                'jkk_setting'        => "enum('ditanggung_perusahaan','tidak_ikut')",
                'jkm_setting'        => "enum('ditanggung_perusahaan','tidak_ikut')",
                'kesehatan_setting'  => "enum('ditanggung_perusahaan','potong_gaji','tidak_ikut')",
            ];
            foreach ($settings as $col => $def) {
                if (! Schema::hasColumn('employee_bpjs', $col)) {
                    DB::statement("ALTER TABLE employee_bpjs ADD COLUMN `{$col}` {$def} DEFAULT 'tidak_ikut'");
                }
            }

            // Reverse renames
            $this->renameIfExists('employee_bpjs', 'employee_jht', 'potongan_jht');
            $this->renameIfExists('employee_bpjs', 'employee_jp', 'potongan_jp');
            $this->renameIfExists('employee_bpjs', 'employee_kesehatan', 'potongan_kesehatan');
            $this->renameIfExists('employee_bpjs', 'employer_jht', 'tanggungan_jht');
            $this->renameIfExists('employee_bpjs', 'employer_jkk', 'tanggungan_jkk');
            $this->renameIfExists('employee_bpjs', 'employer_jkm', 'tanggungan_jkm');
            $this->renameIfExists('employee_bpjs', 'employer_kesehatan', 'tanggungan_kesehatan');
            $this->renameIfExists('employee_bpjs', 'employer_jp', 'tanggungan_jp');
        });
    }

    private function renameIfExists(string $table, string $from, string $to): void
    {
        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, fn (Blueprint $t) => $t->renameColumn($from, $to));
        }
    }
};
