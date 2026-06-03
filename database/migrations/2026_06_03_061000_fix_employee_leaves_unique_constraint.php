<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employee_leaves DROP FOREIGN KEY employee_leaves_employee_id_foreign');
        DB::statement('ALTER TABLE employee_leaves DROP INDEX uq_employee_leave_period_type');

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'leave_type_id', 'leave_period_id', 'transaction_type', 'reference_id'],
                'uq_employee_leave_period_type_ref'
            );
            // Re-add FK dengan index otomatis
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employee_leaves DROP FOREIGN KEY employee_leaves_employee_id_foreign');
        DB::statement('ALTER TABLE employee_leaves DROP INDEX uq_employee_leave_period_type_ref');

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'leave_type_id', 'leave_period_id', 'transaction_type'],
                'uq_employee_leave_period_type'
            );
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }
};
