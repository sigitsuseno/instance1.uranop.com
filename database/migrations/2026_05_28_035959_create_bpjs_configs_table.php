<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi persentase BPJS.
 * Versioned by effective_date — satu versi aktif per waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);

            // JHT (Jaminan Hari Tua)
            $table->decimal('jht_employer', 5, 2)->default(3.70);
            $table->decimal('jht_employee', 5, 2)->default(2.00);

            // JKK (Jaminan Kecelakaan Kerja) — employer only
            $table->decimal('jkk', 5, 2)->default(0.24);

            // JKM (Jaminan Kematian) — employer only
            $table->decimal('jkm', 5, 2)->default(0.30);

            // JP (Jaminan Pensiun)
            $table->decimal('jp_employer', 5, 2)->default(2.00);
            $table->decimal('jp_employee', 5, 2)->default(1.00);

            // BPJS Kesehatan
            $table->decimal('kesehatan_employer', 5, 2)->default(4.00);
            $table->decimal('kesehatan_employee', 5, 2)->default(1.00);

            // Batas maksimal upah (single cap untuk semua program)
            $table->decimal('max_wage_cap', 15, 2)->default(12000000);

            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['effective_date', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_configs');
    }
};
