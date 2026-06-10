<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_rules', function (Blueprint $table) {
            $table->boolean('is_saturday')->default(false)->after('is_holiday');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_rules', function (Blueprint $table) {
            $table->dropColumn('is_saturday');
        });
    }
};
