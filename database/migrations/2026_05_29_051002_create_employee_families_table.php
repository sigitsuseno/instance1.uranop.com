<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->enum('relation', ['spouse', 'child', 'parent', 'sibling', 'other']);
            $table->string('name', 200);
            $table->enum('gender', ['L', 'P']);
            $table->string('nik', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('education', 100)->nullable();
            $table->string('occupation', 100)->nullable();

            $table->boolean('is_dependent')->default(false)->comment('Tanggungan PTKP');
            $table->boolean('is_emergency_contact')->default(false);
            $table->string('emergency_phone', 50)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'relation']);
            $table->index('is_dependent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_families');
    }
};
