<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            
            // BPJS Kesehatan
            $table->decimal('kesehatan_company_pct', 5, 2)->default(4.00);
            $table->decimal('kesehatan_employee_pct', 5, 2)->default(1.00);
            $table->decimal('kesehatan_max_salary', 15, 2)->default(12000000);
            
            // BPJS Ketenagakerjaan
            $table->decimal('jht_company_pct', 5, 2)->default(3.70);
            $table->decimal('jht_employee_pct', 5, 2)->default(2.00);
            
            $table->decimal('jkk_company_pct', 5, 2)->default(0.24);
            $table->decimal('jkm_company_pct', 5, 2)->default(0.30);
            
            $table->decimal('jp_company_pct', 5, 2)->default(2.00);
            $table->decimal('jp_employee_pct', 5, 2)->default(1.00);
            $table->decimal('jp_max_salary', 15, 2)->default(10042300);
            
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
        Schema::dropIfExists('bpjs_configs');
    }
};
