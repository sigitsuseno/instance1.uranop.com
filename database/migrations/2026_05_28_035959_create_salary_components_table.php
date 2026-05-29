<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction']);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // otomatis ditambahkan ke pegawai baru
            $table->string('calculation_type')->default('fixed'); // fixed, percentage
            $table->string('description')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_components');
    }
};
