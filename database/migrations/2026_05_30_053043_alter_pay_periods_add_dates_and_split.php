<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->renameColumn('started_at', 'start_date');
            $table->renameColumn('closed_at', 'end_date');
            
            $table->boolean('is_split')->default(false)->after('period_month');
            $table->foreignId('system_setting_id')->nullable()->after('is_split')->constrained('system_settings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->dropForeign(['system_setting_id']);
            $table->dropColumn(['is_split', 'system_setting_id']);
            
            $table->renameColumn('start_date', 'started_at');
            $table->renameColumn('end_date', 'closed_at');
        });
    }
};
