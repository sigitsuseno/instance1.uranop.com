<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supervisor_att_snapshot', function (Blueprint $table) {
            $table->decimal('lm', 8, 2)->default(0)->change();
            $table->decimal('lm_count', 8, 2)->default(0)->change();
            $table->decimal('lembur', 8, 2)->default(0)->change();
            $table->decimal('lembur_count', 8, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('supervisor_att_snapshot', function (Blueprint $table) {
            $table->integer('lm')->default(0)->change();
            $table->integer('lm_count')->default(0)->change();
            $table->integer('lembur')->default(0)->change();
            $table->integer('lembur_count')->default(0)->change();
        });
    }
};
