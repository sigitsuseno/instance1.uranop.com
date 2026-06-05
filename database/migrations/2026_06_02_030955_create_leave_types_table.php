<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('category', ['leave', 'permit', 'sick', 'special']);
            $table->enum('balance_type', ['decrement', 'increment', 'none'])->default('decrement');
            $table->boolean('is_paid')->default(true);
            $table->boolean('affects_daily_worker')->default(true);
            $table->boolean('affects_monthly_worker')->default(true);
            $table->boolean('requires_medical_doc')->default(false);
            $table->string('color_hex', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('max_days')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
