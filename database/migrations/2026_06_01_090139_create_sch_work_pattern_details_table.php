<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sch_work_pattern_details', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 
            
            $table->foreignId('work_pattern_id')->constrained('sch_work_patterns')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('sch_shifts')->nullOnDelete();
            
            $table->string('name')->nullable(); 
            $table->integer('cycle_day')->default(7); 
            $table->integer('day_number'); 
            
            $table->string('day_type')->default('work_day'); 
            $table->boolean('is_workday')->default(true);
            $table->boolean('is_half_day')->default(false);
            
            $table->string('date_label')->nullable(); 
            
            $table->timestamps();
            
            $table->index(['work_pattern_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sch_work_pattern_details');
    }
};
