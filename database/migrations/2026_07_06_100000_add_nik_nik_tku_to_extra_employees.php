<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extra_employees', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->after('kode');
            $table->string('nik_tku', 50)->nullable()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('extra_employees', function (Blueprint $table) {
            $table->dropColumn(['nik', 'nik_tku']);
        });
    }
};
