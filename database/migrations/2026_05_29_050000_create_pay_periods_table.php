<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pay_periods — periode payroll.
 * Dibuat di Fase 4 sebagai prerequisite untuk employee_bpjs FK.
 * Akan di-expand lebih detail di Fase 8 (Payroll).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name'); // e.g. "Mei 2026"
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');
            $table->enum('status', ['draft', 'processing', 'locked', 'closed'])->default('draft');
            $table->date('started_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['period_year', 'period_month']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_periods');
    }
};
