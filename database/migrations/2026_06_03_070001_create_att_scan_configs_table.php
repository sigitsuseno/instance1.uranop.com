<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_scan_configs', function (Blueprint $table) {
            $table->id();
            $table->string('machine_sn', 100)->nullable()->index();
            $table->string('machine_name', 100)->nullable();
            $table->json('scan_type_rules')->nullable()->comment('Rules: time ranges untuk deteksi in/out/break');
            // Example: [{"type":"in","start":"05:00","end":"09:00"},{"type":"out","start":"16:00","end":"20:00"}]
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_scan_configs');
    }
};
