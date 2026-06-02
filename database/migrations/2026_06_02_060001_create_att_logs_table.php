<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('employee_code', 50)->nullable()->index();
            $table->string('employee_name', 200)->nullable();
            $table->dateTime('scan_datetime')->index();
            $table->string('scan_type', 20)->nullable()->comment('in/out/break');
            $table->string('machine_sn', 100)->nullable();
            $table->string('machine_name', 100)->nullable();
            $table->string('verify_type', 50)->nullable();
            $table->string('pin', 50)->nullable();
            $table->boolean('is_processed')->default(false)->index();
            $table->dateTime('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->string('import_batch', 50)->nullable()->index();
            $table->string('source_file', 255)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_logs');
    }
};
