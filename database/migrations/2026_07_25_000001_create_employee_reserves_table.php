<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_reserves — data insentif / bonus untuk sopir atau karyawan lain.
 * Kolom komponen (JSON) menyimpan rincian insentif yang diterima per periode payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_reserves', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relasi
            $table->foreignId('pay_periode_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Data Insentif
            $table->json('komponen')->comment('Rincian insentif: [{nama, nilai, keterangan}]');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pay_periode_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_reserves');
    }
};
