<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_pph', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // 1. Relasi & Identitas
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('pay_period_id')->constrained('pay_periods')->cascadeOnDelete();
            $table->foreignId('pay_record_id')->nullable()->constrained('pay_records')->nullOnDelete();
            
            $table->string('npwp', 50)->nullable();
            $table->boolean('has_npwp')->default(false);
            $table->string('ptkp_status', 10)->nullable()->comment('Contoh: TK/0');

            // 2. Komponen Penghasilan Bruto (Penambah)
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('tunjangan', 15, 2)->default(0);
            $table->decimal('lembur_bonus_thr', 15, 2)->default(0);
            $table->decimal('bpjs_jkk_perusahaan', 15, 2)->default(0);
            $table->decimal('bpjs_jkm_perusahaan', 15, 2)->default(0);
            $table->decimal('bpjs_kes_perusahaan', 15, 2)->default(0);
            $table->decimal('gross_income', 15, 2)->default(0)->comment('Total Penghasilan Bruto PPh 21');

            // 3. Komponen Pengurang (Deductions)
            $table->decimal('biaya_jabatan', 15, 2)->default(0);
            $table->decimal('bpjs_jht_karyawan', 15, 2)->default(0);
            $table->decimal('bpjs_jp_karyawan', 15, 2)->default(0);
            $table->decimal('total_pengurang', 15, 2)->default(0);

            // 4. Detail Perhitungan Pajak
            $table->string('calculation_method', 20)->default('ter')->comment('ter / progressive');
            $table->string('pph_method', 20)->default('gross')->comment('gross / gross_up / net');
            
            $table->decimal('netto_income', 15, 2)->default(0)->comment('Penghasilan Netto Sebulan');
            $table->decimal('annualized_income', 15, 2)->default(0)->comment('Penghasilan Netto Disetahunkan');
            $table->decimal('pkp', 15, 2)->default(0)->comment('Penghasilan Kena Pajak');
            
            $table->decimal('pph_rate', 15, 4)->default(0)->comment('Persentase tarif (misal 0.25 untuk TER 0.25%)');
            $table->decimal('pph_amount', 15, 2)->default(0)->comment('Pajak yang seharusnya dibayar/dilaporkan');
            $table->decimal('pph_deducted', 15, 2)->default(0)->comment('Pajak yang dipotong dari slip gaji');
            $table->boolean('is_dtp')->default(false)->comment('Ditanggung Pemerintah');

            // 5. Kolom Rekap Desember (Tahunan)
            $table->boolean('is_december_calc')->default(false)->comment('Apakah ini perhitungan akhir tahun');
            $table->decimal('pph_paid_until_nov', 15, 2)->default(0)->comment('PPh yang sudah disetor Jan-Nov');

            // 6. Audit & Status
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexing for performance
            $table->index(['employee_id', 'pay_period_id']);
            $table->index(['pay_period_id', 'is_december_calc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_pph');
    }
};
