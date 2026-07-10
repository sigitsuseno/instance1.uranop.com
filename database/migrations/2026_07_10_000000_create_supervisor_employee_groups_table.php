<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_employee_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('group_name');
            $table->string('group_code');
            $table->json('group_component')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_start', 'period_end']);
            $table->index('group_code');
            $table->unique(['employee_id', 'group_code', 'period_start', 'period_end'], 'uix_emp_group_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_employee_groups');
    }
};
