<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_overtime', function (Blueprint $table) {
            // Drop FK constraint dulu
            $table->dropForeign(['autolog_id']);
            // Ubah jadi nullable
            $table->unsignedBigInteger('autolog_id')->nullable()->change();
            // Pasang ulang FK dengan cascade on delete
            $table->foreign('autolog_id')->references('id')->on('attendance_autologs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_overtime', function (Blueprint $table) {
            $table->dropForeign(['autolog_id']);
            $table->unsignedBigInteger('autolog_id')->nullable(false)->change();
            $table->foreign('autolog_id')->references('id')->on('attendance_autologs')->cascadeOnDelete();
        });
    }
};