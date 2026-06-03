<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Bersihkan data duplicate dulu (keep oldest record per combination)
        DB::statement('
            DELETE e1 FROM employee_leaves e1
            INNER JOIN employee_leaves e2
            WHERE e1.id > e2.id
              AND e1.employee_id = e2.employee_id
              AND e1.leave_type_id = e2.leave_type_id
              AND e1.leave_period_id = e2.leave_period_id
              AND e1.transaction_type = e2.transaction_type
        ');

        // 2. Tambahkan unique index untuk cegah duplicate di masa depan
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'leave_type_id', 'leave_period_id', 'transaction_type'],
                'uq_employee_leave_period_type'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropUnique('uq_employee_leave_period_type');
        });
    }
};
