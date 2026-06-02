<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sch_work_patterns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('employee_type')->nullable(); 

            $table->integer('cut_off_date')->nullable();
            $table->boolean('sun_overtime')->default(false);
            
            $table->integer('work_day')->default(5);
            $table->string('sat_type')->default('off'); 
            $table->boolean('is_half_day_all')->default(false);
            
            $table->integer('work_day_hours')->default(8);
            $table->integer('half_day_hours')->default(4);
            $table->integer('wd_rest_hours')->default(1);
            $table->integer('hd_rest_hours')->default(0);
            
            $table->boolean('is_active')->default(true);
            
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
        Schema::dropIfExists('sch_work_patterns');
    }
};
