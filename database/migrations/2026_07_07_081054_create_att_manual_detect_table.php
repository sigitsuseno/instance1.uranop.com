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
        Schema::create('att_manual_detect', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->unsignedBigInteger('roster_id')->nullable();
            $table->unsignedBigInteger('work_pattern_id')->nullable();
            $table->string('work_pattern_type', 30)->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->json('time_scan_result')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'uq_att_md_employee_date');
            $table->index('roster_id', 'idx_att_md_roster');
            $table->index('status', 'idx_att_md_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('att_manual_detect');
    }
};
