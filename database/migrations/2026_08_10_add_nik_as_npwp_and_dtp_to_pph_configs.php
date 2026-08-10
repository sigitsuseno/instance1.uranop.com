<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pph_configs', function (Blueprint $table) {
            $table->boolean('nik_as_npwp')->default(true)->after('non_npwp_multiplier')
                ->comment('Jika ON, NIK otomatis dianggap NPWP (semua karyawan wajib pajak)');
            $table->boolean('is_dtp')->default(false)->after('nik_as_npwp')
                ->comment('Jika ON, PPh 21 Ditanggung Pemerintah secara global');
        });
    }

    public function down(): void
    {
        Schema::table('pph_configs', function (Blueprint $table) {
            $table->dropColumn(['nik_as_npwp', 'is_dtp']);
        });
    }
};
