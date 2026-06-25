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
        Schema::create('supervisor_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->unsignedBigInteger('employee_shift_roster_id')->nullable();
            $table->date('date');
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->unsignedBigInteger('check_in_log_id')->nullable();
            $table->unsignedBigInteger('check_out_log_id')->nullable();
            $table->string('import_batch')->nullable();
            $table->string('status')->default('pending');
            $table->integer('late_duration')->default(0);
            $table->integer('early_leave_duration')->default(0);
            $table->integer('lembur')->default(0); // menit (overtime biasa)
            $table->decimal('lembur_calc', 8, 2)->nullable();
            $table->integer('lm')->default(0); // menit (lembur minggu & holiday)
            $table->decimal('lm_calc', 8, 2)->nullable();
            $table->integer('deduct_attendance')->default(0);
            $table->time('actual_in')->nullable();
            $table->time('actual_out')->nullable();
            $table->boolean('is_half_day')->default(false);
            $table->integer('holiday_overtime')->default(0);
            $table->boolean('is_sun')->default(false);
            $table->boolean('is_sat')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_manual_edit')->default(false);
            $table->dateTime('last_edited_at')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->dateTime('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('scan_count')->default(0);
            $table->boolean('is_leave')->default(false);
            $table->unsignedBigInteger('leave_id')->nullable();
            $table->integer('deduct_day')->nullable();
            $table->integer('izin_duration')->default(0);
            $table->integer('sakit_duration')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supervisor_attendances');
    }
};
