<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename transaction_type: addition→increment, deduction→decrement
     * + Revert unique constraint ke 4 kolom (tanpa reference_id)
     * + Aggregate: gabung deduction per (employee, type, period) jadi 1 record
     */
    public function up(): void
    {
        // 1. Aggregate decrement: SUM per (employee_id, leave_type_id, leave_period_id)
        $duplicates = DB::select("
            SELECT employee_id, leave_type_id, leave_period_id,
                   SUM(amount) as total_amount,
                   MIN(id) as keep_id
            FROM employee_leaves
            WHERE transaction_type = 'deduction'
            GROUP BY employee_id, leave_type_id, leave_period_id
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $d) {
            DB::table('employee_leaves')->where('id', $d->keep_id)->update([
                'amount' => $d->total_amount,
                'description' => 'Akumulasi potongan cuti',
                'reference_id' => null,
            ]);
            DB::table('employee_leaves')
                ->where('employee_id', $d->employee_id)
                ->where('leave_type_id', $d->leave_type_id)
                ->where('leave_period_id', $d->leave_period_id)
                ->where('transaction_type', 'deduction')
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        // 2. Drop FK + old unique constraint (include reference_id)
        DB::statement('ALTER TABLE employee_leaves DROP FOREIGN KEY employee_leaves_employee_id_foreign');
        DB::statement('ALTER TABLE employee_leaves DROP INDEX uq_employee_leave_period_type_ref');

        // 3. Buat unique constraint baru (4 kolom, tanpa reference_id)
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'leave_type_id', 'leave_period_id', 'transaction_type'],
                'uq_employee_leave_period_type'
            );
            $table->foreign('employee_id')->references('id')->on('employees');
        });

        // 4. Buka enum dulu (tambah nilai baru)
        DB::statement("ALTER TABLE employee_leaves MODIFY COLUMN transaction_type ENUM('addition','deduction','increment','decrement') NOT NULL DEFAULT 'increment'");

        // 5. Convert existing data
        DB::table('employee_leaves')->where('transaction_type', 'addition')->update(['transaction_type' => 'increment']);
        DB::table('employee_leaves')->where('transaction_type', 'deduction')->update(['transaction_type' => 'decrement']);

        // 6. Kunci enum (hapus nilai lama)
        DB::statement("ALTER TABLE employee_leaves MODIFY COLUMN transaction_type ENUM('increment','decrement') NOT NULL DEFAULT 'increment'");
    }

    public function down(): void
    {
        // Buka enum
        DB::statement("ALTER TABLE employee_leaves MODIFY COLUMN transaction_type ENUM('addition','deduction','increment','decrement') NOT NULL DEFAULT 'addition'");

        // Convert back
        DB::table('employee_leaves')->where('transaction_type', 'increment')->update(['transaction_type' => 'addition']);
        DB::table('employee_leaves')->where('transaction_type', 'decrement')->update(['transaction_type' => 'deduction']);

        // Kunci enum
        DB::statement("ALTER TABLE employee_leaves MODIFY COLUMN transaction_type ENUM('addition','deduction') NOT NULL DEFAULT 'addition'");

        // Revert unique constraint
        DB::statement('ALTER TABLE employee_leaves DROP FOREIGN KEY employee_leaves_employee_id_foreign');
        DB::statement('ALTER TABLE employee_leaves DROP INDEX uq_employee_leave_period_type');

        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->unique(
                ['employee_id', 'leave_type_id', 'leave_period_id', 'transaction_type', 'reference_id'],
                'uq_employee_leave_period_type_ref'
            );
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }
};
