<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_overtimes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->date('date')->index();
            $table->unsignedBigInteger('overtime_rule_id')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->decimal('total_hours', 5, 2)->default(0);
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->decimal('calculated_hours', 5, 2)->default(0)->comment('total_hours * multiplier');
            $table->string('status', 20)->default('pending')->comment('pending, approved, rejected');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_overtimes');
    }
};
