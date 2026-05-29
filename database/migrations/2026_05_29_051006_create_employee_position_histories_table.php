<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_position_histories — riwayat jabatan/mutasi karyawan.
 * Semua FK old_* dibuat nullable dari awal untuk handle kasus 'initial' (tidak ada posisi lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_position_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Posisi Lama — nullable (initial tidak punya posisi lama)
            $table->foreignId('old_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('old_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('old_salary_grade_id')->nullable()->constrained('salary_grades')->nullOnDelete();
            $table->decimal('old_salary', 15, 2)->nullable();

            // Posisi Baru — nullable (bisa jadi hanya perubahan dept saja)
            $table->foreignId('new_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('new_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('new_salary_grade_id')->nullable()->constrained('salary_grades')->nullOnDelete();
            $table->decimal('new_salary', 15, 2)->nullable();

            $table->date('effective_date');
            $table->enum('change_reason', [
                'initial', 'promotion', 'demotion',
                'transfer', 'rotation', 'upgrade', 'restructuring',
            ])->nullable();

            $table->text('notes')->nullable();
            $table->string('document_path')->nullable()->comment('SK Kenaikan Pangkat, dll');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'effective_date']);
            $table->index('change_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_position_histories');
    }
};
