<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename overtime_rules → att_overtime_rules (shared table)
        if (Schema::hasTable('overtime_rules') && !Schema::hasTable('att_overtime_rules')) {
            Schema::rename('overtime_rules', 'att_overtime_rules');
        }
        if (Schema::hasTable('overtime_rule_details') && !Schema::hasTable('att_overtime_rule_details')) {
            Schema::rename('overtime_rule_details', 'att_overtime_rule_details');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('att_overtime_rules') && !Schema::hasTable('overtime_rules')) {
            Schema::rename('att_overtime_rules', 'overtime_rules');
        }
        if (Schema::hasTable('att_overtime_rule_details') && !Schema::hasTable('overtime_rule_details')) {
            Schema::rename('att_overtime_rule_details', 'overtime_rule_details');
        }
    }
};
