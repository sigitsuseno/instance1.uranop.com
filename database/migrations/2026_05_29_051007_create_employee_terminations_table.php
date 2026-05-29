<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_terminations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->enum('termination_type', [
                'resign', 'retirement', 'fired', 'contract_end', 'death', 'other',
            ]);
            $table->date('termination_date');
            $table->date('effective_date')->comment('Tanggal efektif keluar');
            $table->text('reason')->nullable();

            $table->decimal('settlement_amount', 15, 2)->nullable()->comment('Uang pisah/kompensasi');
            $table->text('settlement_notes')->nullable();
            $table->boolean('is_eligible_for_rehire')->default(false);

            // Exit Clearance
            $table->boolean('clearance_asset')->default(false)->comment('Pengembalian aset');
            $table->boolean('clearance_finance')->default(false)->comment('Keuangan bersih');
            $table->boolean('clearance_it')->default(false)->comment('Akun IT dinonaktifkan');
            $table->text('clearance_notes')->nullable();

            // Approval
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->string('document_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'termination_date']);
            $table->index(['termination_type', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_terminations');
    }
};
