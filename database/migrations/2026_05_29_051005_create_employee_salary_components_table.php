<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_salary_components — komponen gaji detail per karyawan (Aplikasi Utama).
 *
 * Tiap karyawan bisa punya nilai berbeda untuk setiap komponen.
 * Digunakan untuk:
 *   - Accessor: $employee->gaji_pokok(), ->premi(), ->tunjangan_masa_kerja(), dll
 *   - Perhitungan kompensasi kontrak
 *   - Perhitungan THR
 *   - Dasar hitung payroll
 *
 * Catatan: employee_salary_breakdowns (untuk Supervisor Dashboard / Aplikasi Bayangan)
 * akan dikerjakan setelah Fase 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Gaji Pokok
            $table->decimal('gaji_pokok', 15, 2)->default(0);

            // Premi (bonus tetap per periode)
            $table->decimal('premi', 15, 2)->default(0);

            // Tunjangan Masa Kerja (berbeda tiap karyawan sesuai masa kerja)
            $table->decimal('tunjangan_masa_kerja', 15, 2)->default(0);

            // Hari Kerja (untuk acuan perhitungan)
            $table->integer('hari_kerja')->default(0);

            // Lembur Minggu/Holiday
            $table->decimal('lembur_minggu_holiday', 15, 2)->default(0);

            // Lembur Normal
            $table->decimal('lembur', 15, 2)->default(0);

            // Tunjangan Tetap
            $table->decimal('tunjangan', 15, 2)->default(0);

            // Tunjangan Lainnya (non-tetap, insentif, dll)
            $table->decimal('tunjangan_lain', 15, 2)->default(0);

            // Potongan BPJS
            $table->decimal('bpjs_tk', 15, 2)->default(0)->comment('BPJS Ketenagakerjaan (JHT+JP)');
            $table->decimal('bpjs_ks', 15, 2)->default(0)->comment('BPJS Kesehatan');
            $table->decimal('bpjs_pensiun', 15, 2)->default(0)->comment('BPJS Pensiun');

            // Pajak
            $table->decimal('pph', 15, 2)->default(0)->comment('PPh 21');
            $table->decimal('kompensasi_pph', 15, 2)->default(0)->comment('Kompensasi PPh ditanggung perusahaan');

            // Kasbon
            $table->decimal('kasbon', 15, 2)->default(0);

            // Status & Effective Date
            $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable()->comment('Berlaku mulai tanggal ini');
            $table->date('end_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'is_active']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
