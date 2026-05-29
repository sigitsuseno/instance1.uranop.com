<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->enum('document_type', [
                'ktp', 'kk', 'npwp', 'bpjs',
                'ijazah', 'transkrip', 'sertifikat',
                'kontrak', 'sk', 'other',
            ]);
            $table->string('document_number', 100)->nullable()->comment('No KTP, No NPWP, dll');
            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->string('file_path');
            $table->string('file_name', 200);
            $table->string('file_size', 50)->nullable()->comment('Dalam bytes');
            $table->string('mime_type', 100)->nullable();

            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issued_by', 200)->nullable()->comment('Instansi penerbit');

            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'document_type']);
            $table->index(['expiry_date', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
