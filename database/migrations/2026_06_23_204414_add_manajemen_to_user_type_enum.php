<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support MODIFY COLUMN or ENUM — skip
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('superadmin', 'hrmanager', 'adm_manager', 'hrbranch', 'hr_ast', 'manajemen') DEFAULT 'hr_ast'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN user_type ENUM('superadmin', 'hrmanager', 'adm_manager', 'hrbranch', 'hr_ast') DEFAULT 'hr_ast'");
    }
};
