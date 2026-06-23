<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('superadmin', 'hrmanager', 'adm_manager', 'hrbranch', 'hr_ast', 'manajemen') DEFAULT 'hr_ast'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('superadmin', 'hrmanager', 'adm_manager', 'hrbranch', 'hr_ast') DEFAULT 'hr_ast'");
    }
};
