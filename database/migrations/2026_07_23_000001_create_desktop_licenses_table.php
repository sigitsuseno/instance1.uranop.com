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
        Schema::create('desktop_licenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('license_key', 64)->unique();
            $table->string('instance_name')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->date('licensed_until')->nullable();
            $table->string('status', 20)->default('active'); // active, revoked, expired
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_ip')->nullable();
            $table->string('token')->nullable(); // Sanctum token for desktop
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('desktop_licenses');
    }
};
