<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_attendance_snapshots');
    }
};
