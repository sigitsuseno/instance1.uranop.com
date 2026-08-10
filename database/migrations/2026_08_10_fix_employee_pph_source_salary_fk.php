<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaiki FK employee_pph.source_salary_id:
 * Sebelumnya menunjuk ke employee_salary_components, padahal isi kolom
 * diambil dari activeSalary() yang membaca tabel employee_salaries
 * (sama seperti payroll utama GajiKaryawanController).
 * Drop FK lama, buat ulang menunjuk ke employee_salaries.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropFk('employee_pph_source_salary_id_foreign');

        if (Schema::hasColumn('employee_pph', 'source_salary_id')) {
            Schema::table('employee_pph', function ($table) {
                $table->foreign('source_salary_id')
                    ->references('id')
                    ->on('employee_salaries')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropFk('employee_pph_source_salary_id_foreign');

        if (Schema::hasColumn('employee_pph', 'source_salary_id')) {
            Schema::table('employee_pph', function ($table) {
                $table->foreign('source_salary_id')
                    ->references('id')
                    ->on('employee_salary_components')
                    ->nullOnDelete();
            });
        }
    }

    private function dropFk(string $fkName): void
    {
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND TABLE_SCHEMA = ? AND TABLE_NAME = 'employee_pph'
               AND CONSTRAINT_NAME = ?",
            [DB::getDatabaseName(), $fkName]
        );
        if ($exists && $exists->cnt > 0) {
            Schema::table('employee_pph', fn ($t) => $t->dropForeign($fkName));
        }
    }
};
