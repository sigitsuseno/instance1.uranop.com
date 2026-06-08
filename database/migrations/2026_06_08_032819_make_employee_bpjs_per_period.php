<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ubah employee_bpjs dari one-to-one (unique employee_id) jadi per periode (composite employee_id + pay_period_id) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_bpjs', function (Blueprint $table) {
            // 1. Add regular index dulu (MySQL perlu index untuk FK)
            $table->index('employee_id', 'employee_bpjs_employee_id_index');

            // 2. Drop unique constraint on employee_id
            $table->dropUnique('employee_bpjs_employee_id_unique');

            // 3. Add composite unique (employee_id + pay_period_id)
            $table->unique(['employee_id', 'pay_period_id'], 'employee_bpjs_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employee_bpjs', function (Blueprint $table) {
            $table->dropUnique('employee_bpjs_period_unique');
            $table->dropIndex('employee_bpjs_employee_id_index');
            $table->unique('employee_id');
        });
    }
};
