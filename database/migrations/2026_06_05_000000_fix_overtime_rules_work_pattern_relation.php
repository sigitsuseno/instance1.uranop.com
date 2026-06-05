<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_rules', function (Blueprint $table) {
            $table->dropForeign(['work_pattern_id']);
        });

        Schema::dropIfExists('work_patterns');

        Schema::table('overtime_rules', function (Blueprint $table) {
            $table->foreign('work_pattern_id')->references('id')->on('sch_work_patterns')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_rules', function (Blueprint $table) {
            $table->dropForeign(['work_pattern_id']);
        });
    }
};
