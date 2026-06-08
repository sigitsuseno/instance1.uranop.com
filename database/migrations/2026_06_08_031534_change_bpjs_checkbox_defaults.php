<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Default checkbox BPJS jadi true — karena lebih banyak yg ikut daripada nggak */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_bpjs', function (Blueprint $table) {
            $table->boolean('has_bpjs_tk')->default(true)->change();
            $table->boolean('has_bpjs_ks')->default(true)->change();
            $table->boolean('has_bpjs_pen')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_bpjs', function (Blueprint $table) {
            $table->boolean('has_bpjs_tk')->default(false)->change();
            $table->boolean('has_bpjs_ks')->default(false)->change();
            $table->boolean('has_bpjs_pen')->default(false)->change();
        });
    }
};
