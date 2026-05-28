<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('department_id')->constrained();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('job_grade', 20)->nullable();
            
            // Note: salary_grade_id will be added in phase 3, or we can just declare the column nullable without FK constraints for now, or add it later.
            // Let's add the column without strict FK for now since salary_grades table doesn't exist yet, we will add the FK constraint in Phase 3.
            $table->unsignedBigInteger('salary_grade_id')->nullable();
            
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions');
            $table->boolean('is_managerial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('max_incumbents')->default(1);
            $table->text('requirements')->nullable();
            
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'is_active']);
            $table->index('reports_to_position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
