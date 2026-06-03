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
        Schema::table('leave_types', function (Blueprint $table) {
            $table->boolean('affects_daily_worker')->default(true)->after('is_paid');
            $table->boolean('affects_monthly_worker')->default(true)->after('affects_daily_worker');
            $table->boolean('requires_medical_doc')->default(false)->after('affects_monthly_worker');
            $table->string('color_hex', 7)->nullable()->after('requires_medical_doc');
            $table->boolean('is_active')->default(true)->after('color_hex');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn([
                'affects_daily_worker',
                'affects_monthly_worker',
                'requires_medical_doc',
                'color_hex',
                'is_active',
            ]);
        });
    }
};
