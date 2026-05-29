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
        Schema::create('thr_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->integer('min_months');
            $table->integer('max_months')->nullable();
            $table->boolean('is_prorated')->default(false);
            $table->decimal('percentage', 5, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thr_configs');
    }
};
