<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Jalankan query secara langsung (sama dengan script sebelumnya)
        DB::table('employees')->update([
            'has_npwp' => true,
            'npwp' => DB::raw('COALESCE(npwp, nik)')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dalam kasus ini tidak perlu di-reverse, karena datanya sudah dimodifikasi
        // Jika butuh dikembalikan ke 'false' bisa ditambahkan di sini.
    }
};
