<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->date('tanggal_penggajian')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('pay_periods', function (Blueprint $table) {
            $table->dropColumn('tanggal_penggajian');
        });
    }
};
