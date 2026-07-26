<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_overtime — data lembur per karyawan per periode.
 * Kolom komponen (JSON) menyimpan rincian perhitungan lembur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_overtime', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relasi
            $table->foreignId('autolog_id')->constrained('attendance_autologs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pay_periode_id')->nullable()->constrained('pay_periods')->nullOnDelete();

            // Data Lembur
            $table->decimal('lembur', 8, 2)->default(0)->comment('Total jam lembur');
            $table->decimal('lembur_hitung', 12, 2)->default(0)->comment('Nilai perhitungan lembur');

            // Uang Makan
            $table->string('um_code')->nullable()->comment('Kode uang makan');
            $table->decimal('nominal', 12, 2)->default(0)->comment('Nominal uang makan');

            // Insentif & Komponen
            $table->decimal('insentif', 12, 2)->default(0)->comment('Nilai insentif');
            $table->json('komponen')->nullable()->comment('Rincian komponen lembur: [{nama, nilai, keterangan}]');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pay_periode_id', 'employee_id']);
            $table->index('autolog_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_overtime');
    }
};
