<?php

namespace App\Modules\Sync\Services;

use App\Modules\Attendance\Models\AttendancePrepare;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Models\ManualDetect;
use App\Modules\Attendance\Models\RawLog;
use App\Modules\Auth\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Models\EmployeeBpjs;
use App\Modules\Employee\Models\EmployeeContract;
use App\Modules\Employee\Models\EmployeeSalary;
use App\Modules\Employee\Models\EmployeeSalaryComponent;
use App\Modules\Kasbon\Models\KasbonRequest;
use App\Modules\Leave\Models\LeavePeriod;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Position;
use App\Modules\Payroll\Models\PayPeriod;
use App\Modules\Payroll\Models\PayRecord;
use App\Modules\Schedule\Models\EmployeeShiftRoster;
use App\Modules\Schedule\Models\Holiday;
use App\Modules\Schedule\Models\Shift;
use App\Modules\Schedule\Models\WorkPattern;
use App\Modules\Schedule\Models\WorkPatternDetail;
use App\Modules\Schedule\Models\WorkingCalendar;
use App\Modules\Settings\Models\BpjsConfig;
use App\Modules\Settings\Models\EmployeeGroupMaster;
use App\Modules\Settings\Models\EmployeeGroupSetting;
use App\Modules\Settings\Models\PphConfig;
use App\Modules\Settings\Models\SalaryComponent;
use App\Modules\Settings\Models\SalaryGrade;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Settings\Models\ThrConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncService
{
    /**
     * List of all syncable modules with their metadata.
     */
    public static function modules(): array
    {
        return [
            // Organization data (reference)
            'companies'       => ['model' => Company::class, 'label' => 'Perusahaan', 'order' => 10],
            'branches'        => ['model' => Branch::class, 'label' => 'Cabang', 'order' => 20],
            'departments'     => ['model' => Department::class, 'label' => 'Bagian', 'order' => 30],
            'positions'       => ['model' => Position::class, 'label' => 'Pekerjaan', 'order' => 40],

            // Settings
            'salary-grades'   => ['model' => SalaryGrade::class, 'label' => 'Golongan Gaji', 'order' => 50],
            'salary-components' => ['model' => SalaryComponent::class, 'label' => 'Komponen Gaji', 'order' => 60],
            'bpjs-configs'    => ['model' => BpjsConfig::class, 'label' => 'Konfigurasi BPJS', 'order' => 70],
            'pph-configs'     => ['model' => PphConfig::class, 'label' => 'Konfigurasi PPh', 'order' => 80],
            'thr-configs'     => ['model' => ThrConfig::class, 'label' => 'Konfigurasi THR', 'order' => 90],
            'employee-group-masters' => ['model' => EmployeeGroupMaster::class, 'label' => 'Group Karyawan', 'order' => 100],
            'employee-group-settings' => ['model' => EmployeeGroupSetting::class, 'label' => 'Setting Group', 'order' => 110],
            'settings'        => ['model' => SystemSetting::class, 'label' => 'Pengaturan', 'order' => 120],

            // Auth / Users
            'users'           => ['model' => User::class, 'label' => 'User', 'order' => 130],
            'roles'           => ['model' => Role::class, 'label' => 'Role', 'order' => 140],
            'permissions'     => ['model' => Permission::class, 'label' => 'Permission', 'order' => 150],

            // Employee
            'employees'       => ['model' => Employee::class, 'label' => 'Karyawan', 'order' => 200],
            'employee-contracts' => ['model' => EmployeeContract::class, 'label' => 'Kontrak Karyawan', 'order' => 210],
            'employee-salaries' => ['model' => EmployeeSalary::class, 'label' => 'Gaji Karyawan', 'order' => 220],
            'employee-salary-components' => ['model' => EmployeeSalaryComponent::class, 'label' => 'Komponen Gaji Karyawan', 'order' => 230],
            'employee-bpjs'   => ['model' => EmployeeBpjs::class, 'label' => 'BPJS Karyawan', 'order' => 240],

            // Schedule
            'work-patterns'   => ['model' => WorkPattern::class, 'label' => 'Pola Kerja', 'order' => 300],
            'work-pattern-details' => ['model' => WorkPatternDetail::class, 'label' => 'Detail Pola Kerja', 'order' => 310],
            'shifts'          => ['model' => Shift::class, 'label' => 'Shift', 'order' => 320],
            'employee-shift-rosters' => ['model' => EmployeeShiftRoster::class, 'label' => 'Roster Shift', 'order' => 330],
            'working-calendars' => ['model' => WorkingCalendar::class, 'label' => 'Kalender Kerja', 'order' => 340],
            'holidays'        => ['model' => Holiday::class, 'label' => 'Hari Libur', 'order' => 350],

            // Attendance
            'attendance-prepare' => ['model' => AttendancePrepare::class, 'label' => 'Siap Absen', 'order' => 400],
            'attendance-records' => ['model' => AttendanceRecord::class, 'label' => 'Rekap Absensi', 'order' => 410],
            'raw-logs'        => ['model' => RawLog::class, 'label' => 'Log Mentah', 'order' => 420],
            'manual-detects'  => ['model' => ManualDetect::class, 'label' => 'Deteksi Manual', 'order' => 430],

            // Leave
            'leave-types'     => ['model' => LeaveType::class, 'label' => 'Tipe Cuti', 'order' => 460],
            'leave-policies'  => ['model' => LeavePolicy::class, 'label' => 'Kebijakan Cuti', 'order' => 470],
            'leave-periods'   => ['model' => LeavePeriod::class, 'label' => 'Periode Cuti', 'order' => 480],
            'leave-requests'  => ['model' => LeaveRequest::class, 'label' => 'Cuti', 'order' => 500],

            // Payroll
            'pay-periods'     => ['model' => PayPeriod::class, 'label' => 'Periode Penggajian', 'order' => 550],
            'pay-records'     => ['model' => PayRecord::class, 'label' => 'Penggajian', 'order' => 600],

            // Kasbon
            'kasbon-requests' => ['model' => KasbonRequest::class, 'label' => 'Kasbon', 'order' => 700],
        ];
    }

    /**
     * Update the last_modified_at timestamp for a sync module.
     * Called automatically by SyncTimestampable trait or manually after CRUD.
     */
    public static function updateModuleTimestamp(string $module): void
    {
        DB::table('sync_module_timestamps')->upsert(
            [
                'module_name' => $module,
                'last_modified_at' => now(),
            ],
            ['module_name'],
            ['last_modified_at']
        );
    }

    /**
     * Detect changes per module since a given timestamp.
     * Uses sync_module_timestamps cache for quick check, then queries models
     * only for modules that have changed.
     */
    public static function detectChanges(?string $since): array
    {
        $sinceDate = $since ? Carbon::parse($since) : now()->subYear();
        $changes = [];

        // Quick check: get modules with changed timestamps (1 query vs 35)
        try {
            $timestamps = DB::table('sync_module_timestamps')
                ->where('last_modified_at', '>', $sinceDate)
                ->pluck('last_modified_at', 'module_name');

            $changedModules = $timestamps->toArray();
        } catch (\Throwable) {
            // Fallback if sync_module_timestamps table doesn't exist yet
            $changedModules = null;
        }

        foreach (self::modules() as $key => $mod) {
            // Skip modules that haven't changed (if timestamps available)
            if ($changedModules !== null && !isset($changedModules[$key])) {
                continue;
            }

            // Query actual model to get exact count
            $model = $mod['model'];
            try {
                $count = $model::where('updated_at', '>', $sinceDate)
                    ->orWhere('created_at', '>', $sinceDate)
                    ->count();
            } catch (\Throwable) {
                continue; // Skip models that error
            }

            if ($count > 0) {
                $changes[] = [
                    'module' => $key,
                    'label'  => $mod['label'],
                    'count'  => $count,
                ];
            }
        }

        return $changes;
    }

    /**
     * Pull data for a specific module, optionally since a timestamp.
     */
    public static function pullModule(string $module, ?string $since): array
    {
        $modules = self::modules();

        if (!isset($modules[$module])) {
            return ['error' => "Module '{$module}' tidak dikenal.", 'data' => []];
        }

        $mod = $modules[$module];
        $model = $mod['model'];
        $query = $model::query();

        if ($since) {
            $sinceDate = Carbon::parse($since);
            $query->where(function ($q) use ($sinceDate) {
                $q->where('updated_at', '>', $sinceDate)
                  ->orWhere('created_at', '>', $sinceDate);
            });
        }

        // For models with soft deletes, include trashed
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model))) {
            $query->withTrashed();
        }

        $data = $query->orderBy('id')->get()->toArray();

        return [
            'module' => $module,
            'label'  => $mod['label'],
            'count'  => count($data),
            'data'   => $data,
        ];
    }

    /**
     * Process pushed batch data from desktop.
     * Each item: { uuid, server_id, data, action }
     *   action: created | updated | deleted
     */
    public static function pushModule(string $module, array $batch): array
    {
        $modules = self::modules();

        if (!isset($modules[$module])) {
            return ['error' => "Module '{$module}' tidak dikenal.", 'results' => []];
        }

        $modelClass = $modules[$module]['model'];
        $results = [];

        foreach ($batch as $item) {
            $action = $item['action'] ?? 'created';
            $serverId = $item['server_id'] ?? null;
            $data = $item['data'] ?? [];
            $uuid = $item['uuid'] ?? null;

            try {
                DB::beginTransaction();

                switch ($action) {
                    case 'deleted':
                        if ($serverId) {
                            $record = $modelClass::find($serverId);
                            if ($record) {
                                $record->delete();
                                $results[] = [
                                    'uuid' => $uuid,
                                    'server_id' => $serverId,
                                    'action' => 'deleted',
                                    'status' => 'ok',
                                ];
                            } else {
                                $results[] = [
                                    'uuid' => $uuid,
                                    'server_id' => $serverId,
                                    'action' => 'deleted',
                                    'status' => 'not_found',
                                ];
                            }
                        }
                        break;

                    case 'updated':
                        if ($serverId) {
                            $record = $modelClass::find($serverId);
                            if ($record) {
                                // Remove ID and timestamps from data to avoid overwrite
                                unset($data['id'], $data['created_at'], $data['updated_at']);
                                $record->update($data);
                                $results[] = [
                                    'uuid' => $uuid,
                                    'server_id' => $serverId,
                                    'action' => 'updated',
                                    'status' => 'ok',
                                ];
                            } else {
                                $results[] = [
                                    'uuid' => $uuid,
                                    'server_id' => $serverId,
                                    'action' => 'updated',
                                    'status' => 'not_found',
                                ];
                            }
                        }
                        break;

                    case 'created':
                    default:
                        // Remove ID fields to let DB auto-generate
                        unset($data['id'], $data['created_at'], $data['updated_at']);
                        $record = $modelClass::create($data);
                        $results[] = [
                            'uuid' => $uuid,
                            'server_id' => $record->id,
                            'action' => 'created',
                            'status' => 'ok',
                        ];
                        break;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $results[] = [
                    'uuid' => $uuid,
                    'server_id' => $serverId,
                    'action' => $action,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'module' => $module,
            'processed' => count($results),
            'results' => $results,
        ];
    }
}
