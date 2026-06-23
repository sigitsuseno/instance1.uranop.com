<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_autologs', function (Blueprint $table) {
            $table->id();

            // Instance context (nullable — tidak difilter di multi-instance)
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();

            // Relasi ke employee dan roster
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_shift_roster_id')->nullable();

            // Data attendance
            $table->date('date');
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->timestamp('actual_in')->nullable();
            $table->timestamp('actual_out')->nullable();

            // Tracking log sources (nullable — raw_logs mungkin belum ada)
            $table->unsignedBigInteger('check_in_log_id')->nullable();
            $table->unsignedBigInteger('check_out_log_id')->nullable();
            $table->string('import_batch')->nullable()->index();

            // Status dan perhitungan
            $table->string('status')->default('pending');
            $table->integer('late_duration')->default(0); // menit
            $table->integer('early_leave_duration')->default(0); // menit
            $table->integer('overtime_duration')->default(0); // menit
            $table->decimal('overtime_converted_hours', 5, 2)->nullable();
            $table->integer('izin_duration')->default(0);
            $table->integer('sakit_duration')->default(0);
            $table->integer('holiday_overtime')->default(0);
            $table->integer('deduct_attendance')->default(0); // menit

            // Flags dari roster (snapshot saat sync)
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_sat')->default(false);
            $table->boolean('is_sun')->default(false);
            $table->boolean('is_half_day')->default(false);
            $table->boolean('is_leave')->default(false);
            $table->integer('deduct_day')->nullable();
            $table->unsignedBigInteger('leave_id')->nullable();

            // Manual edit tracking
            $table->boolean('is_manual_edit')->default(false);
            $table->timestamp('last_edited_at')->nullable();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();

            // Lock untuk payroll
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            // Notes
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->integer('scan_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['employee_id', 'date'], 'attendance_autologs_employee_date_unique');
            $table->index(['company_id', 'branch_id', 'date']);
            $table->index(['employee_id', 'status']);
            $table->index('date');
            $table->index('is_locked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_autologs');
    }
};
