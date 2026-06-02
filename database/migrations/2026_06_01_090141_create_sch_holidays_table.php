<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sch_holidays', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 
            
            $table->foreignId('working_calendar_id')->nullable()->constrained('sch_working_calendars')->cascadeOnDelete();
            
            $table->date('date');
            $table->string('description')->nullable();
            $table->string('type')->default('nasional'); // nasional, cuti_bersama, perusahaan
            
            $table->boolean('is_national_holiday')->default(false);
            $table->boolean('is_company_holiday')->default(false);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sch_holidays');
    }
};
