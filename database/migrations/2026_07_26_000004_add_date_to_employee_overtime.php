<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_overtime', function (Blueprint $table) {
            $table->date('date')->after('pay_periode_id');
            $table->unique(['employee_id', 'pay_periode_id', 'date'], 'emp_overtime_emp_period_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employee_overtime', function (Blueprint $table) {
            // FK employee_id butuh index dengan employee_id sebagai leftmost column.
            // Unique index (employee_id, pay_periode_id, date) sekarang jadi penopang FK.
            // Harus bikin pengganti dulu sebelum drop unique.
            $table->index('employee_id', 'employee_overtime_employee_id_fk_index');
            $table->dropUnique('emp_overtime_emp_period_date_unique');
            $table->dropColumn('date');
        });
    }
};
