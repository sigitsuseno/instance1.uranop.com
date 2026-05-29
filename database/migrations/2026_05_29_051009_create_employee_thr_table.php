<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel employee_thr — data THR per karyawan per tahun.
 * Berada di Employee module karena merupakan data derivasi per karyawan,
 * bukan bagian dari proses payroll bulanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_thr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Periode THR
            $table->smallInteger('thr_year');
            $table->string('thr_month', 20)->nullable()->comment('Bulan pencairan, e.g. Mei');

            // Komponen Gaji Dasar
            $table->decimal('gaji_pokok', 15, 2)->default(0);
            $table->decimal('premi', 15, 2)->default(0);
            $table->decimal('tunjangan_masa_kerja', 15, 2)->default(0);

            // Masa Kerja
            $table->date('join_date')->nullable();
            $table->date('reference_date')->nullable()->comment('Tanggal referensi hitung THR (biasanya H-1 lebaran)');
            $table->integer('total_bulan')->default(0)->comment('Total bulan kerja');
            $table->integer('sisa_hari')->default(0)->comment('Sisa hari dalam bulan yang belum genap');
            $table->string('lama_bekerja', 100)->nullable()->comment('Label: "2 tahun 3 bulan"');

            // Hasil Kalkulasi
            $table->decimal('thr_amount', 15, 2)->default(0)->comment('THR sebelum pembulatan');
            $table->decimal('pembulatan', 15, 2)->default(0);
            $table->decimal('total_thr', 15, 2)->default(0)->comment('THR setelah pembulatan');
            $table->decimal('total_terima', 15, 2)->default(0)->comment('Total yang diterima karyawan');

            // Info Pembayaran
            $table->string('no_account', 50)->nullable()->comment('Nomor rekening tujuan');
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->text('catatan')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'thr_year'], 'unique_employee_thr_year');
            $table->index('thr_year');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_thr');
    }
};
