<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop tabel lama
        Schema::dropIfExists('supervisor_attendance_snapshots');

        // Buat tabel baru (struktur mirip att_records)
        Schema::create('supervisor_att_snapshot', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Relasi
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->string('segment', 10)->nullable()->comment('A/B/null — penanda split');

            // Ringkasan Hari
            $table->integer('hari_kerja')->default(0);
            $table->decimal('cuti', 8, 2)->default(0);
            $table->decimal('izin', 8, 2)->default(0);
            $table->decimal('sakit', 8, 2)->default(0);
            $table->integer('absen')->default(0);
            $table->decimal('deduct_day', 8, 2)->default(0);

            // Keterlambatan
            $table->integer('late_minutes')->default(0);

            // Lembur & LM
            $table->integer('lm')->default(0);
            $table->integer('lm_count')->default(0);
            $table->integer('lembur')->default(0);
            $table->integer('lembur_count')->default(0);

            // Status
            $table->string('status')->default('draft');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unique: satu karyawan cuma boleh satu record per periode per segment
            $table->unique(['employee_id', 'pay_period_id', 'segment']);
        });
    }

    public function down(): void
    {
        // Rollback: drop tabel baru, buat ulang tabel lama
        Schema::dropIfExists('supervisor_att_snapshot');

        Schema::create('supervisor_attendance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('period_code');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_working_days')->default(0);
            $table->integer('total_present_days')->default(0);
            $table->integer('total_absent_days')->default(0);
            $table->integer('total_late_days')->default(0);
            $table->integer('total_late_minutes')->default(0);
            $table->integer('total_early_leave_minutes')->default(0);
            $table->integer('total_overtime_minutes')->default(0);
            $table->integer('total_holiday_overtime')->default(0);
            $table->json('overtime_breakdown')->nullable();
            $table->integer('total_leave_days')->default(0);
            $table->integer('total_unpaid_days')->default(0);
            $table->integer('total_sick_days')->default(0);
            $table->integer('total_permit_days')->default(0);
            $table->json('snapshot')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->unsignedBigInteger('payroll_period_id')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
