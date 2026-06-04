<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('att_autologs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->date('date')->index();
            $table->dateTime('first_scan')->nullable();
            $table->dateTime('last_scan')->nullable();
            $table->integer('total_scans')->default(0);
            $table->string('status', 20)->nullable()->comment('present, late, absent, half_day, off, holiday');
            $table->integer('late_minutes')->default(0);
            $table->integer('early_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->json('scan_data')->nullable()->comment('Rincian per-scan: jam, type, machine');
            $table->string('source', 20)->default('auto')->comment('auto, manual');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'date'], 'att_autologs_employee_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('att_autologs');
    }
};
