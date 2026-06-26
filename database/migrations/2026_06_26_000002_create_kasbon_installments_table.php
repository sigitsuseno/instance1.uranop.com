<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasbon_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kasbon_request_id')->constrained('kasbon_requests')->onDelete('cascade');
            $table->foreignId('pay_period_id')->nullable()->constrained('pay_periods');
            $table->integer('installment_number')->comment('Cicilan ke-1, 2, 3, ...');
            $table->decimal('amount', 15, 2)->comment('Nominal cicilan');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasbon_installments');
    }
};
