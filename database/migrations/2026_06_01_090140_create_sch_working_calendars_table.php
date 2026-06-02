<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sch_working_calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); 
            
            $table->string('name');
            $table->integer('year');
            $table->text('description')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sch_working_calendars');
    }
};
