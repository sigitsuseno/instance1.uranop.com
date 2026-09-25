<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_working_day_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relasi
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Pengaturan khusus: hari_kerja menimpa hasil hitung otomatis saat finalisasi.
            // deduct_day/pot_kehadiran diturunkan saat finalisasi: max(0, hk_segmen - hari_kerja).
            $table->integer('hari_kerja')->default(0);

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Satu pengaturan per karyawan per periode
            $table->unique(['employee_id', 'pay_period_id'], 'pay_working_day_overrides_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_working_day_overrides');
    }
};
