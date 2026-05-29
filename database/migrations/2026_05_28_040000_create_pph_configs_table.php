<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pph_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->enum('calculation_method', ['ter', 'progressive'])->default('ter');
            $table->enum('pph_method', ['gross', 'gross_up', 'net'])->default('gross');
            $table->boolean('non_npwp_penalty')->default(true);
            $table->decimal('non_npwp_multiplier', 5, 2)->default(1.2);
            $table->text('description')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pph_configs');
    }
};
