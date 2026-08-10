<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Restruktur employee_pph:
 * 1. Tambah kolom (skip kalau sudah ada)
 * 2. Unique constraint employee_id + pay_period_id
 * 3. Index untuk FK & performance
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Tambah kolom baru ──
        Schema::table('employee_pph', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_pph', 'source_salary_id')) {
                $table->foreignId('source_salary_id')
                    ->nullable()
                    ->after('pay_record_id')
                    ->constrained('employee_salaries')
                    ->nullOnDelete()
                    ->comment('Referensi ke data gaji sumber perhitungan (employee_salaries)');
            }

            if (! Schema::hasColumn('employee_pph', 'source_bpjs_id')) {
                $table->foreignId('source_bpjs_id')
                    ->nullable()
                    ->after('source_salary_id')
                    ->constrained('employee_bpjs')
                    ->nullOnDelete()
                    ->comment('Referensi ke data BPJS per periode');
            }

            if (! Schema::hasColumn('employee_pph', 'nik')) {
                $table->string('nik', 16)
                    ->nullable()
                    ->after('npwp')
                    ->comment('NIK 16 digit (= NPWP)');
            }

            if (! Schema::hasColumn('employee_pph', 'bpjs_kes_karyawan')) {
                $table->decimal('bpjs_kes_karyawan', 15, 2)
                    ->default(0)
                    ->after('bpjs_jp_karyawan')
                    ->comment('BPJS Kesehatan porsi karyawan');
            }

            // Perbaiki npwp comment
            $table->string('npwp', 50)
                ->nullable()
                ->comment('NPWP format lama 15 digit')
                ->change();
        });

        // ── 2. Unique constraint ──
        $this->safeDropForeign('employee_pph_employee_id_foreign');
        $this->safeDropForeign('employee_pph_pay_period_id_foreign');
        $this->safeDropIndex('employee_pph', 'employee_pph_employee_id_pay_period_id_index');
        $this->safeCreateUnique('employee_pph', ['employee_id', 'pay_period_id'], 'uq_employee_pph_period');
        $this->safeCreateForeign('employee_pph', 'employee_id', 'employees');
        $this->safeCreateForeign('employee_pph', 'pay_period_id', 'pay_periods');

        // ── 3. Index tambahan ──
        $this->safeCreateIndex('employee_pph', 'source_salary_id');
        $this->safeCreateIndex('employee_pph', 'source_bpjs_id');
        $this->safeCreateIndex('employee_pph', 'is_december_calc');
    }

    public function down(): void
    {
        Schema::table('employee_pph', function (Blueprint $table) {
            if (Schema::hasColumn('employee_pph', 'source_salary_id')) {
                $table->dropForeign(['source_salary_id']);
            }
            if (Schema::hasColumn('employee_pph', 'source_bpjs_id')) {
                $table->dropForeign(['source_bpjs_id']);
            }
            $table->dropColumn(['source_salary_id', 'source_bpjs_id', 'nik', 'bpjs_kes_karyawan']);
            $table->string('npwp', 50)->nullable()->change();
        });

        $this->safeDropForeign('employee_pph_employee_id_foreign');
        $this->safeDropForeign('employee_pph_pay_period_id_foreign');
        $this->safeDropIndex('employee_pph', 'uq_employee_pph_period');
        $this->safeCreateIndex('employee_pph', ['employee_id', 'pay_period_id'], 'employee_pph_employee_id_pay_period_id_index');
        $this->safeCreateForeign('employee_pph', 'employee_id', 'employees');
        $this->safeCreateForeign('employee_pph', 'pay_period_id', 'pay_periods');
    }

    // ── Helpers ──

    private function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function safeDropForeign(string $fkName): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND TABLE_SCHEMA = ? AND TABLE_NAME = 'employee_pph'
               AND CONSTRAINT_NAME = ?",
            [DB::getDatabaseName(), $fkName]
        );
        if ($exists && $exists->cnt > 0) {
            Schema::table('employee_pph', fn(Blueprint $t) => $t->dropForeign($fkName));
        }
    }

    private function safeDropIndex(string $table, string $indexName): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [DB::getDatabaseName(), $table, $indexName]
        );
        if ($exists && $exists->cnt > 0) {
            Schema::table($table, fn(Blueprint $t) => $t->dropIndex($indexName));
        }
    }

    private function safeCreateForeign(string $table, string $column, string $refTable): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $fkName = "{$table}_{$column}_foreign";
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?",
            [DB::getDatabaseName(), $table, $fkName]
        );
        if (! $exists || $exists->cnt == 0) {
            Schema::table($table, function (Blueprint $t) use ($column, $refTable) {
                $t->foreign($column)->references('id')->on($refTable)->cascadeOnDelete();
            });
        }
    }

    private function safeCreateUnique(string $table, array $columns, string $name): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_TYPE = 'UNIQUE'
               AND TABLE_SCHEMA = ? AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?",
            [DB::getDatabaseName(), $table, $name]
        );
        if (! $exists || $exists->cnt == 0) {
            Schema::table($table, fn(Blueprint $t) => $t->unique($columns, $name));
        }
    }

    private function safeCreateIndex(string $table, string|array $columns, ?string $name = null): void
    {
        if (! $this->isMySql()) {
            return;
        }

        $indexName = $name ?? (is_array($columns) ? implode('_', $columns) . '_index' : $columns . '_index');
        // Laravel auto-generates index name: {table}_{columns}_{index}
        $autoName = $table . '_' . (is_array($columns) ? implode('_', $columns) : $columns) . '_index';
        $exists = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME IN (?, ?)",
            [DB::getDatabaseName(), $table, $indexName, $autoName]
        );
        if (! $exists || $exists->cnt == 0) {
            Schema::table($table, fn(Blueprint $t) => $t->index($columns, $name));
        }
    }
};
