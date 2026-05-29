<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->string('contract_number', 100)->unique();
            $table->enum('contract_type', ['pkwt', 'pkwtt', 'outsourcing', 'freelance']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration_months')->nullable()->comment('Auto-calc dari start_date & end_date');

            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();

            $table->integer('version')->default(1);
            $table->boolean('is_latest')->default(true)->comment('Flag kontrak terbaru karyawan');

            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft');
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamp('compensation_paid_at')->nullable()->comment('Tanggal kompensasi PKWT dibayar');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status', 'is_latest']);
            $table->index(['start_date', 'end_date']);
            $table->index('contract_type');
            $table->index('expiry_notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
