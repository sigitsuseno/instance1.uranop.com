<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->date('date')->index();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('work_pattern_id')->nullable();
            $table->time('schedule_in')->nullable()->comment('Jadwal masuk');
            $table->time('schedule_out')->nullable()->comment('Jadwal keluar');
            $table->dateTime('actual_in')->nullable()->comment('Absen masuk aktual');
            $table->dateTime('actual_out')->nullable()->comment('Absen keluar aktual');
            $table->integer('late_minutes')->default(0);
            $table->integer('early_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->string('status', 20)->nullable()->comment('present, late, absent, off, holiday, permit, sick');
            $table->boolean('is_manual')->default(false);
            $table->string('notes')->nullable();
            $table->json('raw_data')->nullable()->comment('Data mentah absensi');
            $table->boolean('is_locked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'date'], 'att_records_employee_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_records');
    }
};
