<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('report_type', 50)->unique()->comment('Slug unik per laporan: lembur_uang_makan, absensi, bpjs, dll');
            $table->json('employee_groups')->nullable()->comment('Array reference_code group karyawan yg ditampilkan');
            $table->json('config')->nullable()->comment('Pengaturan spesifik per laporan (rate, bracket, toleransi, dll)');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_configs');
    }
};
