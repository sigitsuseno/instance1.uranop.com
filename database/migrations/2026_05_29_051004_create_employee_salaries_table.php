<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_salaries — riwayat gaji karyawan dengan effective date.
 * Digunakan untuk audit trail kenaikan gaji.
 * Data operasional (premi, tunjangan masa kerja, dll) ada di employee_salary_components.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->decimal('base_salary', 15, 2)->default(0)->comment('Gaji pokok');
            $table->decimal('premi', 15, 2)->default(0)->comment('Premi/bonus tetap');
            $table->decimal('tunjangan', 15, 2)->default(0)->comment('Tunjangan tetap lainnya');
            $table->decimal('previous_basic_salary', 15, 2)->default(0)->comment('Gaji pokok sebelumnya');
            $table->decimal('allowance_transport', 15, 2)->default(0);
            $table->decimal('allowance_meal', 15, 2)->default(0);
            $table->decimal('allowance_position', 15, 2)->default(0);

            $table->date('effective_date')->nullable()->comment('Berlaku mulai tanggal ini');
            $table->date('end_date')->nullable()->comment('Berlaku sampai tanggal ini');
            $table->string('change_type')->nullable()->comment('initial|increase|decrease|promotion|adjustment');
            $table->string('letter_number')->nullable()->comment('Nomor SK/Surat');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['employee_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
