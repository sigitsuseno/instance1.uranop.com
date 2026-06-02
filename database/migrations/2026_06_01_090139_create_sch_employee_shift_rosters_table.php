<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sch_employee_shift_rosters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 
            
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('sch_shifts')->nullOnDelete();
            $table->foreignId('work_pattern_id')->nullable()->constrained('sch_work_patterns')->nullOnDelete();
            
            $table->date('date');
            $table->string('shift_code')->nullable();
            $table->string('work_pattern_type')->nullable();
            $table->string('external_code')->nullable();
            
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_sat')->default(false);
            $table->boolean('is_sun')->default(false);
            $table->boolean('is_half_day')->default(false);
            
            $table->boolean('is_leave')->default(false);
            $table->boolean('is_permit')->default(false); 
            $table->unsignedBigInteger('leave_id')->nullable(); 
            
            $table->string('status')->default('scheduled'); 
            $table->string('source')->nullable(); 
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at')->nullable(); 
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['employee_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sch_employee_shift_rosters');
    }
};
