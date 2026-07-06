<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel extra_employees — karyawan titipan (EXTRA EMP).
 * Digunakan untuk mencatat karyawan di luar daftar utama
 * yang tetap masuk dalam perhitungan penggajian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nama', 200);
            $table->string('kode', 50)->nullable()->unique();
            $table->json('komponen_gaji')->nullable()->comment(
                'JSON: gaji_pokok, premi, tj_mk, tunjangan, ttl_bpjs, ttl_pph, total_gaji, cashbon, total_terima'
            );
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_employees');
    }
};
