<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_calculator_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name', 100);
            $table->foreignId('work_pattern_id')
                ->nullable()
                ->constrained('sch_work_patterns')
                ->nullOnDelete()
                ->comment('NULL = global, ID = override spesifik');

            // Jam kerja normal per tipe hari
            $table->integer('normal_work_minutes')->default(480)->comment('Jam normal weekday (menit)');
            $table->integer('saturday_work_minutes')->default(360)->comment('Jam normal Sabtu (menit)');

            // Batas / flat
            $table->integer('holiday_max_minutes')->default(480)->comment('Max OT holiday/Minggu');
            $table->integer('shift_saturday_flat')->default(120)->comment('Flat OT SHIFT Sabtu');

            // Perilaku keterlambatan
            $table->boolean('late_deducts_overtime')->default(false)->comment('Telat mengurangi lembur?');
            $table->integer('late_tolerance')->default(0)->comment('Toleransi telat (menit)');

            // Potongan & rounding
            $table->integer('lm_rest_deduction')->default(60)->comment('Potongan istirahat LM');
            $table->integer('rounding_interval')->default(30)->comment('Interval pembulatan (menit)');
            $table->integer('rounding_threshold')->default(5)->comment('Threshold pembulatan');

            // Upah
            $table->integer('hourly_divisor')->default(173)->comment('Pembagi upah/jam');

            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_calculator_configs');
    }
};
