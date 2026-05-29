<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_grade_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('salary_grade_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');
            $table->decimal('min_salary', 15, 2);
            $table->decimal('max_salary', 15, 2);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_grade_histories');
    }
};
