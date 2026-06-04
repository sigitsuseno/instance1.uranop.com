<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('period', 7)->comment('Format: YYYY-MM');
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->json('snapshot_data')->nullable()->comment('Full attendance data snapshot');
            $table->string('context', 10)->default('main')->comment('main, shadow');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_snapshots');
    }
};
