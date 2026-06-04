<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('late_days')->default(0);
            $table->integer('absent_days')->default(0);
            $table->integer('off_days')->default(0);
            $table->integer('leave_days')->default(0);
            $table->integer('holiday_days')->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->integer('total_late_minutes')->default(0);
            $table->json('summary_data')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'period_start', 'period_end'], 'att_summaries_employee_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_summaries');
    }
};
