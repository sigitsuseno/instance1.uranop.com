<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel supervisor_breakdowns — data breakdown gaji untuk halaman Supervisor Payroll.
 *
 * Mirip dengan pay_records / supervisor_payrolls, tapi khusus menyimpan
 * hasil sinkronisasi dari pay_records ke view supervisor.
 * Satu record = satu karyawan × satu periode × satu segment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // ── RELASI ──
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pay_record_id')->nullable()->constrained('pay_records')->nullOnDelete()
                ->comment('Referensi ke sumber data di pay_records');
            $table->string('segment', 10)->nullable()->comment('A/B/null — penanda split periode');

            // ── GROUP / SECTION ──
            $table->string('section', 10)->nullable()->comment('A/B — hasil mapping group karyawan');
            $table->json('group_codes')->nullable()->comment('Reference code group karyawan (cache)');

            // ── DATA KARYAWAN (DENORMALIZED) ──
            $table->string('employee_code', 50)->nullable();
            $table->string('employee_name')->nullable();
            $table->string('gender', 1)->nullable();
            $table->string('department_name')->nullable();
            $table->string('position_name')->nullable();
            $table->date('join_date')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name')->nullable();

            // ── DATA MASUKAN ──
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('premi', 15, 2)->default(0);
            $table->decimal('tj_masa_kerja', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);
            $table->integer('hari_kerja')->default(0);
            $table->decimal('deduct_day', 8, 2)->default(0);
            $table->integer('lm')->default(0)->comment('Lembur Minggu/Holiday (menit)');
            $table->integer('lm_count')->default(0);
            $table->integer('lembur_count')->default(0)->comment('Lembur biasa (menit)');

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
            $table->string('status')->default('draft')->comment('draft/synced/locked');
            $table->text('notes')->nullable();

            // ── AUDIT ──
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── INDEXES ──
            $table->unique(['employee_id', 'pay_period_id', 'segment'], 'sup_bdowns_unique');
            $table->index('pay_period_id');
            $table->index('employee_id');
            $table->index('section');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_breakdowns');
    }
};
