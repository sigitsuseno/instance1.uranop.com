<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('reference_code')->index()->comment('Relasi dinamis ke tabel lain misal shift/work_pattern atau employee_group_masters');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            // Mencegah duplikasi referensi yang sama untuk satu karyawan
            $table->unique(['employee_id', 'reference_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_groups');
    }
};
