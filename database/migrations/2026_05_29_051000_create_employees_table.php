<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employees — master data karyawan.
 * Single-instance: tanpa company_id / branch_id.
 * Denormalized cache (base_salary, premi, tunjangan) diupdate via EmployeeService::syncDenormalized()
 * untuk performa list. Data sesungguhnya ada di employee_salary_components.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relasi
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();

            // Kode dan Identitas
            $table->string('employee_code')->unique()->comment('Kode Karyawan / NIP Internal');
            $table->string('nip')->nullable()->unique()->comment('Nomor Induk Pegawai');
            $table->string('nik', 16)->nullable()->unique()->comment('NIK KTP');
            $table->string('name', 200);
            $table->string('photo', 255)->nullable();
            $table->enum('gender', ['L', 'P']);
            $table->string('place_of_birth', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('blood_type', 5)->nullable();

            // Kontak & Alamat
            $table->string('email', 200)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 20)->nullable();

            // Data Pajak & BPJS
            $table->string('npwp', 50)->nullable();
            $table->string('bpjs_ketenagakerjaan', 50)->nullable();
            $table->string('bpjs_kesehatan', 50)->nullable();
            $table->boolean('has_npwp')->default(false);
            $table->string('ptkp', 10)->nullable()->comment('Status PTKP: TK/0, K/1, dll');

            // Status Karyawan
            $table->enum('employment_status', [
                'probation', 'contract', 'permanent',
                'outsource', 'freelance', 'resigned', 'terminated',
            ]);
            $table->enum('payroll_cycle', ['monthly', 'weekly', 'daily'])->default('monthly');

            // Tanggal Penting
            $table->date('join_date');
            $table->date('end_date')->nullable();
            $table->date('permanent_date')->nullable();
            $table->date('resign_date')->nullable();

            // Data Bank
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_account_name', 200)->nullable();

            // Denormalized Cache — diupdate via syncDenormalized() saat salary berubah
            // Data otoritatif ada di employee_salary_components
            $table->decimal('base_salary', 15, 2)->default(0)->comment('Cache dari employee_salary_components.gaji_pokok');
            $table->decimal('premi', 15, 2)->default(0)->comment('Cache dari employee_salary_components.premi');
            $table->decimal('tunjangan', 15, 2)->default(0)->comment('Cache dari employee_salary_components.tunjangan');

            // Status & Audit
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable()->comment('Terakhir sync dari shadow app');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['department_id', 'position_id']);
            $table->index(['is_active', 'employment_status']);
            $table->index('join_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
