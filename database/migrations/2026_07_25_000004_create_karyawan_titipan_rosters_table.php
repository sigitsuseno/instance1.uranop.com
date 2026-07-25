<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan_titipan_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_titipan_id')->constrained('karyawan_titipan')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['H', 'A', 'C', 'I', 'S', 'Off', '-'])->default('H');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique: 1 karyawan hanya punya 1 status per tanggal
            $table->unique(['karyawan_titipan_id', 'date'], 'kt_roster_unique');

            // Index
            $table->index('date');
            $table->index(['karyawan_titipan_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan_titipan_rosters');
    }
};
