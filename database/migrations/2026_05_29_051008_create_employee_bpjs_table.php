<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_bpjs — konfigurasi & data kepesertaan BPJS per karyawan.
 * pay_period_id nullable — akan diisi saat generate payroll (Fase 8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bpjs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pay_period_id')->nullable()->constrained('pay_periods')->nullOnDelete()
                ->comment('Diisi saat generate payroll, null jika belum pernah di-generate');

            // Nomor Kepesertaan
            $table->string('bpjs_ketenagakerjaan_no', 50)->nullable();
            $table->string('bpjs_kesehatan_no', 50)->nullable();

            // Konfigurasi Dasar Gaji BPJS
            $table->enum('bpjs_base_type', ['gaji_pokok', 'umk', 'custom'])->default('gaji_pokok');

            // Setting Iuran
            $table->enum('jht_setting', ['ditanggung_perusahaan', 'potong_gaji', 'tidak_ikut'])->default('tidak_ikut');
            $table->enum('jp_setting', ['ditanggung_perusahaan', 'potong_gaji', 'tidak_ikut'])->default('tidak_ikut');
            $table->enum('jkk_setting', ['ditanggung_perusahaan', 'tidak_ikut'])->default('tidak_ikut');
            $table->enum('jkm_setting', ['ditanggung_perusahaan', 'tidak_ikut'])->default('tidak_ikut');
            $table->enum('kesehatan_setting', ['ditanggung_perusahaan', 'potong_gaji', 'tidak_ikut'])->default('tidak_ikut');
            $table->integer('kesehatan_dependents')->default(0)->comment('Jumlah tanggungan BPJS Kesehatan');

            // Status Kepesertaan
            $table->enum('status_ketenagakerjaan', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('status_kesehatan', ['active', 'inactive', 'suspended'])->default('active');

            // Tanggal Kepesertaan
            $table->date('date_joined_ketenagakerjaan')->nullable();
            $table->date('date_joined_kesehatan')->nullable();
            $table->date('date_left_ketenagakerjaan')->nullable();
            $table->date('date_left_kesehatan')->nullable();

            // Kelas & Faskes
            $table->enum('bpjs_kesehatan_class', ['Kelas I', 'Kelas II', 'Kelas III'])->nullable();
            $table->string('faskes_tingkat_1', 100)->nullable();
            $table->string('faskes_tingkat_1_code', 20)->nullable();

            // Nominal Potongan Karyawan (dipotong dari gaji)
            $table->decimal('potongan_jht', 15, 2)->default(0);
            $table->decimal('potongan_jp', 15, 2)->default(0);
            $table->decimal('potongan_kesehatan', 15, 2)->default(0);

            // Nominal Tanggungan Perusahaan
            $table->decimal('tanggungan_jht', 15, 2)->default(0);
            $table->decimal('tanggungan_jp', 15, 2)->default(0);
            $table->decimal('tanggungan_jkk', 15, 2)->default(0);
            $table->decimal('tanggungan_jkm', 15, 2)->default(0);
            $table->decimal('tanggungan_kesehatan', 15, 2)->default(0);

            // Dasar Gaji BPJS Terakhir Digunakan
            $table->decimal('bpjs_base_salary', 15, 2)->default(0);
            $table->timestamp('last_generated_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('bpjs_ketenagakerjaan_no');
            $table->index('bpjs_kesehatan_no');
            $table->index('status_ketenagakerjaan');
            $table->index('status_kesehatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bpjs');
    }
};
