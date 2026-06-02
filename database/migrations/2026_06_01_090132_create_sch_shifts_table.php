<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sch_shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 

            $table->foreignId('work_pattern_id')->nullable()->constrained('sch_work_patterns')->nullOnDelete();
            
            $table->string('code')->unique();
            $table->string('name');
            $table->string('external_code')->nullable(); 

            $table->time('work_hour_start')->nullable();
            $table->time('work_hour_end')->nullable();
            
            $table->string('shift_checkin_options')->nullable(); 
            $table->time('check_in_start')->nullable();
            $table->time('check_in_end')->nullable();
            $table->time('check_out_start')->nullable();
            $table->time('check_out_end')->nullable();
            
            $table->boolean('is_overnight')->default(false);
            $table->time('check_out_overnight_start')->nullable();
            $table->time('check_out_overnight_end')->nullable();

            $table->integer('tolerance_minutes')->default(0);
            $table->integer('min_work_hours')->nullable();
            $table->boolean('has_overtime')->default(false);
            $table->decimal('overtime_multiplier', 5, 2)->nullable();
            
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_dayoff')->default(false); 
            
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sch_shifts');
    }
};
