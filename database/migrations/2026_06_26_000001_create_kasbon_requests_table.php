<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasbon_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->decimal('amount', 15, 2)->comment('Nominal pengajuan kasbon');
            $table->integer('tenor')->default(1)->comment('Jumlah bulan cicilan');
            $table->text('reason')->nullable()->comment('Alasan pengajuan');
            $table->enum('status', ['pending', 'approved', 'rejected', 'disbursed', 'completed'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable()->comment('Tanggal pencairan');
            $table->decimal('remaining_amount', 15, 2)->default(0)->comment('Sisa yang belum dilunasi');
            $table->text('notes')->nullable()->comment('Catatan internal');
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasbon_requests');
    }
};
