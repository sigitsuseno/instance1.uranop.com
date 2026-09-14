<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom tambahan untuk penandatangan dokumen (cetak Perjanjian Kerja):
     * nama_pimpinan = nama yang tercetak di bawah tanda tangan,
     * ttd_pimpinan  = path gambar tanda tangan (disk public).
     * Kolom pic_name sengaja tidak diubah — masih dipakai cetakan kompensasi.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('nama_pimpinan')->nullable()->after('pic_name');
            $table->string('ttd_pimpinan')->nullable()->after('nama_pimpinan');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['nama_pimpinan', 'ttd_pimpinan']);
        });
    }
};
