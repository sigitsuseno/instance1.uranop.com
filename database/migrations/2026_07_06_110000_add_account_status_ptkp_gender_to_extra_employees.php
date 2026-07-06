<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_employees', function (Blueprint $table) {
            $table->string('account', 50)->nullable()->after('nik_tku');
            $table->string('status_ptkp', 15)->nullable()->after('account');
            $table->string('gender', 1)->nullable()->after('status_ptkp');
        });
    }

    public function down(): void
    {
        Schema::table('extra_employees', function (Blueprint $table) {
            $table->dropColumn(['account', 'status_ptkp', 'gender']);
        });
    }
};
