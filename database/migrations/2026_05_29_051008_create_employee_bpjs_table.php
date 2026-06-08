<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data kepesertaan & iuran BPJS per karyawan.
 * Satu employee = satu record (unique employee_id).
 * pay_period_id diisi saat generate per periode payroll.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_bpjs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pay_period_id')->nullable()->constrained('pay_periods')->nullOnDelete()
                ->comment('Diisi saat generate iuran per periode');

            // === Nomor Kepesertaan ===
            $table->string('bpjs_ketenagakerjaan_no', 50)->nullable();
            $table->string('bpjs_kesehatan_no', 50)->nullable();

            // === Checkbox Keanggotaan ===
            $table->boolean('has_bpjs_tk')->default(false)->comment('Ikut BPJS Ketenagakerjaan');
            $table->boolean('has_bpjs_ks')->default(false)->comment('Ikut BPJS Kesehatan');
            $table->boolean('has_bpjs_pen')->default(false)->comment('Ikut BPJS Pensiun');

            // === Dasar Kalkulasi ===
            $table->enum('bpjs_base_type', ['gaji_pokok', 'umk', 'custom'])->default('gaji_pokok');
            $table->decimal('bpjs_base_salary', 15, 2)->default(0);
            $table->decimal('tj_masa_kerja', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);

            // === 5 Porsi Perusahaan (Employer) ===
            $table->decimal('employer_jht', 15, 2)->default(0);
            $table->decimal('employer_jkk', 15, 2)->default(0);
            $table->decimal('employer_jkm', 15, 2)->default(0);
            $table->decimal('employer_kesehatan', 15, 2)->default(0);
            $table->decimal('employer_jp', 15, 2)->default(0);

            // === 3 Porsi Karyawan (Employee) ===
            $table->decimal('employee_jht', 15, 2)->default(0);
            $table->decimal('employee_kesehatan', 15, 2)->default(0);
            $table->decimal('employee_jp', 15, 2)->default(0);

            // === Status Kepesertaan ===
            $table->enum('status_ketenagakerjaan', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('status_kesehatan', ['active', 'inactive', 'suspended'])->default('active');

            // === Tanggal ===
            $table->date('date_joined_ketenagakerjaan')->nullable();
            $table->date('date_joined_kesehatan')->nullable();
            $table->date('date_left_ketenagakerjaan')->nullable();
            $table->date('date_left_kesehatan')->nullable();

            // === Detail Kesehatan ===
            $table->enum('bpjs_kesehatan_class', ['Kelas I', 'Kelas II', 'Kelas III'])->nullable();
            $table->integer('kesehatan_dependents')->default(0)->comment('Jml tanggungan (+1% per org)');
            $table->string('faskes_tingkat_1', 100)->nullable();
            $table->string('faskes_tingkat_1_code', 20)->nullable();

            $table->timestamp('last_generated_at')->nullable();
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
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
