<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->integer('cut_off_date')->nullable()->after('value');
            $table->string('working_day_type')->default('fixed')->after('cut_off_date'); // fixed, calendar, flexible
            $table->integer('fixed_working_day')->nullable()->after('working_day_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['cut_off_date', 'working_day_type', 'fixed_working_day']);
        });
    }
};
