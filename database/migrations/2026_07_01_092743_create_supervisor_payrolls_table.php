<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_payrolls', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Relasi
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('att_record_id')->nullable()->constrained('att_records')->nullOnDelete();
            $table->string('segment', 10)->nullable()->comment('A/B/null — penanda split');

            // ── DATA MASUKAN ──
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('premi', 15, 2)->default(0);
            $table->decimal('tj_masa_kerja', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);
            $table->integer('hari_kerja')->default(0);
            $table->decimal('deduct_day', 8, 2)->default(0);
            $table->integer('lm')->default(0)->comment('display only');
            $table->integer('lm_count')->default(0);
            $table->integer('lembur_count')->default(0);

            // ── HASIL HITUNGAN ──
            $table->decimal('gaji', 15, 2)->default(0);
            $table->decimal('upah_lembur', 15, 2)->default(0);
            $table->decimal('premi_hadir', 15, 2)->default(0);
            $table->decimal('revisi', 15, 2)->default(0);
            $table->decimal('gaji_kotor', 15, 2)->default(0);

            // ── POTONGAN ──
            $table->decimal('bpjs_tk', 15, 2)->default(0);
            $table->decimal('bpjs_ks', 15, 2)->default(0);
            $table->decimal('bpjs_pen', 15, 2)->default(0);
            $table->decimal('pph', 15, 2)->default(0);
            $table->decimal('cashbon', 15, 2)->default(0);
            $table->decimal('pot_kehadiran', 15, 2)->default(0);

            // ── PEMBULATAN ──
            $table->decimal('pblt', 15, 2)->default(0);

            // ── GAJI BERSIH ──
            $table->decimal('gaji_bersih', 15, 2)->default(0);

            // ── STATUS ──
            $table->string('status')->default('draft');

            // ── NOTES ──
            $table->text('notes')->nullable();

            // ── AUDIT ──
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'pay_period_id', 'segment'], 'sup_payrolls_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_payrolls');
    }
};
