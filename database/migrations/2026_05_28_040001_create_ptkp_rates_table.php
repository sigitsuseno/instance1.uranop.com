<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ptkp_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pph_config_id')->constrained()->cascadeOnDelete();
            $table->string('status_code', 10); // TK0, TK1, K0, dll
            $table->string('status_name'); 
            $table->decimal('value', 15, 2);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            
            $table->unique(['pph_config_id', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ptkp_rates');
    }
};
