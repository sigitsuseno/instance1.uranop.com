<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename old bpjs_config column names → new spec.
 * Old: *_company_pct/*_employee_pct → New: *_employer/*_employee
 * Drop: kesehatan_max_salary, jp_max_salary → Add: max_wage_cap
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bpjs_configs', function (Blueprint $table) {
            // 1. Rename all pct columns
            $this->renameIfExists('kesehatan_company_pct', 'kesehatan_employer');
            $this->renameIfExists('kesehatan_employee_pct', 'kesehatan_employee');
            $this->renameIfExists('jht_company_pct', 'jht_employer');
            $this->renameIfExists('jht_employee_pct', 'jht_employee');
            $this->renameIfExists('jkk_company_pct', 'jkk');
            $this->renameIfExists('jkm_company_pct', 'jkm');
            $this->renameIfExists('jp_company_pct', 'jp_employer');
            $this->renameIfExists('jp_employee_pct', 'jp_employee');

            // 2. Drop old max_salary columns
            if (Schema::hasColumn('bpjs_configs', 'kesehatan_max_salary')) {
                $table->dropColumn('kesehatan_max_salary');
            }
            if (Schema::hasColumn('bpjs_configs', 'jp_max_salary')) {
                $table->dropColumn('jp_max_salary');
            }

            // 3. Add single max_wage_cap (default 12jt)
            if (! Schema::hasColumn('bpjs_configs', 'max_wage_cap')) {
                $table->decimal('max_wage_cap', 15, 2)->default(12000000);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bpjs_configs', function (Blueprint $table) {
            $this->renameIfExists('kesehatan_employer', 'kesehatan_company_pct');
            $this->renameIfExists('kesehatan_employee', 'kesehatan_employee_pct');
            $this->renameIfExists('jht_employer', 'jht_company_pct');
            $this->renameIfExists('jht_employee', 'jht_employee_pct');
            $this->renameIfExists('jkk', 'jkk_company_pct');
            $this->renameIfExists('jkm', 'jkm_company_pct');
            $this->renameIfExists('jp_employer', 'jp_company_pct');
            $this->renameIfExists('jp_employee', 'jp_employee_pct');

            if (Schema::hasColumn('bpjs_configs', 'max_wage_cap')) {
                $table->dropColumn('max_wage_cap');
            }

            // Restore old
            if (! Schema::hasColumn('bpjs_configs', 'kesehatan_max_salary')) {
                $table->decimal('kesehatan_max_salary', 15, 2)->default(12000000);
            }
            if (! Schema::hasColumn('bpjs_configs', 'jp_max_salary')) {
                $table->decimal('jp_max_salary', 15, 2)->default(10042300);
            }
        });
    }

    /** Safe rename: only if old column exists and new doesn't */
    private function renameIfExists(string $from, string $to): void
    {
        if (Schema::hasColumn('bpjs_configs', $from) && ! Schema::hasColumn('bpjs_configs', $to)) {
            Schema::table('bpjs_configs', fn (Blueprint $table) =>
                $table->renameColumn($from, $to)
            );
        }
    }
};
