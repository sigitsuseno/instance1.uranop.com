<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom origin_join_date — tanggal masuk asal karyawan, dipertahankan dari
     * sistem lama (shadow app) / sebelum migrasi data. Beda dengan join_date
     * yang merupakan tanggal masuk efektif di sistem saat ini.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('origin_join_date')->nullable()->after('join_date')->comment('Tanggal masuk asal (dari sistem lama / sebelum migrasi)');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('origin_join_date');
        });
    }
};