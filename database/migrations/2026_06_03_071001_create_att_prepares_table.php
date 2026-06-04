<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_prepares', function (Blueprint $table) {
            $table->id();

            // Relasi
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Tanggal & Periode
            $table->date('date');
            $table->date('periode_start');
            $table->date('periode_end');

            // Data absensi
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();

            // Jadwal (snapshot dari roster)
            $table->time('schedule_in')->nullable();
            $table->time('schedule_out')->nullable();

            // Lembur Minggu & Holiday (LM)
            $table->integer('lm')->default(0)->comment('Raw overtime menit (minggu/holiday)');
            $table->integer('lm_count')->default(0)->comment('LM setelah multiplier');

            // Lembur Hari Kerja
            $table->integer('overtime')->default(0)->comment('Raw overtime menit (hari kerja)');
            $table->integer('overtime_count')->default(0)->comment('Overtime setelah multiplier');

            // Keterlambatan
            $table->integer('late_minutes')->default(0);

            // Status
            $table->string('status')->default('absent')->comment('cuti, izin, sakit, absent, hadir, terlambat, libur, off');
            $table->string('review_status')->default('cek')->comment('cek, perhatian, lengkap');

            // Lock
            $table->boolean('is_locked')->default(false);
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['employee_id', 'date'], 'att_prepares_employee_date_unique');
            $table->index('date');
            $table->index('periode_start');
            $table->index('periode_end');
            $table->index('status');
            $table->index('review_status');
            $table->index('is_locked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_prepares');
    }
};
