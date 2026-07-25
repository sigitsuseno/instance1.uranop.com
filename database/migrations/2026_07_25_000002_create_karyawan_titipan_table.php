<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel karyawan_titipan — daftar karyawan titipan (borongan/subkon).
 * Digunakan untuk mencatat karyawan luar yang ditempatkan di perusahaan
 * dalam periode tertentu dengan status aktif/nonaktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan_titipan', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Data Karyawan
            $table->string('nama', 200);
            $table->string('employee_code', 50)->nullable()->unique();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->json('component')->nullable()->comment('Komponen data tambahan (JSON)');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_titipan');
    }
};
