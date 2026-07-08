<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('page', 50)->unique()->comment('Slug halaman: manual-sync, sync-kehadiran, import, recap, consecutive, dll');
            $table->json('config')->nullable()->comment('Pengaturan spesifik per halaman');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_configs');
    }
};
