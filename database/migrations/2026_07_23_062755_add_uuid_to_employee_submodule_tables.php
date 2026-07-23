<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'employee_contracts',
        'employee_families',
        'employee_documents',
        'employee_salaries',
        'employee_salary_components',
        'employee_position_histories',
        'employee_terminations',
    ];

    /**
     * Run the migrations.
     * Tambah kolom uuid ke 7 tabel employee submodule.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            // 1. Add uuid column (nullable dulu)
            Schema::table($table, function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            // 2. Generate UUID untuk existing records
            DB::table($table)->whereNull('uuid')->eachById(function ($row) use ($table) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });

            // 3. Unique index
            try {
                Schema::table($table, function (Blueprint $table) {
                    $table->uuid('uuid')->unique()->change();
                });
            } catch (\Throwable $e) {
                // Fallback: add index manually
                DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$table}_uuid_unique` (`uuid`)");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique("{$table}_uuid_unique");
                $table->dropColumn('uuid');
            });
        }
    }
};
