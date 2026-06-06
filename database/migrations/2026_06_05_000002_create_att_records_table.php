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
        Schema::dropIfExists('att_records');
    }
};
