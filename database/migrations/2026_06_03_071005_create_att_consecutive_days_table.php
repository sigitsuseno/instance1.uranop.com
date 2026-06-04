<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_consecutive_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('consecutive_count')->default(1);
            $table->string('break_reason', 50)->nullable()->comment('off, leave, absent, holiday');
            $table->boolean('alert_triggered')->default(false)->comment('Alert jika melebihi batas');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_consecutive_days');
    }
};
