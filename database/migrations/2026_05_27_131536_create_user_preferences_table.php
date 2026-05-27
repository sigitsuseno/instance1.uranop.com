<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('theme', ['light', 'dark', 'system'])->default('system');
            $table->string('language', 10)->default('id');
            $table->string('date_format', 20)->default('d M Y');
            $table->string('time_format', 20)->default('H:i');
            $table->string('timezone', 50)->default('Asia/Jakarta');

            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_whatsapp')->default(false);

            $table->string('default_dashboard', 50)->default('main');
            $table->json('dashboard_layout')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
