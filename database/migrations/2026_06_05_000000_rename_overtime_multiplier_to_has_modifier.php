<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename overtime_multiplier (decimal) → has_modifier (boolean).
     * Konsep: bukan lagi menyimpan nilai multiplier, tapi flag apakah shift ini
     * punya modifier/perlakuan khusus dalam perhitungan lembur.
     */
    public function up(): void
    {
        Schema::table('sch_shifts', function (Blueprint $table) {
            // Drop kolom lama (decimal)
            $table->dropColumn('overtime_multiplier');
        });

        Schema::table('sch_shifts', function (Blueprint $table) {
            // Tambah kolom baru (boolean, default false)
            $table->boolean('has_modifier')->default(false)->after('has_overtime');
        });
    }

    public function down(): void
    {
        Schema::table('sch_shifts', function (Blueprint $table) {
            $table->dropColumn('has_modifier');
        });

        Schema::table('sch_shifts', function (Blueprint $table) {
            $table->decimal('overtime_multiplier', 5, 2)->nullable()->after('has_overtime');
        });
    }
};
