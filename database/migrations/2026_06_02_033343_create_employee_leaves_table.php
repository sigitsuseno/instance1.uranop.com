<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->foreignId('leave_period_id')->constrained('leave_periods');
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->enum('transaction_type', ['increment', 'decrement'])->default('increment');
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->unique(
                ['employee_id', 'leave_type_id', 'leave_period_id', 'reference_id', 'transaction_type'],
                'uq_employee_leave_period_type'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
    }
};
