<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom premi di employee_pph.
 * Sebelumnya perhitungan PPh tidak menyertakan premi sebagai komponen
 * penghasilan bruto (padahal Employee::premi() dipakai payroll utama dan
 * PphCalculationService). Kolom ini mencatat premi per periode sebagai
 * komponen terpisah, konsisten dengan gaji_pokok & tunjangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_pph', function (Blueprint $table) {
            if (! Schema::hasColumn('employee_pph', 'premi')) {
                $table->decimal('premi', 15, 2)
                    ->default(0)
                    ->after('tunjangan')
                    ->comment('Premi / bonus tetap per periode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_pph', function (Blueprint $table) {
            if (Schema::hasColumn('employee_pph', 'premi')) {
                $table->dropColumn('premi');
            }
        });
    }
};
