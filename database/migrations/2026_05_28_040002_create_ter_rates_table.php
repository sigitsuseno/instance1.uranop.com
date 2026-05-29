<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ter_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pph_config_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['A', 'B', 'C']);
            $table->decimal('min_income', 15, 2);
            $table->decimal('max_income', 15, 2)->nullable();
            $table->decimal('rate', 5, 2); 
            $table->string('description')->nullable();
            $table->integer('sort_order')->default(0);
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->index(['pph_config_id', 'category']);
            $table->index(['pph_config_id', 'min_income', 'max_income']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ter_rates');
    }
};
