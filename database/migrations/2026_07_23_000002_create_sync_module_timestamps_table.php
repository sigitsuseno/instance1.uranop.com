<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_module_timestamps', function (Blueprint $table) {
            $table->string('module_name', 100)->primary();
            $table->timestamp('last_modified_at')->useCurrent();
        });

        // Populate initial timestamps from each syncable module's MAX(updated_at)
        $modules = [
            'companies'              => 'companies',
            'branches'               => 'branches',
            'departments'            => 'departments',
            'positions'              => 'positions',
            'salary-grades'          => 'salary_grades',
            'salary-components'      => 'salary_components',
            'bpjs-configs'           => 'bpjs_configs',
            'pph-configs'            => 'pph_configs',
            'thr-configs'            => 'thr_configs',
            'employee-group-masters' => 'employee_group_masters',
            'employee-group-settings'=> 'employee_group_settings',
            'settings'               => 'system_settings',
            'users'                  => 'users',
            'roles'                  => 'roles',
            'permissions'            => 'permissions',
            'employees'              => 'employees',
            'employee-contracts'     => 'employee_contracts',
            'employee-salaries'      => 'employee_salaries',
            'employee-salary-components' => 'employee_salary_components',
            'employee-bpjs'          => 'employee_bpjs',
            'work-patterns'          => 'work_patterns',
            'work-pattern-details'   => 'work_pattern_details',
            'shifts'                 => 'shifts',
            'employee-shift-rosters' => 'employee_shift_rosters',
            'working-calendars'      => 'working_calendars',
            'holidays'               => 'holidays',
            'attendance-prepare'     => 'attendance_prepare',
            'attendance-records'     => 'attendance_records',
            'raw-logs'               => 'raw_logs',
            'manual-detects'         => 'manual_detects',
            'leave-types'            => 'leave_types',
            'leave-policies'         => 'leave_policies',
            'leave-periods'          => 'leave_periods',
            'leave-requests'         => 'leave_requests',
            'pay-periods'            => 'pay_periods',
            'pay-records'            => 'pay_records',
            'kasbon-requests'        => 'kasbon_requests',
        ];

        foreach ($modules as $moduleName => $tableName) {
            try {
                $max = DB::table($tableName)->max('updated_at');
                if ($max) {
                    DB::table('sync_module_timestamps')->insert([
                        'module_name' => $moduleName,
                        'last_modified_at' => $max,
                    ]);
                } else {
                    DB::table('sync_module_timestamps')->insert([
                        'module_name' => $moduleName,
                        'last_modified_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Table might not exist yet — skip
                DB::table('sync_module_timestamps')->insert([
                    'module_name' => $moduleName,
                    'last_modified_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_module_timestamps');
    }
};
